<?php

namespace Tests\Feature;

use App\Mail\CommentReceivedMail;
use App\Models\Comment;
use App\Models\User;
use BSPDX\Keystone\Models\KeystonePermission as Permission;
use Canvas\Models\Post;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class CommentEditingTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    private function admin(): User
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

    private function comment(Post $post, array $attributes = []): Comment
    {
        return Comment::factory()->create(array_merge([
            'post_id' => $post->id,
            'message' => 'Original message',
            'approved_at' => now(),
            'is_spam' => false,
            'depth' => 0,
        ], $attributes));
    }

    private function spam(Post $post, array $attributes = []): Comment
    {
        return $this->comment($post, array_merge(['approved_at' => null, 'is_spam' => true], $attributes));
    }

    public function test_the_policy_is_discovered_for_comments(): void
    {
        $post = $this->makePost();
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $comment = $this->comment($post, ['user_id' => $owner->id]);

        $this->assertTrue($owner->can('update', $comment));
        $this->assertTrue($owner->can('delete', $comment));
        $this->assertFalse($stranger->can('update', $comment));
        $this->assertFalse($stranger->can('delete', $comment));
    }

    public function test_an_author_edits_their_comment(): void
    {
        $post = $this->makePost();
        $owner = User::factory()->create();
        $comment = $this->comment($post, ['user_id' => $owner->id]);

        $this->actingAs($owner)
            ->put(route('comments.update', [$post->slug, $comment]), ['message' => 'Corrected message'])
            ->assertRedirect(route('post', $post->slug).'#comment-'.$comment->id)
            ->assertSessionHas('comment_updated');

        $this->assertSame('Corrected message', $comment->fresh()->message);

        $this->get(route('post', $post->slug))
            ->assertOk()
            ->assertSee('Corrected message')
            ->assertDontSee('Original message');
    }

    public function test_an_empty_edit_is_refused(): void
    {
        $post = $this->makePost();
        $owner = User::factory()->create();
        $comment = $this->comment($post, ['user_id' => $owner->id]);

        $this->actingAs($owner)
            ->put(route('comments.update', [$post->slug, $comment]), ['message' => ''])
            ->assertRedirect(route('post', $post->slug).'#comment-'.$comment->id)
            ->assertSessionHasErrorsIn('commentEdit', ['message']);

        $this->assertSame('Original message', $comment->fresh()->message);
        $this->assertNull($comment->fresh()->edited_at);
    }

    public function test_an_over_long_edit_is_refused(): void
    {
        $post = $this->makePost();
        $owner = User::factory()->create();
        $comment = $this->comment($post, ['user_id' => $owner->id]);

        $this->actingAs($owner)
            ->put(route('comments.update', [$post->slug, $comment]), ['message' => str_repeat('a', 5001)])
            ->assertSessionHasErrorsIn('commentEdit', ['message']);

        $this->assertSame('Original message', $comment->fresh()->message);
    }

    public function test_a_five_thousand_character_edit_is_accepted(): void
    {
        $post = $this->makePost();
        $owner = User::factory()->create();
        $comment = $this->comment($post, ['user_id' => $owner->id]);

        $this->actingAs($owner)
            ->put(route('comments.update', [$post->slug, $comment]), ['message' => str_repeat('a', 5000)])
            ->assertSessionHasNoErrors();

        $this->assertSame(5000, strlen($comment->fresh()->message));
    }

    public function test_an_author_cannot_edit_their_spam_comment(): void
    {
        $post = $this->makePost();
        $owner = User::factory()->create();
        $comment = $this->spam($post, ['user_id' => $owner->id]);

        $this->actingAs($owner)
            ->put(route('comments.update', [$post->slug, $comment]), ['message' => 'Sneaky'])
            ->assertForbidden();

        $this->assertSame('Original message', $comment->fresh()->message);
    }

    public function test_an_author_deletes_their_comment(): void
    {
        $post = $this->makePost();
        $owner = User::factory()->create();
        $comment = $this->comment($post, ['user_id' => $owner->id]);

        $this->actingAs($owner)
            ->delete(route('comments.destroy', [$post->slug, $comment]))
            ->assertRedirect(route('post', $post->slug).'#comments')
            ->assertSessionHas('comment_deleted');

        $this->assertSoftDeleted('comments', ['id' => $comment->id]);

        $this->get(route('post', $post->slug))
            ->assertOk()
            ->assertDontSee('Original message');
    }

    public function test_an_author_cannot_delete_their_spam_comment(): void
    {
        $post = $this->makePost();
        $owner = User::factory()->create();
        $comment = $this->spam($post, ['user_id' => $owner->id]);

        $this->actingAs($owner)
            ->delete(route('comments.destroy', [$post->slug, $comment]))
            ->assertForbidden();

        $this->assertNotSoftDeleted('comments', ['id' => $comment->id]);
    }

    public function test_another_users_comment_cannot_be_edited(): void
    {
        $post = $this->makePost();
        $comment = $this->comment($post);

        $this->actingAs(User::factory()->create())
            ->put(route('comments.update', [$post->slug, $comment]), ['message' => 'Hijacked'])
            ->assertForbidden();

        $this->assertSame('Original message', $comment->fresh()->message);
    }

    public function test_another_users_comment_cannot_be_deleted(): void
    {
        $post = $this->makePost();
        $comment = $this->comment($post);

        $this->actingAs(User::factory()->create())
            ->delete(route('comments.destroy', [$post->slug, $comment]))
            ->assertForbidden();

        $this->assertNotSoftDeleted('comments', ['id' => $comment->id]);
    }

    public function test_a_matching_email_confers_nothing(): void
    {
        $post = $this->makePost();
        $user = User::factory()->create();
        $comment = $this->comment($post, ['user_id' => null, 'email' => $user->email]);

        $this->actingAs($user)
            ->put(route('comments.update', [$post->slug, $comment]), ['message' => 'Mine now'])
            ->assertForbidden();

        $this->actingAs($user)
            ->delete(route('comments.destroy', [$post->slug, $comment]))
            ->assertForbidden();

        $this->assertSame('Original message', $comment->fresh()->message);
        $this->assertNotSoftDeleted('comments', ['id' => $comment->id]);
    }

    public function test_a_guest_cannot_edit(): void
    {
        $post = $this->makePost();
        $comment = $this->comment($post, ['user_id' => null]);

        $this->put(route('comments.update', [$post->slug, $comment]), ['message' => 'Guest edit'])
            ->assertRedirect(route('login'));

        $this->assertSame('Original message', $comment->fresh()->message);
    }

    public function test_a_guest_cannot_delete(): void
    {
        $post = $this->makePost();
        $comment = $this->comment($post, ['user_id' => null]);

        $this->delete(route('comments.destroy', [$post->slug, $comment]))
            ->assertRedirect(route('login'));

        $this->assertNotSoftDeleted('comments', ['id' => $comment->id]);
    }

    public function test_an_admin_edits_another_users_comment_without_taking_authorship(): void
    {
        $post = $this->makePost();
        $author = User::factory()->create();
        $comment = $this->comment($post, ['user_id' => $author->id, 'name' => 'Original Author']);

        $this->actingAs($this->admin())
            ->put(route('comments.update', [$post->slug, $comment]), ['message' => 'Moderated wording'])
            ->assertRedirect(route('post', $post->slug).'#comment-'.$comment->id);

        $comment->refresh();

        $this->assertSame('Moderated wording', $comment->message);
        $this->assertSame($author->id, $comment->user_id);
        $this->assertSame('Original Author', $comment->name);
        $this->assertNotNull($comment->edited_at);
    }

    public function test_an_admin_deletes_an_anonymous_comment(): void
    {
        $post = $this->makePost();
        $comment = $this->comment($post, ['user_id' => null]);

        $this->actingAs($this->admin())
            ->delete(route('comments.destroy', [$post->slug, $comment]))
            ->assertRedirect(route('post', $post->slug).'#comments');

        $this->assertSoftDeleted('comments', ['id' => $comment->id]);
    }

    public function test_an_admin_edits_and_deletes_a_spam_comment(): void
    {
        $post = $this->makePost();
        $comment = $this->spam($post);
        $admin = $this->admin();

        $this->actingAs($admin)
            ->put(route('comments.update', [$post->slug, $comment]), ['message' => 'Scrubbed'])
            ->assertRedirect();

        $this->assertSame('Scrubbed', $comment->fresh()->message);
        $this->assertTrue($comment->fresh()->is_spam);
        $this->assertNull($comment->fresh()->approved_at);

        $this->actingAs($admin)
            ->delete(route('comments.destroy', [$post->slug, $comment]))
            ->assertRedirect();

        $this->assertSoftDeleted('comments', ['id' => $comment->id]);
    }

    public function test_extra_fields_in_an_edit_are_ignored(): void
    {
        $post = $this->makePost();
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $elsewhere = $this->comment($post);
        $comment = $this->comment($post, [
            'user_id' => $owner->id,
            'name' => 'Real Name',
            'email' => 'real@example.com',
            'ip_address' => '203.0.113.9',
            'user_agent' => 'Original Agent',
        ]);
        $approvedAt = $comment->fresh()->approved_at;

        $this->actingAs($owner)
            ->withHeader('User-Agent', 'Editing Agent')
            ->put(route('comments.update', [$post->slug, $comment]), [
                'message' => 'New text',
                'name' => 'Forged Name',
                'email' => 'forged@example.com',
                'parent_id' => $elsewhere->id,
                'user_id' => $other->id,
                'depth' => 3,
                'is_spam' => true,
                'approved_at' => null,
                'edited_at' => '2001-01-01 00:00:00',
                'post_id' => (string) Str::uuid(),
            ])
            ->assertRedirect();

        $comment->refresh();

        $this->assertSame('New text', $comment->message);
        $this->assertSame('Real Name', $comment->name);
        $this->assertSame('real@example.com', $comment->email);
        $this->assertNull($comment->parent_id);
        $this->assertSame($owner->id, $comment->user_id);
        $this->assertSame(0, $comment->depth);
        $this->assertFalse($comment->is_spam);
        $this->assertTrue($approvedAt->equalTo($comment->approved_at));
        $this->assertSame($post->id, $comment->post_id);
        $this->assertSame('203.0.113.9', $comment->ip_address);
        $this->assertSame('Original Agent', $comment->user_agent);
        $this->assertFalse($comment->edited_at->isSameDay('2001-01-01'));
    }

    public function test_changing_the_message_records_the_edit(): void
    {
        $post = $this->makePost();
        $owner = User::factory()->create();
        $comment = $this->comment($post, ['user_id' => $owner->id]);

        $this->assertNull($comment->fresh()->edited_at);

        $this->actingAs($owner)
            ->put(route('comments.update', [$post->slug, $comment]), ['message' => 'Changed']);

        $this->assertTrue($comment->fresh()->isEdited());
    }

    public function test_resubmitting_the_same_message_records_nothing(): void
    {
        $post = $this->makePost();
        $owner = User::factory()->create();
        $comment = $this->comment($post, ['user_id' => $owner->id]);

        $this->actingAs($owner)
            ->put(route('comments.update', [$post->slug, $comment]), ['message' => 'Original message'])
            ->assertRedirect(route('post', $post->slug).'#comment-'.$comment->id);

        $this->assertFalse($comment->fresh()->isEdited());
    }

    public function test_marking_spam_and_restoring_is_not_an_edit(): void
    {
        $post = $this->makePost();
        $comment = $this->comment($post);
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.comments.spam', $comment));
        $this->actingAs($admin)->post(route('admin.comments.not-spam', $comment));

        $this->assertTrue($comment->fresh()->isVisible());
        $this->assertFalse($comment->fresh()->isEdited());
    }

    public function test_a_comment_addressed_through_the_wrong_post_is_not_found(): void
    {
        $post = $this->makePost();
        $otherPost = $this->makePost();
        $owner = User::factory()->create();
        $comment = $this->comment($post, ['user_id' => $owner->id]);

        $this->actingAs($owner)
            ->put(route('comments.update', [$otherPost->slug, $comment]), ['message' => 'Wrong door'])
            ->assertNotFound();

        $this->actingAs($owner)
            ->put(route('comments.update', [$otherPost->slug, $comment]), ['message' => ''])
            ->assertNotFound();

        $this->actingAs($owner)
            ->delete(route('comments.destroy', [$otherPost->slug, $comment]))
            ->assertNotFound();

        $this->assertSame('Original message', $comment->fresh()->message);
        $this->assertNotSoftDeleted('comments', ['id' => $comment->id]);
    }

    public function test_an_already_deleted_comment_is_not_found(): void
    {
        $post = $this->makePost();
        $owner = User::factory()->create();
        $comment = $this->comment($post, ['user_id' => $owner->id]);
        $comment->delete();

        $this->actingAs($owner)
            ->put(route('comments.update', [$post->slug, $comment]), ['message' => 'Too late'])
            ->assertNotFound();

        $this->actingAs($owner)
            ->delete(route('comments.destroy', [$post->slug, $comment]))
            ->assertNotFound();

        $this->assertSame('Original message', Comment::withTrashed()->find($comment->id)->message);
    }

    public function test_deleting_retains_the_row_and_leaves_replies_untouched(): void
    {
        $post = $this->makePost();
        $owner = User::factory()->create();
        $comment = $this->comment($post, ['user_id' => $owner->id]);
        $reply = $this->comment($post, ['parent_id' => $comment->id, 'depth' => 1, 'message' => 'A reply']);
        $nested = $this->comment($post, ['parent_id' => $reply->id, 'depth' => 2, 'message' => 'A nested reply']);

        $this->actingAs($owner)
            ->delete(route('comments.destroy', [$post->slug, $comment]))
            ->assertRedirect();

        $row = Comment::withTrashed()->find($comment->id);
        $this->assertNotNull($row);
        $this->assertNotNull($row->deleted_at);
        $this->assertSame('Original message', $row->message);

        $reply->refresh();
        $nested->refresh();

        $this->assertSame($comment->id, $reply->parent_id);
        $this->assertSame(1, $reply->depth);
        $this->assertTrue($reply->isVisible());
        $this->assertSame($reply->id, $nested->parent_id);
        $this->assertSame(2, $nested->depth);
        $this->assertTrue($nested->isVisible());
    }

    public function test_no_mail_is_sent_on_edit_or_delete(): void
    {
        $post = $this->makePost();
        $owner = User::factory()->create();
        $comment = $this->comment($post, ['user_id' => $owner->id]);
        $queuedByPosting = Mail::queued(CommentReceivedMail::class)->count();

        $this->actingAs($owner)
            ->put(route('comments.update', [$post->slug, $comment]), ['message' => 'Edited'])
            ->assertRedirect();
        $this->actingAs($owner)
            ->delete(route('comments.destroy', [$post->slug, $comment]))
            ->assertRedirect();

        $this->assertSame($queuedByPosting, Mail::queued(CommentReceivedMail::class)->count());
        Mail::assertNothingSent();
    }
}
