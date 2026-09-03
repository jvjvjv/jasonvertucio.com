<?php

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\User;
use BSPDX\Keystone\Models\KeystonePermission as Permission;
use Canvas\Models\Post;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CommentModerationTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    private function moderator(): User
    {
        $user = User::factory()->create();
        Permission::firstOrCreate(['name' => 'manage-blog']);
        $user->givePermissionTo('manage-blog');

        return $user;
    }

    private function makePost(): Post
    {
        return Post::create([
            'id' => (string) Str::uuid(),
            'title' => 'A post',
            'slug' => 'a-post-'.Str::random(8),
            'summary' => 'Summary',
            'body' => 'Body',
            'published_at' => now(),
            'user_id' => User::factory()->create()->id,
        ]);
    }

    public function test_a_permitted_user_sees_the_queue(): void
    {
        $post = $this->makePost();
        $comment = Comment::factory()->create([
            'post_id' => $post->id,
            'approved_at' => now(),
            'is_spam' => false,
        ]);

        $this->actingAs($this->moderator())
            ->get(route('admin.comments.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('comments/Index', false)
                ->has('comments.data')
                ->where('comments.data.0.id', $comment->id)
                ->where('comments.data.0.is_spam', false)
            );
    }

    public function test_an_unpermitted_user_is_refused(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.comments.index'))
            ->assertForbidden();
    }

    public function test_a_guest_is_refused(): void
    {
        $this->get(route('admin.comments.index'))->assertRedirect();
    }

    public function test_marking_spam_hides_the_comment_and_retains_the_row(): void
    {
        $post = $this->makePost();
        $comment = Comment::factory()->create([
            'post_id' => $post->id,
            'message' => 'Spammy body',
            'approved_at' => now(),
            'is_spam' => false,
        ]);

        $this->actingAs($this->moderator())
            ->post(route('admin.comments.spam', $comment))
            ->assertRedirect();

        $comment->refresh();

        $this->assertTrue($comment->is_spam);
        $this->assertNull($comment->approved_at);
        $this->assertDatabaseHas('comments', ['id' => $comment->id]);

        $this->get(route('post', $post->slug))
            ->assertOk()
            ->assertDontSee('Spammy body');
    }

    public function test_marking_not_spam_restores_with_a_fresh_timestamp(): void
    {
        $post = $this->makePost();
        $comment = Comment::factory()->create([
            'post_id' => $post->id,
            'message' => 'Restored body',
            'approved_at' => null,
            'is_spam' => true,
        ]);

        $this->travelTo(now()->addMinutes(5));

        $this->actingAs($this->moderator())
            ->post(route('admin.comments.not-spam', $comment))
            ->assertRedirect();

        $comment->refresh();

        $this->assertFalse($comment->is_spam);
        $this->assertNotNull($comment->approved_at);
        $this->assertTrue($comment->approved_at->greaterThan($comment->created_at));

        $this->travelBack();

        $this->get(route('post', $post->slug))
            ->assertOk()
            ->assertSee('Restored body');
    }

    public function test_no_path_produces_an_approved_spam_comment(): void
    {
        $post = $this->makePost();
        $comment = Comment::factory()->create([
            'post_id' => $post->id,
            'approved_at' => now(),
            'is_spam' => false,
        ]);
        $moderator = $this->moderator();

        $this->actingAs($moderator)->post(route('admin.comments.spam', $comment));
        $this->actingAs($moderator)->post(route('admin.comments.not-spam', $comment));
        $this->actingAs($moderator)->post(route('admin.comments.spam', $comment));

        $this->assertSame(
            0,
            Comment::query()->whereNotNull('approved_at')->where('is_spam', true)->count()
        );
    }

    public function test_an_unpermitted_user_cannot_mark_spam(): void
    {
        $comment = Comment::factory()->create(['approved_at' => now(), 'is_spam' => false]);

        $this->actingAs(User::factory()->create())
            ->post(route('admin.comments.spam', $comment))
            ->assertForbidden();

        $this->assertFalse($comment->fresh()->is_spam);
    }
}
