<?php

namespace App\Services\ChatBot;

use App\Models\AiChatBot;
use App\Models\AiConversation;
use Illuminate\Http\Request;
use Jvjvjv\CodeTalker\Services\ChatBot\ChatBotPresenter;

/**
 * The `ai/ChatBot` page props: the package's baseline transcript/history/URL
 * fields, plus the two the host UI needs and the package does not know about:
 * `bot.allowed_roles` for BotAccessCard, and `previousHref` for the back link.
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
                'allowed_roles' => $aiChatBot->allowed_roles ?? [],
            ],
            'messages' => $this->presenter->transcript($conversation),
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
