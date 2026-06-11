<?php

namespace App\Services\Mcp\Tools\ChatBot;

use Illuminate\Database\Eloquent\Builder;
use Jvjvjv\CodeTalker\Contracts\Mcp\AiToolHandlerContract;
use Canvas\Models\Post;

class GetRecentBlogPostsTool implements AiToolHandlerContract
{
    public function name(): string
    {
        return 'get_recent_blog_posts';
    }

    public function description(): string
    {
        return 'Load recent blog posts with titles, summaries, and URLs. Supports search by keyword in title, summary, or body.';
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
                'search' => [
                    'type' => 'string',
                    'description' => 'Optional search term to filter posts by title, summary, or body content.',
                ],
            ],
            'required' => [],
        ];
    }

    public function handle(array $input): array
    {
        $limit = min((int) ($input['limit'] ?? 10), 20);
        $search = trim((string) ($input['search'] ?? ''));

        $posts = Post::published()
            ->with('topic')
            ->latest('published_at')
            ->limit($limit)
            ->where(function (Builder $query) use ($search) {
                if ($search !== '') {
                    // Use LIKE for title and summary (works with TEXT columns < 255 bytes)
                    $query->where(function ($q) use ($search) {
                        $q->from('canvas_posts');
                        $q->where('title', 'like', "%{$search}%")
                            ->orWhere('summary', 'like', "%{$search}%");
                    });

                    // Use full-text search for body (better performance on large text fields)
                    $query->orWhere(function ($q) use ($search) {
                        $q->from('canvas_posts');
                        $q->whereRaw("MATCH(body) AGAINST(? IN NATURAL LANGUAGE MODE)", [$search]);
                    });
                }
            })
            ->get(['id', 'title', 'summary', 'slug', 'published_at']);

        return [
            'posts' => $posts->map(static fn (Post $post): array => [
                'title' => $post->title,
                'summary' => $post->summary,
                'slug' => $post->slug,
                'url' => '/blog/' . $post->slug,
                'published_at' => $post->published_at?->toDateString(),
                'topic' => $post->topic->first()?->name,
            ])->values()->toArray(),
        ];
    }
}
