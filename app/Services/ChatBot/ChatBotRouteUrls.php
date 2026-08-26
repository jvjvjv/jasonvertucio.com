<?php

namespace App\Services\ChatBot;

use App\Models\AiChatBot;

/**
 * Resolves a bot's endpoint URLs against whichever of the two identical route
 * groups — root-level or `/chat`-prefixed — that bot is served from.
 *
 * Host-owned reimplementation of the package's removed (0.11.0)
 * `Jvjvjv\CodeTalker\Services\ChatBot\ChatBotRouteUrls`.
 */
class ChatBotRouteUrls
{
    public function for(AiChatBot $aiChatBot, string $action): string
    {
        $prefix = $aiChatBot->usesRootAccessPath() ? 'chat-bots.root.' : 'chat-bots.chat.';

        return route($prefix.$action, $aiChatBot);
    }

    /**
     * The hash-link base for this bot, e.g. `/chat/my-bot/`.
     */
    public function chatUrlBase(AiChatBot $aiChatBot): string
    {
        return '/chat/'.$aiChatBot->slug.'/';
    }

    public function chatUrlFor(AiChatBot $aiChatBot, ?string $chatHash): ?string
    {
        return $chatHash ? $this->chatUrlBase($aiChatBot).$chatHash : null;
    }
}
