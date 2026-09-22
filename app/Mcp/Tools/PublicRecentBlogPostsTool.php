<?php

namespace App\Mcp\Tools;

use App\Mcp\Tools\Concerns\CachesPublicToolResults;
use App\Services\Mcp\Tools\ChatBot\GetRecentBlogPostsTool;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

/**
 * The blog listing as served over the public MCP endpoint, with its results
 * cached. Attributes are re-declared because PHP does not inherit them.
 */
#[Name('get-recent-blog-posts')]
#[Description('Load recent posts from Jason Vertucio\'s blog with titles, summaries, topics and URLs. Supports search by keyword in title, summary, or body.')]
class PublicRecentBlogPostsTool extends GetRecentBlogPostsTool
{
    use CachesPublicToolResults;

    /**
     * @return array<string, mixed>
     */
    protected function postsPayload(Request $request): array
    {
        return $this->remember('blog-posts', [
            'limit' => $request->get('limit'),
            'search' => $request->get('search'),
        ], fn (): array => parent::postsPayload($request)) ?? ['posts' => []];
    }
}
