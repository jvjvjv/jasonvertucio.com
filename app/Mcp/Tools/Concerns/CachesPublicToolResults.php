<?php

namespace App\Mcp\Tools\Concerns;

use App\Services\Mcp\PublicMcpCache;
use Illuminate\Support\Facades\Cache;
use Jvjvjv\CodeTalker\Support\ToolContext;

/**
 * Caches a public MCP tool's structured payload.
 *
 * The endpoint's data changes on the order of weeks, so repeated calls cost a
 * cache read rather than a query. That is what lets the rate limits be sized
 * for abuse rather than for load.
 *
 * Entries are invalidated by the events that actually change the data, through
 * {@see PublicMcpCache}'s generation stamp; the TTL is only a backstop.
 *
 * **The disclosure level is part of the key.** An anonymous caller and a token
 * holder receive different projections of identical arguments, so a key that
 * ignored identity would serve one the other's data.
 */
trait CachesPublicToolResults
{
    /**
     * @param  array<string, mixed>  $arguments
     * @param  callable(): (array<string, mixed>|null)  $payload
     * @return array<string, mixed>|null
     */
    protected function remember(string $group, array $arguments, callable $payload): ?array
    {
        $key = $this->resultCacheKey($group, $arguments);

        $cached = Cache::get($key);

        if (is_array($cached) && array_key_exists('value', $cached)) {
            return $cached['value'];
        }

        $value = $payload();

        Cache::put($key, ['value' => $value], now()->addSeconds((int) config('mcp-server.cache_ttl')));

        return $value;
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    protected function resultCacheKey(string $group, array $arguments): string
    {
        ksort($arguments);

        return 'mcp:tool-result:'.$group.':'.sha1((string) json_encode([
            'generation' => PublicMcpCache::generation($group),
            'arguments' => $arguments,
            'disclosure' => $this->disclosureLevel(),
        ]));
    }

    /**
     * What this caller is entitled to see, as a cache-key component.
     *
     * Keyed on the resolved user rather than a coarse anonymous/authenticated
     * flag, because two token holders may hold different permissions and
     * therefore receive different projections.
     */
    protected function disclosureLevel(): string
    {
        $context = property_exists($this, 'context') && $this->context instanceof ToolContext
            ? $this->context
            : app(ToolContext::class);

        return $context->userId === null ? 'anonymous' : 'user:'.$context->userId;
    }
}
