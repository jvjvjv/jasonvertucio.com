<?php

namespace App\Listeners;

use App\Services\Mcp\PublicMcpCache;

/**
 * Drops the public MCP endpoint's cached blog listing when a post changes.
 *
 * Registered on the same Canvas events as {@see FlushBlogFeedCache}, so the
 * endpoint and the site's own feed go stale together rather than drifting.
 */
class FlushPublicMcpBlogCache
{
    public function handle(object $event): void
    {
        PublicMcpCache::flush(PublicMcpCache::GROUP_BLOG_POSTS);
    }
}
