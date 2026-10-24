<?php

namespace Tests\Feature\Blog;

use App\Models\Comment;
use App\Models\User;
use Canvas\Events\PostViewed;
use Canvas\Enums\Role;
use Canvas\Models\CanvasUser;
use Canvas\Models\Post;
use Canvas\Models\Tag;
use Canvas\Models\Topic;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Tests\TestCase;

class PostVisibilityTest extends TestCase
{
    use DatabaseTransactions;

    private function makePost(?string $when): Post
    {
        return Post::create([
            'id' => (string) Str::uuid(),
            'title' => 'A post',
            'slug' => 'visibility-'.Str::random(8),
            'summary' => 'Summary',
            'body' => 'Body text',
            'published_at' => match ($when) {
                null => null,
                'future' => now()->addDay(),
                default => now()->subHour(),
            },
            'user_id' => User::factory()->create()->id,
        ]);
    }

    private function makeTaxon(string $model, string $table, string $ownerId): Topic|Tag
    {
        $id = (string) Str::uuid();
        DB::table($table)->insert([
            'id' => $id,
            'name' => 'Name',
            'slug' => 'taxon-'.Str::random(8),
            'user_id' => $ownerId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $model::query()->findOrFail($id);
    }

    private function blogAuthor(): User
    {
        $user = User::factory()->create();
        CanvasUser::query()->create([
            'user_id' => $user->id,
            'role' => Role::Admin,
            'username' => 'author-'.Str::random(6),
        ]);

        return $user;
    }

    public function test_a_published_post_is_shown(): void
    {
        $post = $this->makePost('past');

        $this->get(route('post', $post->slug))->assertOk()->assertSee('Body text');
    }

    public function test_scheduled_and_draft_posts_are_not_found_for_anonymous_visitors(): void
    {
        Event::fake([PostViewed::class]);

        foreach (['future', null] as $when) {
            $post = $this->makePost($when);

            $this->get(route('post', $post->slug))->assertNotFound();
        }

        Event::assertNotDispatched(PostViewed::class);
    }

    public function test_scheduled_and_draft_posts_are_not_found_for_a_user_without_blog_access(): void
    {
        Event::fake([PostViewed::class]);
        $this->actingAs(User::factory()->create());

        foreach (['future', null] as $when) {
            $post = $this->makePost($when);

            $this->get(route('post', $post->slug))->assertNotFound();
        }

        Event::assertNotDispatched(PostViewed::class);
    }

    public function test_a_blog_author_can_preview_unpublished_posts(): void
    {
        Event::fake([PostViewed::class]);
        $this->actingAs($this->blogAuthor());

        foreach (['future', null] as $when) {
            $post = $this->makePost($when);

            $this->get(route('post', $post->slug))
                ->assertOk()
                ->assertSee('Body text')
                ->assertSee('not published');
        }

        Event::assertNotDispatched(PostViewed::class);
    }

    public function test_a_published_post_shows_no_preview_notice(): void
    {
        $this->actingAs($this->blogAuthor());
        $post = $this->makePost('past');

        $this->get(route('post', $post->slug))->assertOk()->assertDontSee('not published');
    }

    public function test_comments_cannot_be_submitted_edited_or_spammed_through_an_unpublished_post(): void
    {
        $post = $this->makePost('future');
        $comment = Comment::factory()->create(['post_id' => $post->id, 'depth' => 0]);
        $this->actingAs($this->blogAuthor());
        Gate::define('moderate-comments', fn () => true);

        $this->post(route('comments.store', $post->slug), ['message' => 'Hello'])->assertNotFound();
        $this->put(route('comments.update', [$post->slug, $comment]), ['message' => 'Edited'])->assertNotFound();
        $this->post(route('comments.spam', [$post->slug, $comment]), [])->assertNotFound();

        $this->assertSame(1, Comment::query()->where('post_id', $post->id)->count());
        $this->assertNotSame('Edited', $comment->fresh()->message);
        $this->assertFalse($comment->fresh()->is_spam);
    }

    public function test_topic_and_tag_listings_and_counts_exclude_unpublished_posts(): void
    {
        $published = $this->makePost('past');
        $scheduled = $this->makePost('future');
        $ownerId = $published->getRawOriginal('user_id');
        $topic = $this->makeTaxon(Topic::class, 'canvas_topics', $ownerId);
        $tag = $this->makeTaxon(Tag::class, 'canvas_tags', $ownerId);
        foreach ([$published, $scheduled] as $post) {
            $post->forceFill(['topic_id' => $topic->id])->save();
            $post->tags()->sync([$tag->id]);
        }
        $scheduled->update(['title' => 'Scheduled secret']);
        $published->update(['title' => 'Visible one']);

        foreach ([route('topicList', $topic->slug), route('tagList', $tag->slug)] as $url) {
            $this->get($url)->assertOk()->assertSee('Visible one')->assertDontSee('Scheduled secret');
        }

        $this->get(route('topics'))->assertSee('1 posts')->assertDontSee('2 posts');
        $this->get(route('tags'))->assertSee('1 posts')->assertDontSee('2 posts');
    }
}
