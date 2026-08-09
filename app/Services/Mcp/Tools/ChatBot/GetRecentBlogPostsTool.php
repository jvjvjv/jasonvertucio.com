<?php

namespace App\Services\Mcp\Tools\ChatBot;

use Canvas\Models\Post;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('get-recent-blog-posts')]
#[Description('Load recent blog posts with titles, summaries, and URLs. Supports search by keyword in title, summary, or body.')]
class GetRecentBlogPostsTool extends Tool
{
    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'limit' => $schema->integer()
                ->min(1)
                ->max(20)
                ->description('Number of posts to return (default 10)'),
            'search' => $schema->string()
                ->description('Optional search term to filter posts by title, summary, or body content.'),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $limit = min((int) ($request->get('limit') ?? 10), 20);
        $search = trim((string) ($request->get('search') ?? ''));

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
                        $q->whereRaw('MATCH(body) AGAINST(? IN NATURAL LANGUAGE MODE)', [$search]);
                    });
                }
            })
            ->get(['id', 'title', 'summary', 'slug', 'published_at']);

        return Response::structured([
            'posts' => $posts->map(static fn (Post $post): array => [
                'title' => $post->title,
                'summary' => $post->summary,
                'slug' => $post->slug,
                'url' => '/blog/'.$post->slug,
                'published_at' => $post->published_at?->toDateString(),
                'topic' => $post->topic->first()?->name,
            ])->values()->toArray(),
        ]);
    }
}
