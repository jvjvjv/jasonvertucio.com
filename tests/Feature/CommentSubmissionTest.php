<?php

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\User;
use Canvas\Models\Post;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Tests\TestCase;

class CommentSubmissionTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        RateLimiter::clear('comments');
    }

    private function makePost(?string $publishedAt = 'now'): Post
    {
        return Post::create([
            'id' => (string) Str::uuid(),
            'title' => 'A post',
            'slug' => 'a-post-'.Str::random(8),
            'summary' => 'Summary',
            'body' => 'Body',
            'published_at' => $publishedAt === null ? null : now(),
            'user_id' => User::factory()->create()->id,
        ]);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'message' => 'First!',
        ], $overrides);
    }

    public function test_an_anonymous_visitor_can_comment(): void
    {
        $post = $this->makePost();

        $this->post(route('comments.store', $post->slug), $this->payload())
            ->assertRedirect();

        $comment = Comment::query()->where('post_id', $post->id)->firstOrFail();

        $this->assertNull($comment->user_id);
        $this->assertSame('Ada Lovelace', $comment->name);
        $this->assertSame(0, $comment->depth);
        $this->assertNotNull($comment->approved_at);
        $this->assertFalse($comment->is_spam);
    }

    public function test_an_authenticated_user_comments_with_a_name_snapshot(): void
    {
        $post = $this->makePost();
        $user = User::factory()->create(['name' => 'Grace Hopper']);

        $this->actingAs($user)
            ->post(route('comments.store', $post->slug), ['message' => 'Hello'])
            ->assertRedirect();

        $comment = Comment::query()->where('post_id', $post->id)->firstOrFail();

        $this->assertSame($user->id, $comment->user_id);
        $this->assertSame('Grace Hopper', $comment->name);

        $user->update(['name' => 'Renamed']);

        $this->assertSame('Grace Hopper', $comment->fresh()->name);
    }

    public function test_a_reply_to_a_depth_four_comment_is_accepted(): void
    {
        $post = $this->makePost();
        $parent = Comment::factory()->create(['post_id' => $post->id, 'depth' => 4]);

        $this->post(route('comments.store', $post->slug), $this->payload(['parent_id' => $parent->id]))
            ->assertRedirect();

        $this->assertSame(5, Comment::query()->where('parent_id', $parent->id)->firstOrFail()->depth);
    }

    public function test_a_reply_to_a_depth_five_comment_is_rejected(): void
    {
        $post = $this->makePost();
        $parent = Comment::factory()->create(['post_id' => $post->id, 'depth' => 5]);

        $this->post(route('comments.store', $post->slug), $this->payload(['parent_id' => $parent->id]))
            ->assertSessionHasErrors('parent_id');

        $this->assertSame(0, Comment::query()->where('parent_id', $parent->id)->count());
    }

    public function test_a_parent_on_a_different_post_is_rejected(): void
    {
        $post = $this->makePost();
        $otherPost = $this->makePost();
        $parent = Comment::factory()->create(['post_id' => $otherPost->id, 'depth' => 0]);

        $this->post(route('comments.store', $post->slug), $this->payload(['parent_id' => $parent->id]))
            ->assertSessionHasErrors('parent_id');

        $this->assertSame(0, Comment::query()->where('parent_id', $parent->id)->count());
    }

    public function test_an_unpublished_post_refuses_comments(): void
    {
        $post = $this->makePost(null);

        $this->post(route('comments.store', $post->slug), $this->payload())
            ->assertSessionHasErrors('post');

        $this->assertSame(0, Comment::query()->where('post_id', $post->id)->count());
    }

    public function test_a_honeypot_submission_is_silently_discarded(): void
    {
        $post = $this->makePost();

        $clean = $this->post(route('comments.store', $post->slug), $this->payload());
        RateLimiter::clear('comments');
        $trapped = $this->post(route('comments.store', $post->slug), $this->payload([
            'website' => 'http://spam.example',
        ]));

        $trapped->assertStatus($clean->getStatusCode());
        $trapped->assertSessionHasNoErrors();

        $this->assertSame(1, Comment::query()->where('post_id', $post->id)->count());
    }

    public function test_repeat_submissions_are_throttled(): void
    {
        $post = $this->makePost();
        $limit = config('comments.rate_limit_per_minute');

        for ($i = 0; $i < $limit; $i++) {
            $this->post(route('comments.store', $post->slug), $this->payload(['message' => "Comment {$i}"]))
                ->assertRedirect();
        }

        $this->post(route('comments.store', $post->slug), $this->payload(['message' => 'One too many']))
            ->assertStatus(429);

        $this->assertSame($limit, Comment::query()->where('post_id', $post->id)->count());
    }

    public function test_the_client_address_prefers_the_cloudflare_header(): void
    {
        $post = $this->makePost();

        $this->withHeader('CF-Connecting-IP', '203.0.113.7')
            ->post(route('comments.store', $post->slug), $this->payload())
            ->assertRedirect();

        $this->assertSame('203.0.113.7', Comment::query()->where('post_id', $post->id)->firstOrFail()->ip_address);
    }

    public function test_a_comment_requires_a_message(): void
    {
        $post = $this->makePost();

        $this->post(route('comments.store', $post->slug), $this->payload(['message' => '']))
            ->assertSessionHasErrors('message');

        $this->assertSame(0, Comment::query()->where('post_id', $post->id)->count());
    }
}
