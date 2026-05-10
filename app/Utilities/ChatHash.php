<?php

namespace App\Utilities;

class ChatHash
{
    /**
     * Generate a SHA1 hash for a conversation that can be used as a URL-safe identifier.
     */
    public static function generate(string|int $conversationId, string $conversationCreatedAt, string $featureKey): string
    {
        return sha1("{$conversationId}:{$conversationCreatedAt}:{$featureKey}");
    }
}
