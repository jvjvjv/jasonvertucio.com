<?php

namespace Tests\Feature;

use App\Mail\CommentReceivedMail;
use App\Models\Comment;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CommentNotificationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_the_mailable_is_queueable(): void
    {
        $this->assertInstanceOf(ShouldQueue::class, CommentReceivedMail::preview());
    }

    public function test_the_configured_recipient_is_used(): void
    {
        Mail::fake();
        config()->set('comments.notification_email', 'inbox@example.com');
        config()->set('comments.owner_email', 'owner@example.com');

        Comment::factory()->create(['email' => 'visitor@example.com']);

        Mail::assertQueued(CommentReceivedMail::class, function (CommentReceivedMail $mail): bool {
            return $mail->hasTo('inbox@example.com');
        });
    }

    public function test_the_default_recipient_applies_when_unset(): void
    {
        $this->assertSame('me@jasonvertucio.com', config('comments.notification_email'));
    }

    public function test_the_owners_own_comment_dispatches_nothing(): void
    {
        Mail::fake();
        config()->set('comments.owner_email', 'owner@example.com');

        Comment::factory()->anonymous()->create(['email' => 'owner@example.com']);

        Mail::assertNothingQueued();
        Mail::assertNothingSent();
    }

    public function test_an_owner_match_is_case_insensitive(): void
    {
        Mail::fake();
        config()->set('comments.owner_email', 'owner@example.com');

        Comment::factory()->anonymous()->create(['email' => 'OWNER@Example.COM']);

        Mail::assertNothingQueued();
    }

    public function test_a_registered_owner_is_matched_by_their_user_email(): void
    {
        Mail::fake();
        config()->set('comments.owner_email', 'owner@example.com');
        $owner = User::factory()->create(['email' => 'owner@example.com']);

        Comment::factory()->create(['user_id' => $owner->id, 'email' => 'something-else@example.com']);

        Mail::assertNothingQueued();
    }

    public function test_someone_elses_comment_is_notified(): void
    {
        Mail::fake();
        config()->set('comments.owner_email', 'owner@example.com');

        Comment::factory()->anonymous()->create(['email' => 'visitor@example.com']);

        Mail::assertQueued(CommentReceivedMail::class);
    }

    public function test_the_template_renders_with_a_null_post(): void
    {
        $comment = Comment::factory()->make([
            'post_id' => null,
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'message' => 'Body text',
        ]);
        $comment->setRelation('post', null);

        $rendered = (new CommentReceivedMail($comment))->render();

        $this->assertStringContainsString('Ada Lovelace', $rendered);
        $this->assertStringContainsString('Body text', $rendered);
        $this->assertStringContainsString('mailto:ada@example.com', $rendered);
    }
}
