<?php

namespace App\Mcp\Tools;

use App\Models\User;
use App\Services\Blog\BlogPostWriter;
use Canvas\Models\CanvasUser;
use Canvas\Models\Post;
use Canvas\Support\PostSnapshot;
use Canvas\Support\PublishedAt;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Jvjvjv\CodeTalker\Support\ToolContext;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

/**
 * Creates or updates a blog post over the public MCP endpoint — the one tool
 * on that roster that writes.
 *
 * Hidden from every caller whose token does not resolve to a holder of
 * `manage-blog` with a Canvas account, and re-checked in the handler for a
 * client that calls it by name. The user is checked with hasPermissionTo() rather than can(), as in
 * {@see \App\Services\Mcp\Tools\CompanyIntel\CompanyIntelTool}.
 *
 * Lives under app/Mcp/ rather than app/Services/Mcp/Tools/, which CodeTalker
 * discovers for the chat loop: a chat bot must never be handed this tool. It
 * does not use CachesPublicToolResults — a write answered from cache would
 * never happen.
 *
 * Nothing is published unless asked for. A new post is a draft by default,
 * and an update leaves the publication state alone. A live post is never
 * taken offline here: unpublishing and rescheduling stay in the editor.
 */
#[Name(self::NAME)]
#[Description('Create a blog post on Jason Vertucio\'s blog, or update one by id. New posts are drafts unless publish is "now" or "schedule". Changes to a post that is already published go live immediately. Requires a token whose owner may manage the blog.')]
class SaveBlogPostTool extends Tool
{
    public const NAME = 'save-blog-post';

    private const PUBLISH_OPTIONS = ['draft', 'now', 'schedule'];

    public function __construct(
        protected ToolContext $context,
        protected BlogPostWriter $writer,
    ) {}

    public function shouldRegister(): bool
    {
        return $this->authorizedUser() !== null;
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()
                ->description('ID of an existing post to update. Omit to create a new post.'),
            'title' => $schema->string()
                ->description('Post title. Required to publish or schedule.'),
            'body' => $schema->string()
                ->description('Post body as HTML.'),
            'summary' => $schema->string()
                ->description('Short summary shown in post listings.'),
            'slug' => $schema->string()
                ->description('URL slug (letters, digits, dashes, underscores). Omit to derive it from the title, as the blog editor does.'),
            'topic' => $schema->string()
                ->description('Slug of an existing topic; an empty string removes the topic. Topics are never created here, and an unknown slug is ignored and reported.'),
            'tags' => $schema->array()
                ->items($schema->string())
                ->description('Slugs of existing tags, replacing the post\'s tags. Tags are never created here, and unknown slugs are ignored and reported.'),
            'publish' => $schema->string()
                ->enum(self::PUBLISH_OPTIONS)
                ->description('"draft" keeps the post unpublished (the default for a new post), "now" publishes it immediately, "schedule" publishes it at published_at. Omit on an update to leave the publication state as it is. A published post cannot be unpublished or rescheduled here.'),
            'published_at' => $schema->string()
                ->description('ISO-8601 time with a timezone offset or Z, for example 2026-12-01T09:00:00-08:00. Required when publish is "schedule", and must be in the future.'),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $user = $this->authorizedUser();

        if ($user === null) {
            return Response::error('This tool requires blog-management access.');
        }

        $input = array_filter($request->validate([
            'id' => ['nullable', 'string'],
            'title' => ['nullable', 'string', 'max:191'],
            'body' => ['nullable', 'string'],
            'summary' => ['nullable', 'string'],
            'slug' => ['nullable', 'string', 'alpha_dash', 'max:191'],
            'topic' => ['nullable', 'string'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'distinct'],
            'publish' => ['nullable', Rule::in(self::PUBLISH_OPTIONS)],
            'published_at' => [
                'nullable',
                'string',
                'prohibited_unless:publish,schedule',
                'required_if:publish,schedule',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! is_string($value) || ! PublishedAt::hasTimezone($value)) {
                        $fail('The published_at time must be ISO-8601 with a timezone offset or Z.');

                        return;
                    }

                    try {
                        $at = PublishedAt::parse($value);
                    } catch (\Throwable) {
                        $fail('The published_at time is not a valid date.');

                        return;
                    }

                    if ($at->lessThanOrEqualTo(now())) {
                        $fail('The published_at time must be in the future.');
                    }
                },
            ],
        ]), fn (mixed $value): bool => $value !== null);

        $post = null;

        if (isset($input['id'])) {
            $post = Post::query()->find($input['id']);

            // A post the caller may not update is reported exactly like a
            // missing one, so the reply never confirms that it exists.
            if ($post === null || Gate::forUser($user)->denies('update', $post)) {
                return Response::error('Post not found.');
            }
        }

        $post ??= new Post(['id' => (string) Str::uuid()]);
        $wasLive = $post->exists && $post->published;
        $publish = $input['publish'] ?? ($post->exists ? null : 'draft');

        if ($wasLive && in_array($publish, ['draft', 'schedule'], true)) {
            return Response::error('This post is published. Unpublishing or rescheduling it is not supported here; use the blog editor.');
        }

        $attributes = Arr::only($input, ['title', 'slug', 'summary', 'body']);

        if ($publish === 'draft') {
            $attributes['published_at'] = null;
        } elseif ($publish === 'now' && ! $wasLive) {
            $attributes['published_at'] = now()->format('Y-m-d H:i:s');
        } elseif ($publish === 'schedule') {
            $attributes['published_at'] = PublishedAt::toStorageString(PublishedAt::parse($input['published_at']));
        }

        $publishedAt = array_key_exists('published_at', $attributes) ? $attributes['published_at'] : $post->published_at;
        $title = array_key_exists('title', $attributes) ? $attributes['title'] : $post->title;

        if ($publishedAt !== null && blank($title)) {
            return Response::error('A title is required to publish or schedule a post.');
        }

        $saved = $this->writer->save($post, $attributes, Arr::only($input, ['topic', 'tags']), $user);
        $post = $saved['post'];

        return Response::structured([
            'id' => (string) $post->getKey(),
            'slug' => $post->slug,
            'status' => match (PostSnapshot::from($post)->visibility()) {
                'live' => 'published',
                'scheduled' => 'scheduled',
                default => 'draft',
            },
            'published_at' => $post->published_at?->toIso8601String(),
            'url' => route('post', $post->slug),
            'ignored' => $saved['ignored'],
        ]);
    }

    private function authorizedUser(): ?User
    {
        if ($this->context->userId === null) {
            return null;
        }

        $user = User::find($this->context->userId);

        // The editor's own gate too: Canvas's Authorize middleware refuses
        // anyone without a Canvas account, whatever their app permissions.
        return $user !== null
            && $user->hasPermissionTo('manage-blog')
            && CanvasUser::query()->whereKey($user->getKey())->exists()
                ? $user
                : null;
    }
}
