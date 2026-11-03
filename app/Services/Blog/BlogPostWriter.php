<?php

namespace App\Services\Blog;

use Canvas\Models\Post;
use Canvas\Models\Tag;
use Canvas\Models\Topic;
use Canvas\Support\PostLifecycleEvents;
use Canvas\Support\PostSlug;
use Canvas\Support\PostSnapshot;
use Canvas\Support\RecordPostRevision;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Saves a Canvas post the way the blog editor's deliberate save does.
 *
 * Mirrors the non-pending branch of Canvas's `PostController::store()` — the
 * path behind Publish, Schedule and Update, as opposed to an editor autosave —
 * so a post written here is indistinguishable from one saved in the browser:
 * the editor's slug rules, topic and tags resolved against existing records
 * only, the same lifecycle events (which flush the blog feed and the public
 * MCP blog cache), and the same revision checkpoint.
 *
 * Canvas keeps that logic inside its controller, so it is mirrored rather than
 * called. Re-read `PostController::store()` and the editor's slug helpers in
 * `resources/js/lib/posts/form.ts` when upgrading Canvas.
 */
class BlogPostWriter
{
    /**
     * The slug `GET /posts/create` mints before a post has a title.
     */
    private const GENERATED_SLUG = '/^post-[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

    /**
     * @param  array{title?: string|null, slug?: string|null, summary?: string|null, body?: string|null, published_at?: string|null}  $attributes  Columns to change; an absent key is left as it is. `published_at` is a storage string in the app timezone.
     * @param  array{topic?: string|null, tags?: list<string>}  $taxonomy  Slugs of existing records; an absent key leaves that association as it is.
     * @return array{post: Post, ignored: array{topics: list<string>, tags: list<string>}}
     */
    public function save(Post $post, array $attributes, array $taxonomy, Authenticatable $author): array
    {
        $post->loadMissing(['tags', 'topic']);

        $before = $post->exists ? PostSnapshot::from($post) : null;
        $hadRevisions = $post->exists && $post->revisions()->exists();
        $ignored = ['topics' => [], 'tags' => []];

        $slug = $this->slugForSave($post, $attributes, $author);

        $post->fill($attributes);
        $post->slug = $slug;
        $post->user_id ??= $author->getAuthIdentifier();
        $post->clearPending();

        if (array_key_exists('topic', $taxonomy)) {
            $topicSlug = $taxonomy['topic'];
            $topic = filled($topicSlug) ? Topic::query()->firstWhere('slug', $topicSlug) : null;

            if (filled($topicSlug) && $topic === null) {
                $ignored['topics'][] = $topicSlug;
            } else {
                $post->topic_id = $topic?->getKey();
            }
        }

        $post->save();

        if (array_key_exists('tags', $taxonomy)) {
            $tags = Tag::query()->whereIn('slug', $taxonomy['tags'])->get(['id', 'slug']);

            $ignored['tags'] = array_values(array_diff($taxonomy['tags'], $tags->pluck('slug')->all()));
            $post->tags()->sync($tags->pluck('id')->all());
        }

        $post->load(['tags', 'topic']);

        PostLifecycleEvents::dispatch($before, $post);

        $userId = $author->getAuthIdentifier();
        $userId = is_int($userId) || is_numeric($userId) ? (int) $userId : null;
        $reason = RecordPostRevision::reasonForStore($before, PostSnapshot::from($post), true, $hadRevisions);
        RecordPostRevision::fromPost($post, $userId, $reason);

        return ['post' => $post, 'ignored' => $ignored];
    }

    /**
     * The slug to store, applying the editor's rules: a supplied slug is kept,
     * otherwise the slug follows the title until it has been set by hand. Any
     * collision gets Canvas's `-2`, `-3`, … suffix.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function slugForSave(Post $post, array $attributes, Authenticatable $author): string
    {
        $current = (string) ($post->slug ?? '');
        $slug = $attributes['slug'] ?? null;

        if (! is_string($slug) || $slug === '') {
            $title = (string) ($attributes['title'] ?? $post->title ?? '');

            $slug = ! $post->exists || (array_key_exists('title', $attributes) && $this->slugFollowsTitle((string) $post->title, $current))
                ? $this->slugForTitle($title, $current !== '' ? $current : 'post-'.$post->getKey())
                : $current;
        }

        return PostSlug::forSave($slug, $post->exists ? $post : null, $post->exists ? null : $author->getAuthIdentifier());
    }

    /**
     * Port of the editor's `slugify()`.
     */
    private function slugify(string $value): string
    {
        $slug = preg_replace(['/[^a-z0-9]+/', '/^-+|-+$/'], ['-', ''], trim(mb_strtolower($value)));

        return $slug === '' ? 'post' : $slug;
    }

    /**
     * Port of the editor's `slugForTitle()`: a title with no letters or digits
     * keeps the generated placeholder.
     */
    private function slugForTitle(string $title, string $currentSlug): string
    {
        if (! preg_match('/[a-z0-9]/i', $title) && preg_match(self::GENERATED_SLUG, $currentSlug)) {
            return $currentSlug;
        }

        return $this->slugify($title);
    }

    /**
     * Port of the editor's `slugFollowsTitle()`: true until the slug has been
     * set by hand. A uniqueness suffix (`hello-world-2`) still follows.
     */
    private function slugFollowsTitle(string $title, string $slug): bool
    {
        if ($slug === '' || preg_match(self::GENERATED_SLUG, $slug)) {
            return true;
        }

        $base = $this->slugify($title);

        if ($slug === $base) {
            return true;
        }

        if (! str_starts_with($slug, $base.'-')) {
            return false;
        }

        return (bool) preg_match('/^[1-9]\d*$/', substr($slug, strlen($base) + 1));
    }
}
