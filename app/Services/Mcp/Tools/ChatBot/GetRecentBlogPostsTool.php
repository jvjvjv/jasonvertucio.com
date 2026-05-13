<?php

namespace App\Services\Mcp\Tools\ChatBot;

use App\Contracts\Mcp\AiToolHandlerContract;
use Canvas\Models\Post;

class GetRecentBlogPostsTool implements AiToolHandlerContract
{
    public function name(): string
    {
        return 'get_recent_blog_posts';
    }

    public function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'limit' => [
                    'type' => 'integer',
                    'minimum' => 1,
                    'maximum' => 20,
                    'description' => 'Number of posts to return (default 10)',
                ],
            ],
            'required' => [],
        ];
    }

    public function handle(array $input): array
    {
        $limit = min((int) ($input['limit'] ?? 10), 20);

        $posts = Post::published()
            ->with('topic')
            ->latest('published_at')
            ->limit($limit)
            ->get(['id', 'title', 'summary', 'slug', 'published_at', 'topic_id']);

        return [
            'posts' => $posts->map(static fn (Post $post): array => [
                'title' => $post->title,
                'summary' => $post->summary,
                'slug' => $post->slug,
                'url' => '/blog/' . $post->slug,
                'published_at' => $post->published_at?->toDateString(),
                'topic' => $post->topic?->name,
            ])->values()->toArray(),
        ];
    }
}
