<?php

namespace App\Services\ChatBot;

use Illuminate\Http\Request;
use Jvjvjv\CodeTalker\Models\AiChatBot;
use Jvjvjv\CodeTalker\Models\AiConversation;
use Jvjvjv\CodeTalker\Services\ChatBot\ChatBotPagePayload;
use Jvjvjv\CodeTalker\Services\ChatBot\ChatBotRouteUrls;

/**
 * The `ai/ChatBot` props plus the two the host UI needs and the package does
 * not know about: `bot.allowed_roles` for BotAccessCard, and `previousHref`
 * for the back link.
 *
 * This one delegates to the parent rather than replacing it, so it needs no
 * private copy of `$urls` — only its own request for the referer.
 */
class HostChatBotPagePayload extends ChatBotPagePayload
{
    public function __construct(
        ChatBotRouteUrls $urls,
        private Request $request,
    ) {
        parent::__construct($urls);
    }

    /**
     * The parameter names are part of the contract: the package controller
     * passes `showIdentityForm` and `includeChatHash` as named arguments.
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
        $payload = parent::build(
            $aiChatBot,
            $conversation,
            $history,
            showIdentityForm: $showIdentityForm,
            includeChatHash: $includeChatHash,
        );

        $payload['bot']['allowed_roles'] = $aiChatBot->allowed_roles ?? [];
        $payload['previousHref'] = $this->previousHref();

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
