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

    public function test_a_moderator_deletes_a_spam_comment_and_it_leaves_the_queue(): void
    {
        $post = $this->makePost();
        $comment = Comment::factory()->create([
            'post_id' => $post->id,
            'approved_at' => null,
            'is_spam' => true,
        ]);
        $moderator = $this->moderator();

        $this->actingAs($moderator)
            ->from(route('admin.comments.index'))
            ->delete(route('admin.comments.destroy', $comment))
            ->assertRedirect(route('admin.comments.index'))
            ->assertSessionHas('success');

        $this->assertSoftDeleted('comments', ['id' => $comment->id]);

        $this->actingAs($moderator)
            ->get(route('admin.comments.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('comments.data', fn ($rows) => ! collect($rows)->pluck('id')->contains($comment->id))
            );
    }

    public function test_deleting_an_approved_comment_leaves_its_replies_listed_and_displayed(): void
    {
        $post = $this->makePost();
        $comment = Comment::factory()->create([
            'post_id' => $post->id,
            'message' => 'Parent body',
            'approved_at' => now(),
            'is_spam' => false,
            'depth' => 0,
        ]);
        $reply = Comment::factory()->create([
            'post_id' => $post->id,
            'parent_id' => $comment->id,
            'message' => 'Reply body',
            'approved_at' => now(),
            'is_spam' => false,
            'depth' => 1,
        ]);
        $moderator = $this->moderator();

        $this->actingAs($moderator)
            ->delete(route('admin.comments.destroy', $comment))
            ->assertRedirect();

        $this->assertSoftDeleted('comments', ['id' => $comment->id]);
        $this->assertNotSoftDeleted('comments', ['id' => $reply->id, 'parent_id' => $comment->id, 'depth' => 1]);

        $this->actingAs($moderator)
            ->get(route('admin.comments.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('comments.data', fn ($rows) => collect($rows)->pluck('id')->contains($reply->id)
                    && ! collect($rows)->pluck('id')->contains($comment->id))
            );

        $this->get(route('post', $post->slug))
            ->assertOk()
            ->assertSee('Reply body')
            ->assertSee('[comment removed]')
            ->assertDontSee('Parent body');
    }

    public function test_an_unpermitted_user_cannot_delete_through_the_queue(): void
    {
        $author = User::factory()->create();
        $comment = Comment::factory()->create(['user_id' => $author->id, 'approved_at' => now(), 'is_spam' => false]);

        $this->actingAs($author)
            ->delete(route('admin.comments.destroy', $comment))
            ->assertForbidden();

        $this->delete(route('admin.comments.destroy', $comment));

        $this->assertNotSoftDeleted('comments', ['id' => $comment->id]);
    }

    public function test_deleting_from_the_queue_leaves_approval_and_spam_state_alone(): void
    {
        $approved = Comment::factory()->create(['approved_at' => now()->subDay(), 'is_spam' => false]);
        $spam = Comment::factory()->create(['approved_at' => null, 'is_spam' => true]);
        $approvedAt = $approved->fresh()->approved_at;
        $moderator = $this->moderator();

        $this->actingAs($moderator)->delete(route('admin.comments.destroy', $approved))->assertRedirect();
        $this->actingAs($moderator)->delete(route('admin.comments.destroy', $spam))->assertRedirect();

        $approved = Comment::withTrashed()->find($approved->id);
        $spam = Comment::withTrashed()->find($spam->id);

        $this->assertTrue($approvedAt->equalTo($approved->approved_at));
        $this->assertFalse($approved->is_spam);
        $this->assertNull($approved->edited_at);
        $this->assertNull($spam->approved_at);
        $this->assertTrue($spam->is_spam);
    }
}
