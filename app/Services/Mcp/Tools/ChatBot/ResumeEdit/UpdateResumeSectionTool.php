<?php

namespace App\Services\Mcp\Tools\ChatBot\ResumeEdit;

use App\Models\ResumeVersion;
use App\Services\ResumeEditCandidateService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\Log;
use Jvjvjv\CodeTalker\Models\AiConversationMessage;
use Jvjvjv\CodeTalker\Support\ToolContext;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('update-resume-section')]
#[Description(
    'Propose an edit to one section of the main resume (personal, skills, experience, education, or projects). '
    .'This never changes the live resume directly — it creates or updates a draft revision that a human must '
    .'review and approve before it goes live. `data` is a JSON-encoded value matching the shape of that section '
    .'as returned by get-resume-data (e.g. for "personal": {"name","title","email","phone","linkedin","url","summary"}; '
    .'for "skills": {"top":[{"title","list":[...]}],"other":[...]}; for "experience"/"education"/"projects": the full '
    .'replacement array for that section).'
)]
class UpdateResumeSectionTool extends AuthorizedResumeEditTool
{
    private const SECTIONS = ['personal', 'skills', 'experience', 'education', 'projects'];

    public function __construct(
        ToolContext $context,
        private ResumeEditCandidateService $candidateService,
    ) {
        parent::__construct($context);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'section' => $schema->string()
                ->enum(self::SECTIONS)
                ->description('Which resume section to replace.')
                ->required(),
            'data' => $schema->string()
                ->description('JSON-encoded replacement value for the section.')
                ->required(),
            'summary' => $schema->string()
                ->description('A short human-readable summary of what changed, for the conversation record.'),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        Log::info('chat-bot.update-resume-section: request received', [
            'conversation_id' => $this->context->conversation?->id,
            'user_id' => $this->context->userId,
        ]);

        if ($response = $this->guard()) {
            Log::warning('chat-bot.update-resume-section: access denied', [
                'conversation_id' => $this->context->conversation?->id,
                'user_id' => $this->context->userId,
            ]);

            return $response;
        }

        $section = (string) ($request->get('section') ?? '');

        if (! in_array($section, self::SECTIONS, true)) {
            return Response::error('Invalid section. Must be one of: '.implode(', ', self::SECTIONS));
        }

        $rawData = (string) ($request->get('data') ?? '');
        $decoded = json_decode($rawData, true);

        if (! is_array($decoded)) {
            // Some models (especially smaller local ones) lose the JSON grammar
            // partway through a long string argument and leak their own
            // tool-call formatting after the closing brace/bracket. Recover by
            // decoding just the first balanced JSON value and discarding the
            // trailing garbage, instead of failing the whole edit outright.
            $decoded = $this->decodeLeadingJsonValue($rawData);

            if (is_array($decoded)) {
                Log::warning('chat-bot.update-resume-section: recovered JSON with trailing garbage discarded', [
                    'conversation_id' => $this->context->conversation?->id,
                    'raw_length' => strlen($rawData),
                ]);
            }
        }

        if (! is_array($decoded)) {
            return Response::error('data must be valid JSON matching the section shape.');
        }

        $base = ResumeVersion::current()->first();

        if ($base === null) {
            return Response::error('No live resume version found to branch a draft from.');
        }

        try {
            $candidate = $this->candidateService->resolveOrCreateCandidateForEdit($base, $this->context->conversation);
            $candidate = $this->candidateService->applySectionEdit($candidate, $section, $decoded);

            $this->recordEditMessage($candidate->ai_conversation_id, $section, (string) ($request->get('summary') ?? ''));

            Log::info('chat-bot.update-resume-section: edit applied', [
                'conversation_id' => $this->context->conversation?->id,
                'candidate_id' => $candidate->id,
                'revision_number' => $candidate->revision_number,
                'section' => $section,
            ]);

            return Response::structured([
                'success' => true,
                'candidate_id' => $candidate->id,
                'revision_number' => $candidate->revision_number,
                'section' => $section,
                '_page_reload' => true,
            ]);
        } catch (\Throwable $throwable) {
            Log::error('chat-bot.update-resume-section: edit failed', [
                'conversation_id' => $this->context->conversation?->id,
                'user_id' => $this->context->userId,
                'exception' => $throwable::class,
                'message' => $throwable->getMessage(),
            ]);

            return Response::error('Failed to save the resume edit. Check the application log for details.');
        }
    }

    /**
     * Decode the first balanced JSON object/array found at the start of the
     * string, ignoring any trailing content after it closes. Returns null if
     * no balanced value is found or it doesn't decode to an array.
     */
    private function decodeLeadingJsonValue(string $value): mixed
    {
        $depth = 0;
        $start = null;
        $inString = false;
        $escaped = false;

        for ($i = 0, $length = strlen($value); $i < $length; $i++) {
            $char = $value[$i];

            if ($start === null) {
                if ($char === '{' || $char === '[') {
                    $start = $i;
                    $depth = 1;
                }

                continue;
            }

            if ($escaped) {
                $escaped = false;

                continue;
            }

            if ($char === '\\') {
                $escaped = true;

                continue;
            }

            if ($char === '"') {
                $inString = ! $inString;

                continue;
            }

            if ($inString) {
                continue;
            }

            if ($char === '{' || $char === '[') {
                $depth++;
            } elseif ($char === '}' || $char === ']') {
                $depth--;

                if ($depth === 0) {
                    $decoded = json_decode(substr($value, $start, $i - $start + 1), true);

                    return is_array($decoded) ? $decoded : null;
                }
            }
        }

        return null;
    }

    private function recordEditMessage(?int $conversationId, string $section, string $summary): void
    {
        if ($conversationId === null) {
            return;
        }

        $description = $summary !== '' ? $summary : "Updated the resume's {$section} section.";

        AiConversationMessage::create([
            'ai_conversation_id' => $conversationId,
            'role' => 'user',
            'content' => "I edited the main resume via the resume-edit tool ({$section}): {$description}",
            'metadata' => [
                'origin' => 'ai_resume_edit',
                'section' => $section,
            ],
        ]);
    }
}
