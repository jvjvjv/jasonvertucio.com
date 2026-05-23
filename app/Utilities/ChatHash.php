<?php

namespace App\Utilities;

class ChatHash
{
    /**
     * Generate a deterministic MD5 hash for a conversation UUID that can be used
     * as a URL-safe identifier. Same UUID always produces the same hash.
     */
    public static function generate(string $conversationUuid): string
    {
        return md5($conversationUuid);
    }
}
