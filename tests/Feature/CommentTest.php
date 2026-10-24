<?php

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CommentTest extends TestCase
{
    use DatabaseTransactions;

    public function test_a_comment_can_be_created_through_the_factory(): void
    {
        Mail::fake();

        $comment = Comment::factory()->create();

        $this->assertDatabaseHas('comments', [
            'id' => $comment->id,
            'message' => $comment->message,
        ]);
    }

    public function test_the_facebook_parent_comment_id_is_mass_assignable(): void
    {
        Mail::fake();

        $comment = Comment::factory()->create(['fb_comment_parent_id' => '1234567890']);

        $this->assertSame('1234567890', $comment->fresh()->fb_comment_parent_id);
    }

    public function test_the_visible_scope_excludes_unapproved_and_spam(): void
    {
        Mail::fake();

        $visible = Comment::factory()->create(['approved_at' => now(), 'is_spam' => false]);
        $spam = Comment::factory()->create(['approved_at' => null, 'is_spam' => true]);
        $held = Comment::factory()->create(['approved_at' => null, 'is_spam' => false]);

        $ids = Comment::query()->visible()->pluck('id');

        $this->assertTrue($ids->contains($visible->id));
        $this->assertFalse($ids->contains($spam->id));
        $this->assertFalse($ids->contains($held->id));
    }

    public function test_replies_and_parent_relationships_resolve(): void
    {
        Mail::fake();

        $parent = Comment::factory()->create(['depth' => 0]);
        $reply = Comment::factory()->create(['parent_id' => $parent->id, 'depth' => 1]);

        $this->assertTrue($parent->replies->contains($reply->id));
        $this->assertSame($parent->id, $reply->parent->id);
    }

    public function test_accepts_replies_stops_at_max_depth(): void
    {
        Mail::fake();

        $deep = Comment::factory()->make(['depth' => Comment::MAX_DEPTH]);
        $shallow = Comment::factory()->make(['depth' => Comment::MAX_DEPTH - 1]);

        $this->assertFalse($deep->acceptsReplies());
        $this->assertTrue($shallow->acceptsReplies());
    }

    public function test_an_anonymous_comment_has_no_user(): void
    {
        Mail::fake();

        $comment = Comment::factory()->anonymous()->create();

        $this->assertNull($comment->user_id);
        $this->assertNotNull($comment->name);
    }

    public function test_a_trashed_approved_comment_is_not_visible(): void
    {
        Mail::fake();

        $comment = Comment::factory()->create(['approved_at' => now(), 'is_spam' => false]);
        $this->assertTrue($comment->isVisible());

        $comment->delete();

        $this->assertFalse($comment->isVisible());
    }

    public function test_a_comment_reports_edited_only_once_edited_at_is_set(): void
    {
        Mail::fake();

        $comment = Comment::factory()->create(['approved_at' => now(), 'is_spam' => false]);
        $this->assertFalse($comment->fresh()->isEdited());

        $comment->edited_at = now();
        $comment->save();

        $this->assertTrue($comment->fresh()->isEdited());
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $comment->fresh()->edited_at);
    }

    public function test_edited_at_cannot_be_mass_assigned(): void
    {
        Mail::fake();

        $comment = Comment::factory()->create(['approved_at' => now(), 'is_spam' => false]);
        $comment->update(['edited_at' => now()]);

        $this->assertNull($comment->fresh()->edited_at);
    }

    public function test_the_edited_factory_state_marks_a_comment_edited(): void
    {
        Mail::fake();

        $this->assertTrue(Comment::factory()->edited()->create()->fresh()->isEdited());
    }

    /**
     * @return array<string, array{string, string, bool, bool}>
     */
    public static function manageabilityCases(): array
    {
        return [
            'owner of a visible comment' => ['owner', 'visible', false, true],
            'owner of a spam comment' => ['owner', 'spam', false, false],
            'owner of a deleted comment' => ['owner', 'deleted', false, false],
            'another user' => ['other', 'visible', false, false],
            'anonymous comment with a matching email' => ['email-match', 'visible', false, false],
            'no user' => ['guest', 'visible', false, false],
            'admin on an owned visible comment' => ['owner', 'visible', true, true],
            'admin on a spam comment' => ['owner', 'spam', true, true],
            'admin on another user\'s comment' => ['other', 'visible', true, true],
            'admin on an anonymous comment' => ['email-match', 'visible', true, true],
        ];
    }

    #[DataProvider('manageabilityCases')]
    public function test_is_manageable_by(string $relation, string $state, bool $isAdmin, bool $expected): void
    {
        Mail::fake();

        $viewer = User::factory()->create();

        $attributes = match ($state) {
            'spam' => ['approved_at' => null, 'is_spam' => true],
            default => ['approved_at' => now(), 'is_spam' => false],
        };

        $comment = match ($relation) {
            'owner' => Comment::factory()->create($attributes + ['user_id' => $viewer->id]),
            'email-match' => Comment::factory()->anonymous()->create($attributes + ['email' => $viewer->email]),
            default => Comment::factory()->create($attributes),
        };

        if ($state === 'deleted') {
            $comment->delete();
        }

        $this->assertSame($expected, $comment->isManageableBy($relation === 'guest' ? null : $viewer, $isAdmin));
    }
}
