<?php

namespace Tests\Feature;

use App\Models\Comment;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Mail;
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
}
