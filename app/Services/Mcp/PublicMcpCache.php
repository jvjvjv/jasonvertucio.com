<?php

namespace App\Services\Mcp;

use Illuminate\Support\Facades\Cache;

/**
 * Invalidation for the public MCP endpoint's cached tool results.
 *
 * The cache driver here is `file`, which supports neither tags nor prefix
 * scans, so entries cannot be found and deleted after the fact. Instead each
 * group carries a **generation stamp** that forms part of every key it writes:
 * bumping the stamp orphans the old entries, which then expire on their own
 * TTL. This works identically on any driver.
 */
class PublicMcpCache
{
    public const GROUP_RESUME = 'resume';

    public const GROUP_BLOG_POSTS = 'blog-posts';

    public const GROUP_SITE_INFO = 'site-info';

    /**
     * The current generation for a group, created on first use.
     */
    public static function generation(string $group): string
    {
        return (string) Cache::rememberForever(self::stampKey($group), static fn (): string => '1');
    }

    /**
     * Orphan every cached result in a group.
     */
    public static function flush(string $group): void
    {
        Cache::forever(self::stampKey($group), (string) (((int) self::generation($group)) + 1));
    }

    private static function stampKey(string $group): string
    {
        return 'mcp:cache-generation:'.$group;
    }
}
