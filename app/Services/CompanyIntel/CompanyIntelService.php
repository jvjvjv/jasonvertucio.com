<?php

namespace App\Services\CompanyIntel;

use App\Enums\IntelStartMode;
use App\Enums\RoleIntelStatus;
use App\Exceptions\NoIntelSystemException;
use App\Exceptions\RoleIntelAlreadyExistsException;
use App\Exceptions\RoleIntelHasNoCompanyException;
use App\Exceptions\RoleIntelNotFailedException;
use App\Jobs\RunCompanyIntelJob;
use App\Models\Application;
use App\Models\Company;
use App\Models\RoleIntel;
use App\Services\Mcp\CompanyIntelToolRegistry;
use App\Services\TargetedResumeService;
use Generator;
use Illuminate\Database\QueryException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Jvjvjv\CodeTalker\Enums\AiConversationStatus;
use Jvjvjv\CodeTalker\Enums\AiInteractionStatus;
use Jvjvjv\CodeTalker\Models\AiConversation;
use Jvjvjv\CodeTalker\Models\AiConversationMessage;
use Jvjvjv\CodeTalker\Models\AiInteractionLog;
use Jvjvjv\CodeTalker\Models\AiLlmMessage;
use Jvjvjv\CodeTalker\Models\AiSystem;
use Jvjvjv\CodeTalker\Models\AiSystemPrompt;
use Jvjvjv\CodeTalker\Services\ConversationUsageService;
use Jvjvjv\CodeTalker\Services\LaravelAi\AgentFactory;
use Jvjvjv\CodeTalker\Services\LaravelAi\StreamTranslator;
use Laravel\Ai\Messages\AssistantMessage;
use Laravel\Ai\Messages\UserMessage;
use Laravel\Ai\Streaming\Events\StreamEnd;
use Laravel\Ai\Streaming\Events\StreamEvent;
use Laravel\Ai\Streaming\Events\ToolCall as ToolCallEvent;
use Laravel\Ai\Streaming\Events\ToolResult as ToolResultEvent;
use Throwable;

/**
 * Company Intel's lifecycle: starting an intel session for an application,
 * running its turns — streamed to the intel page or drained by the queue —
 * and correcting a wrong company.
 */
class CompanyIntelService
{
    /**
     * The Company Intel prompt's fixed built-in id, after the five seeded by
     * `seed_default_ai_system_prompts` ({@see TargetedResumeService}).
     * Ids up to 49 are reserved for built-in prompts.
     */
    public const int PROMPT_ID_COMPANY_INTEL = 6;

    public const string FEATURE = 'company-intel';

    public const string INITIAL_MESSAGE = 'User has just applied to this job. Please begin an analysis of the role and company.';

    /**
     * Agent steps (tool round trips) allowed per turn. Research turns call
     * web tools and the four recording tools, so this is above the chat's 10
     * but bounded to stay inside the queue worker's timeout.
     */
    private const int MAX_STEPS = 16;

    public function __construct(
        private AgentFactory $agentFactory,
        private ConversationUsageService $conversationUsageService,
        private CompanyIntelSystems $systems,
    ) {}

    /**
     * Start intel for an application: its session, first message and Role
     * intel, all in one transaction. A background start queues the first
     * turn once the transaction commits; a streamed start leaves it pending
     * for the intel page to run.
     *
     * @throws RoleIntelAlreadyExistsException
     */
    public function start(Application $application, AiSystem $system, IntelStartMode $mode, int|string|null $userId = null): RoleIntel
    {
        $roleIntel = DB::transaction(function () use ($application, $system, $mode, $userId): RoleIntel {
            $locked = Application::query()->lockForUpdate()->findOrFail($application->id);

            if (RoleIntel::query()->where('application_id', $locked->id)->exists()) {
                throw new RoleIntelAlreadyExistsException;
            }

            $conversation = AiConversation::create([
                'user_id' => $userId ?? auth()->id(),
                'ai_system_id' => $system->id,
                'feature' => self::FEATURE,
                'title' => $this->conversationTitle($locked),
                'status' => AiConversationStatus::Active,
                'context' => ['auto_start_pending' => $mode === IntelStartMode::Stream],
            ]);

            AiConversationMessage::create([
                'ai_conversation_id' => $conversation->id,
                'role' => 'system',
                'content' => self::instructions(AiSystemPrompt::find(self::PROMPT_ID_COMPANY_INTEL)?->content),
            ]);

            AiConversationMessage::create([
                'ai_conversation_id' => $conversation->id,
                'role' => 'user',
                'content' => self::initialMessage($locked),
            ]);

            return RoleIntel::create([
                'application_id' => $locked->id,
                'ai_conversation_id' => $conversation->id,
                'status' => RoleIntelStatus::Queued,
            ]);
        });

        if ($mode === IntelStartMode::Background) {
            RunCompanyIntelJob::dispatch($roleIntel->id)->afterCommit();
        }

        $application->setRelation('roleIntel', $roleIntel);

        return $roleIntel;
    }

    /**
     * Start intel with the system asked for, or the default when none is.
     *
     * @throws NoIntelSystemException when that system is not eligible, or there is no default
     * @throws RoleIntelAlreadyExistsException
     */
    public function startWith(Application $application, ?int $aiSystemId, IntelStartMode $mode): RoleIntel
    {
        $system = $this->systems->resolve($aiSystemId);

        if ($system === null) {
            throw new NoIntelSystemException;
        }

        return $this->start($application, $system, $mode);
    }

    /**
     * Start intel in the background with the default system, if there is one
     * and the application has none yet. Called after an `applied` entry has
     * committed, so nothing here may fail that: every error is logged and
     * swallowed.
     */
    public function autoStart(Application $application): void
    {
        try {
            if ($application->roleIntel()->exists()) {
                return;
            }

            $system = $this->systems->default();

            if ($system === null) {
                return;
            }

            $this->start($application, $system, IntelStartMode::Background);
        } catch (Throwable $e) {
            Log::warning('company-intel: automatic start failed', [
                'application_id' => $application->id,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Run the pending turn again after a failure.
     *
     * @throws RoleIntelNotFailedException
     */
    public function retry(RoleIntel $roleIntel, IntelStartMode $mode): RoleIntel
    {
        $roleIntel = DB::transaction(function () use ($roleIntel, $mode): RoleIntel {
            $locked = RoleIntel::query()->lockForUpdate()->findOrFail($roleIntel->id);

            if ($locked->status !== RoleIntelStatus::Failed) {
                throw new RoleIntelNotFailedException;
            }

            $locked->update(['status' => RoleIntelStatus::Queued, 'failure_reason' => null]);
            $this->setAutoStartPending($locked, $mode === IntelStartMode::Stream);

            return $locked;
        });

        if ($mode === IntelStartMode::Background) {
            RunCompanyIntelJob::dispatch($roleIntel->id)->afterCommit();
        }

        return $roleIntel;
    }

    /**
     * Detach a wrong company and remember it as rejected for this role. The
     * company is deleted with its insights when no other Role intel refers to
     * it. A replacement, when chosen, is attached directly; otherwise the
     * message that asks the agent to research the hint is returned, for the
     * intel page to send as the next turn so the admin watches it run.
     *
     * @throws RoleIntelHasNoCompanyException
     */
    public function rejectCompany(RoleIntel $roleIntel, ?int $replacementCompanyId, ?string $hint): ?string
    {
        return DB::transaction(function () use ($roleIntel, $replacementCompanyId, $hint): ?string {
            $locked = RoleIntel::query()->lockForUpdate()->with('company')->findOrFail($roleIntel->id);
            $wrong = $locked->company;

            if ($wrong === null) {
                throw new RoleIntelHasNoCompanyException;
            }

            $locked->rememberRejected($wrong);
            $locked->company()->dissociate();
            $locked->save();

            if (! RoleIntel::query()->where('company_id', $wrong->id)->exists()) {
                $wrong->delete();
            }

            if ($replacementCompanyId !== null) {
                $locked->company()->associate(Company::query()->findOrFail($replacementCompanyId));
                $locked->save();

                return null;
            }

            return self::correctionMessage($wrong->name, (string) $hint);
        });
    }

    public static function correctionMessage(string $wrongName, string $hint): string
    {
        return "The company you identified ({$wrongName}) is wrong. The correct company is: {$hint}. Please research the correct company and record it.";
    }

    /**
     * Run the job queued for this Role intel, discarding the stream.
     */
    public function runInBackground(RoleIntel $roleIntel): void
    {
        foreach ($this->runTurn($roleIntel) as $frame) {
            // Nobody is listening; the turn's results are its records.
        }
    }

    /**
     * Run one turn of the intel session: the pending user message, or the
     * admin's new one. Yields SSE frames in the applications chat's format.
     *
     * @return Generator<int, string>
     */
    public function runTurn(RoleIntel $roleIntel, ?string $userMessage = null): Generator
    {
        yield ": heartbeat\n\n";

        $conversation = AiConversation::query()->with('aiSystem')->findOrFail($roleIntel->ai_conversation_id);
        $system = $conversation->aiSystem;

        $this->setAutoStartPending($roleIntel, false, $conversation);

        if ($userMessage !== null && trim($userMessage) !== '') {
            AiConversationMessage::create([
                'ai_conversation_id' => $conversation->id,
                'role' => 'user',
                'content' => $userMessage,
            ]);
        }

        $roleIntel->update(['status' => RoleIntelStatus::Running, 'failure_reason' => null]);

        [$instructions, $history, $prompt] = $this->messagesFor($conversation);

        $startTime = microtime(true);
        $turnNumber = $this->nextTurnNumber($conversation);
        $registry = new CompanyIntelToolRegistry($conversation);

        try {
            if ($system === null) {
                throw new \RuntimeException('The AI system for this intel session no longer exists.');
            }

            $agent = $this->agentFactory->forSystem(
                $system,
                instructions: $instructions,
                messages: $history,
                tools: $registry->toLaravelAiTools($system->allowed_tools ?? []),
                maxSteps: self::MAX_STEPS,
            );

            $requestPayload = [
                'model' => $system->model,
                'max_tokens' => $system->max_tokens,
                'instructions' => $instructions,
                'messages' => [
                    ...array_map(
                        fn ($message): array => ['role' => $message->role->value, 'content' => $message->content],
                        $agent->messages(),
                    ),
                    ['role' => 'user', 'content' => $prompt],
                ],
            ];

            AiLlmMessage::create([
                'ai_conversation_id' => $conversation->id,
                'direction' => 'request',
                'turn_number' => (string) $turnNumber,
                'request_data' => $requestPayload,
                'created_at' => now(),
            ]);

            $translator = new StreamTranslator;
            $textBlocks = [];
            /** @var array<int, StreamEvent> $events */
            $events = [];
            $toolCalls = [];

            foreach ($agent->stream($prompt) as $event) {
                $events[] = $event;

                if ($event instanceof ToolCallEvent) {
                    $toolCalls[] = ['id' => $event->toolCall->id, 'name' => $event->toolCall->name];

                    yield 'data: '.json_encode([
                        'type' => 'tool_use_progress',
                        'text' => '',
                        'tools' => [$event->toolCall->name],
                    ])."\n\n";
                }

                foreach ($translator->translate($event) as $browserEvent) {
                    if ($browserEvent['type'] === 'content_block_delta') {
                        $textBlocks[] = $browserEvent['delta']['text'];
                    }

                    yield 'data: '.json_encode($browserEvent)."\n\n";
                }

                // A tool may have recorded findings: the page refreshes them
                // while the turn carries on.
                if ($event instanceof ToolResultEvent) {
                    yield 'data: '.json_encode(['type' => 'page_reload'])."\n\n";
                }
            }

            $usage = StreamEnd::combineUsage($events);

            AiLlmMessage::create([
                'ai_conversation_id' => $conversation->id,
                'direction' => 'response',
                'turn_number' => (string) $turnNumber,
                'request_data' => $requestPayload,
                'response_data' => [
                    'events' => array_map(static fn (StreamEvent $event): array => $event->toArray(), $events),
                    'stop_reason' => $translator->stopReason(),
                    'input_tokens' => $usage->promptTokens ?: null,
                    'output_tokens' => $usage->completionTokens ?: null,
                    'model' => $system->model,
                    'tool_calls' => $toolCalls,
                ],
                'duration_ms' => (int) ((microtime(true) - $startTime) * 1000),
                'created_at' => now(),
            ]);

            foreach ($translator->finish() as $browserEvent) {
                yield 'data: '.json_encode($browserEvent)."\n\n";
            }

            $fullResponse = implode('', $textBlocks);
            $inputTokens = $translator->inputTokens() ?: null;
            $outputTokens = $translator->outputTokens() ?: null;

            if ($fullResponse !== '') {
                AiConversationMessage::create([
                    'ai_conversation_id' => $conversation->id,
                    'role' => 'assistant',
                    'content' => $fullResponse,
                    'metadata' => [
                        'input_tokens' => $inputTokens,
                        'output_tokens' => $outputTokens,
                        'model' => $system->model,
                    ],
                ]);
            }

            $pricing = $this->conversationUsageService->pricingSnapshotForSystem($system, $system->model);

            AiInteractionLog::create([
                'ai_system_id' => $system->id,
                'ai_conversation_id' => $conversation->id,
                'user_id' => $conversation->user_id,
                'feature' => self::FEATURE,
                'input_tokens' => $inputTokens,
                'output_tokens' => $outputTokens,
                'model' => $system->model,
                'input_token_price_snapshot' => $pricing['input_token_price_snapshot'],
                'output_token_price_snapshot' => $pricing['output_token_price_snapshot'],
                'duration_ms' => (int) ((microtime(true) - $startTime) * 1000),
                'status' => AiInteractionStatus::Success,
            ]);

            $this->conversationUsageService->syncConversation($conversation->fresh());
            $roleIntel->update(['status' => RoleIntelStatus::Ready]);

            yield 'data: '.json_encode(['type' => 'page_reload'])."\n\n";
            yield "data: [DONE]\n\n";
        } catch (Throwable $e) {
            Log::error('company-intel: turn failed', [
                'role_intel_id' => $roleIntel->id,
                'conversation_id' => $conversation->id,
                'exception' => $e::class,
                'message' => $e->getMessage(),
                'provider_response' => $this->providerErrorBody($e),
            ]);

            $this->markFailed($roleIntel, $this->failureReason($e));

            if ($system !== null) {
                AiInteractionLog::create([
                    'ai_system_id' => $system->id,
                    'ai_conversation_id' => $conversation->id,
                    'user_id' => $conversation->user_id,
                    'feature' => self::FEATURE,
                    'model' => $system->model,
                    'duration_ms' => (int) ((microtime(true) - $startTime) * 1000),
                    'status' => AiInteractionStatus::Error,
                    'error_message' => trim($e->getMessage()."\n".($this->providerErrorBody($e) ?? '')),
                ]);
            }

            yield 'data: '.json_encode(['type' => 'error', 'message' => $this->failureReason($e)])."\n\n";
            yield "data: [DONE]\n\n";
        }
    }

    public function markFailed(RoleIntel $roleIntel, string $reason): void
    {
        $roleIntel->update([
            'status' => RoleIntelStatus::Failed,
            'failure_reason' => mb_substr($reason, 0, 1000),
        ]);
    }

    /**
     * The message shown for a failed turn. A database error is never shown
     * verbatim: it can carry SQL and values.
     */
    /**
     * The provider's error body. A streamed request's exception message
     * carries only the status, because Guzzle will not summarize a body it
     * cannot rewind, so the reason the provider gave is otherwise lost. A
     * gateway that already read the body to classify the error (Anthropic
     * checks it for credit messages) leaves the stream drained, but the
     * response keeps its decoded JSON.
     */
    private function providerErrorBody(Throwable $e): ?string
    {
        if (! $e instanceof RequestException) {
            return null;
        }

        try {
            $body = $e->response->body();

            if ($body === '') {
                $decoded = $e->response->json();
                $body = $decoded === null ? '' : (string) json_encode($decoded);
            }
        } catch (Throwable) {
            return null;
        }

        return $body === '' ? null : Str::limit($body, 2000);
    }

    private function failureReason(Throwable $e): string
    {
        if ($e instanceof QueryException) {
            return 'Research failed while saving its results.';
        }

        return $e->getMessage() !== '' ? $e->getMessage() : 'Research failed.';
    }

    /**
     * The system message becomes the agent's instructions and the last user
     * message the prompt; everything between is history.
     *
     * @return array{0: string, 1: array<int, UserMessage|AssistantMessage>, 2: string}
     */
    private function messagesFor(AiConversation $conversation): array
    {
        $messages = $conversation->messages()->orderBy('created_at')->orderBy('id')->get();
        $instructions = (string) ($messages->firstWhere('role', 'system')?->content ?? self::instructions(null));
        $dialogue = $messages->where('role', '!=', 'system')->values();

        $lastUserIndex = $dialogue->keys()->reverse()->first(fn (int $index): bool => $dialogue[$index]->role === 'user');
        $history = [];
        $prompt = '';

        foreach ($dialogue as $index => $message) {
            $content = (string) $message->content;

            if ($index === $lastUserIndex) {
                $prompt = $content;

                continue;
            }

            if ($message->role === 'assistant') {
                if (trim($content) !== '') {
                    $history[] = new AssistantMessage($content);
                }

                continue;
            }

            $history[] = new UserMessage($content);
        }

        return [$instructions, $history, $prompt];
    }

    private function nextTurnNumber(AiConversation $conversation): int
    {
        $max = AiLlmMessage::query()->where('ai_conversation_id', $conversation->id)->max('turn_number');

        return is_numeric($max) ? (int) $max + 1 : 1;
    }

    private function setAutoStartPending(RoleIntel $roleIntel, bool $pending, ?AiConversation $conversation = null): void
    {
        $conversation ??= AiConversation::query()->find($roleIntel->ai_conversation_id);

        if ($conversation === null) {
            return;
        }

        $context = $conversation->context ?? [];

        if (($context['auto_start_pending'] ?? false) === $pending) {
            return;
        }

        $context['auto_start_pending'] = $pending;
        $conversation->update(['context' => $context]);
    }

    private function conversationTitle(Application $application): string
    {
        $position = $application->knownPosition();
        $company = $application->knownCompanyName();

        return match (true) {
            $position !== null && $company !== null => "Intel: {$position} at {$company}",
            $company !== null => "Intel: {$company}",
            $position !== null => "Intel: {$position}",
            default => 'Intel',
        };
    }

    /**
     * The first user message: the opening line, then the job.
     */
    public static function initialMessage(Application $application): string
    {
        $message = self::INITIAL_MESSAGE."\n\n";

        if (($position = $application->knownPosition()) !== null) {
            $message .= "Job Title: {$position}\n\n";
        }

        if (($company = $application->knownCompanyName()) !== null) {
            $message .= "Company Name: {$company}\n\n";
        }

        if ($application->location) {
            $message .= "Job Location: {$application->location}\n\n";
        }

        return $message."Job Description:\n\n{$application->job_description}";
    }

    /**
     * The starter content of the Company Intel prompt, and the fallback when
     * the prompt row is missing.
     */
    public static function buildCompanyIntelPrompt(): string
    {
        return <<<'PROMPT'
        You are a research assistant helping a job seeker prepare after applying for a job.

        Research two things:

        1. The hiring company: what it does, its size, revenue, headquarters, ownership, culture, values, leadership, products, recent news, and anything a candidate should know before interviewing there.
        2. The role: what the job involves day to day, the skills it really asks for, the team and who it reports to, likely compensation, how the company interviews, and any concerns worth raising.

        Prefer the company's own site and reputable sources. Say when something is uncertain or could not be found rather than guessing. Be concise and practical: the reader wants to walk into an interview prepared.
        PROMPT;
    }

    /**
     * The site's own instructions for recording findings, appended after the
     * editable prompt so that editing the prompt cannot break recording.
     */
    public static function recordingInstructions(): string
    {
        return <<<'INSTRUCTIONS'
        ## Recording your findings

        Your findings are kept as records, not just as this conversation, so record everything you learn with these tools:

        1. Call `get-intel-context` first. It gives you the job, anything already recorded, and any companies the user has rejected for this role.
        2. Call `save-company-profile` with the company's name and its own website, plus whatever metadata you find. If the company is already attached, call it again to fill in or correct fields.
        3. Call `add-company-insight` once for each separate finding about the company (culture, values, products, leadership, news, interview process, compensation, risks and so on), citing the source URL when you have one. Do not repeat insights that are already recorded.
        4. Call `save-role-intel` with what you learned about the role.

        Never attach a company the user has rejected for this role. When you are done, reply with a short summary of what you found and what you could not find.
        INSTRUCTIONS;
    }

    /**
     * The intel session's instructions: the Company Intel prompt, then the
     * recording instructions.
     */
    public static function instructions(?string $promptContent): string
    {
        $prompt = trim((string) $promptContent) !== '' ? $promptContent : self::buildCompanyIntelPrompt();

        return trim($prompt)."\n\n---\n\n".self::recordingInstructions();
    }
}
