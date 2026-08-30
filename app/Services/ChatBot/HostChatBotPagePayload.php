<?php

namespace App\Services\ChatBot;

use App\Models\AiChatBot;
use App\Models\AiConversation;
use Illuminate\Http\Request;
use Jvjvjv\CodeTalker\Services\ChatBot\ChatBotPresenter;

/**
 * The `ai/ChatBot` page props: the package's baseline transcript/history/URL
 * fields, plus the two the host UI needs and the package does not know about:
 * `bot.required_permission` for BotAccessCard, and `previousHref` for the back link.
 *
 * Host-owned reimplementation of the package's removed (0.11.0)
 * `Jvjvjv\CodeTalker\Services\ChatBot\ChatBotPagePayload`, built on the
 * package's still-available `ChatBotPresenter` for the transcript/cost queries.
 */
class HostChatBotPagePayload
{
    public function __construct(
        private ChatBotRouteUrls $urls,
        private ChatBotPresenter $presenter,
        private Request $request,
    ) {
    }

    /**
     * The parameter names are part of the contract: the host controller passes
     * `showIdentityForm` and `includeChatHash` as named arguments.
     *
     * @param  array<int, array<string, mixed>>  $history
     * @return array<string, mixed>
     */
    public function build(
        AiChatBot $aiChatBot,
        ?AiConversation $conversation,
        array $history,
        bool $showIdentityForm,
        bool $includeChatHash = false,
    ): array {
        $payload = [
            'bot' => [
                'name' => $aiChatBot->name,
                'description' => $aiChatBot->description,
                'require_visitor_identity' => $aiChatBot->require_visitor_identity,
                'total_cost_usd' => $this->presenter->totalCostUsd($aiChatBot),
                'required_permission' => $aiChatBot->required_permission,
            ],
            'messages' => $this->transcriptWithToolPanels($conversation),
            'history' => $history,
            'messageUrl' => $this->urls->for($aiChatBot, 'message'),
            'resetUrl' => $this->urls->for($aiChatBot, 'reset'),
            'switchUrl' => $this->urls->for($aiChatBot, 'switch'),
            'statusUrl' => $this->urls->for($aiChatBot, 'status'),
            'warmupUrl' => $this->urls->for($aiChatBot, 'warmup'),
            'chatUrl' => $this->urls->chatUrlFor($aiChatBot, $conversation?->chat_hash),
            'chatUrlBase' => $this->urls->chatUrlBase($aiChatBot),
            'showIdentityForm' => $showIdentityForm,
            'previousHref' => $this->previousHref(),
        ];

        if ($includeChatHash) {
            $payload['chatHash'] = $conversation?->chat_hash;
        }

        return $payload;
    }

    /**
     * The package transcript, enriched with each message's tool activity.
     *
     * `ChatBotPresenter::transcript()` doesn't expose `tool_calls`/`tool_results`
     * (host-only concern, not part of the package's page-payload contract), so
     * this re-queries just those two columns with the exact same filter/order
     * the presenter uses and zips them in by position — safe because both
     * queries run back-to-back within one request against the same rows.
     *
     * @return array<int, array<string, mixed>>
     */
    private function transcriptWithToolPanels(?AiConversation $conversation): array
    {
        $transcript = $this->presenter->transcript($conversation);

        if ($conversation === null || $transcript === []) {
            return $transcript;
        }

        $toolColumns = $conversation->messages()
            ->where('role', '!=', 'system')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get(['tool_calls', 'tool_results'])
            ->values();

        $includePayloads = ! app()->environment('production');

        foreach ($transcript as $index => &$message) {
            $message['tool_panels'] = $this->toolPanelsFor(
                $toolColumns[$index]->tool_calls ?? null,
                $toolColumns[$index]->tool_results ?? null,
                $includePayloads,
            );
        }

        return $transcript;
    }

    /**
     * Pair each tool call with its result (matched by id — laravel/ai's
     * TextGenerationLoop::executeToolCalls() constructs every ToolResult with
     * the calling ToolCall's id) into the host's `ToolPanel` shape. A call with
     * no matching result yet (e.g. a turn cut off mid-tool-use) is still
     * included, with no output.
     *
     * @param  array<int, array<string, mixed>>|null  $toolCalls
     * @param  array<int, array<string, mixed>>|null  $toolResults
     * @return array<int, array<string, mixed>>|null
     */
    private function toolPanelsFor(?array $toolCalls, ?array $toolResults, bool $includePayloads): ?array
    {
        if (blank($toolCalls)) {
            return null;
        }

        $resultsById = collect($toolResults ?? [])->keyBy('id');

        return collect($toolCalls)
            ->map(function (array $call) use ($resultsById, $includePayloads): array {
                // `pretext` is required by the frontend's `ToolPanel` shape
                // (always '' in the live tool_use_progress frame too — see
                // ConversationTurnRunner.php) — must be present even though
                // it's never actually populated with text.
                $panel = ['pretext' => '', 'tools' => [$call['name']]];

                if (! $includePayloads) {
                    return $panel;
                }

                $panel['input'] = $call['arguments'];

                $result = $resultsById->get($call['id']);

                if ($result !== null) {
                    $panel['output'] = $result['result'];
                }

                return $panel;
            })
            ->all();
    }

    /**
     * Where the back link points: wherever the visitor came from, as long as
     * that was somewhere on this site and not this very page.
     */
    private function previousHref(): ?string
    {
        $referer = $this->request->headers->get('referer');

        if ($referer === null || $referer === $this->request->fullUrl()) {
            return route('chat-bots.index');
        }

        return parse_url($referer, PHP_URL_HOST) === $this->request->getHost()
            ? $referer
            : route('chat-bots.index');
    }
}
