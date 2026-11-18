<?php

namespace Tests\Feature;

use App\Jobs\NotifyCommentReplyRecipient;
use App\Mail\CommentReplyConfirmationMail;
use App\Mail\CommentReplyMail;
use App\Models\Comment;
use App\Models\CommentEmailOptOut;
use App\Models\User;
use App\Support\CommentReplyEmails;
use Canvas\Models\Post;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Routing\Exceptions\InvalidSignatureException;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class CommentReplyEmailLinksTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        config()->set('comments.reply_notifications.enabled', true);
    }

    private function makePost(): Post
    {
        return Post::create([
            'id' => (string) Str::uuid(),
            'title' => 'On threading',
            'slug' => 'on-threading-'.Str::random(8),
            'summary' => 'Summary',
            'body' => 'Body',
            'published_at' => now()->subMinute(),
            'user_id' => User::factory()->create()->id,
        ]);
    }

    private function pendingComment(array $attributes = []): Comment
    {
        return Comment::withoutEvents(fn () => Comment::factory()->anonymous()->forPost($this->makePost())->wantsReplyEmails()->create(array_merge([
            'email' => 'reader@example.com',
            'approved_at' => now(),
        ], $attributes)));
    }

    private function subscribedComment(array $attributes = []): Comment
    {
        return Comment::withoutEvents(fn () => Comment::factory()->anonymous()->forPost($this->makePost())->subscribedToReplies()->create(array_merge([
            'email' => 'reader@example.com',
            'approved_at' => now(),
        ], $attributes)));
    }

    /**
     * The path and query of an absolute URL, as a test request wants it.
     */
    private function relative(string $url): string
    {
        $parts = parse_url($url);

        return $parts['path'].(isset($parts['query']) ? '?'.$parts['query'] : '');
    }

    public function test_loading_the_confirm_link_shows_the_button_and_confirms_nothing(): void
    {
        $comment = $this->pendingComment();

        $this->get($this->relative(CommentReplyEmails::confirmUrl($comment)))
            ->assertOk()
            ->assertSee('Confirm reply emails')
            ->assertSee('On threading');

        $this->assertNull($comment->fresh()->notify_replies_confirmed_at);
    }

    public function test_pressing_confirm_activates_the_subscription(): void
    {
        $comment = $this->pendingComment();

        $this->post($this->relative(CommentReplyEmails::confirmUrl($comment)))
            ->assertOk()
            ->assertSee('Reply emails confirmed');

        $this->assertTrue($comment->fresh()->hasActiveReplySubscription());
    }

    public function test_the_confirm_form_posts_back_to_the_signed_url(): void
    {
        $comment = $this->pendingComment();
        $path = $this->relative(CommentReplyEmails::confirmUrl($comment));

        $this->get($path)->assertSee('action="'.e($path).'"', false);
    }

    public function test_a_spam_comment_cannot_be_confirmed(): void
    {
        $comment = $this->pendingComment(['is_spam' => true, 'approved_at' => null]);

        $this->post($this->relative(CommentReplyEmails::confirmUrl($comment)))->assertStatus(410);

        $this->assertNull($comment->fresh()->notify_replies_confirmed_at);
    }

    public function test_a_deleted_comment_cannot_be_confirmed(): void
    {
        $comment = $this->pendingComment();
        $path = $this->relative(CommentReplyEmails::confirmUrl($comment));
        $comment->delete();

        $this->post($path)->assertNotFound();

        $this->assertNull(Comment::withTrashed()->find($comment->id)->notify_replies_confirmed_at);
    }

    public function test_a_comment_no_longer_opted_in_cannot_be_confirmed(): void
    {
        $comment = $this->pendingComment(['notify_replies' => false]);

        $this->post($this->relative(CommentReplyEmails::confirmUrl($comment)))->assertStatus(410);

        $this->assertNull($comment->fresh()->notify_replies_confirmed_at);
    }

    public function test_confirming_lifts_an_earlier_opt_out(): void
    {
        $comment = $this->pendingComment(['email' => 'Opted@Example.com']);
        CommentEmailOptOut::optOut('opted@example.com');

        $this->get($this->relative(CommentReplyEmails::confirmUrl($comment)))->assertOk();
        $this->assertTrue(CommentEmailOptOut::covers('opted@example.com'));

        $this->post($this->relative(CommentReplyEmails::confirmUrl($comment)))
            ->assertOk()
            ->assertSee('Reply emails confirmed');

        $this->assertTrue($comment->fresh()->hasActiveReplySubscription());
        $this->assertFalse(CommentEmailOptOut::covers('opted@example.com'));
    }

    public function test_confirming_an_unverified_users_comment_lifts_the_recorded_address(): void
    {
        $user = User::factory()->unverified()->create(['email' => 'now-'.Str::random(6).'@example.com']);
        $comment = $this->pendingComment(['user_id' => $user->id, 'email' => 'recorded@example.com']);
        CommentEmailOptOut::optOut('recorded@example.com');
        CommentEmailOptOut::optOut($user->email);

        $this->post($this->relative(CommentReplyEmails::confirmUrl($comment)))->assertOk();

        $this->assertFalse(CommentEmailOptOut::covers('recorded@example.com'));
        $this->assertTrue(CommentEmailOptOut::covers($user->email));
        $this->assertSame('recorded@example.com', $comment->fresh()->replyNotificationAddress());
    }

    public function test_a_reply_is_emailed_after_opting_back_in(): void
    {
        $comment = $this->pendingComment(['email' => 'returning@example.com']);
        CommentEmailOptOut::optOut('returning@example.com');
        $this->post($this->relative(CommentReplyEmails::confirmUrl($comment)))->assertOk();

        $reply = Comment::withoutEvents(fn () => Comment::factory()->anonymous()->forPost($comment->post)->asReply($comment)->create([
            'email' => 'someone@example.com',
            'depth' => 1,
            'approved_at' => now(),
        ]));
        (new NotifyCommentReplyRecipient($reply->id))->handle();

        Mail::assertSent(CommentReplyMail::class, fn (CommentReplyMail $mail): bool => $mail->hasTo('returning@example.com'));
    }

    public function test_an_expired_link_for_a_pending_comment_offers_a_new_one(): void
    {
        $comment = $this->pendingComment();
        $path = $this->relative(CommentReplyEmails::confirmUrl($comment));
        $this->travel(8)->days();

        $this->get($path)
            ->assertForbidden()
            ->assertSee('This link has expired')
            ->assertSee('Send me a new link')
            ->assertSee('/comments/'.$comment->id.'/reply-emails/resend?expires=', false);

        $this->post($path)
            ->assertForbidden()
            ->assertSee('This link has expired');

        $this->assertNull($comment->fresh()->notify_replies_confirmed_at);
    }

    public function test_pressing_send_me_a_new_link_queues_a_confirmation(): void
    {
        $comment = $this->pendingComment();

        $this->post(CommentReplyEmails::resendUrl($comment))
            ->assertOk()
            ->assertSee('New link sent')
            ->assertSee('r•••••@example.com');

        Mail::assertQueued(CommentReplyConfirmationMail::class, fn (CommentReplyConfirmationMail $mail): bool => $mail->hasTo('reader@example.com') && $mail->comment->is($comment));
    }

    public function test_new_links_count_against_the_daily_cap(): void
    {
        config()->set('comments.reply_notifications.confirmations_per_address_per_day', 1);
        $comment = $this->pendingComment();

        $this->post(CommentReplyEmails::resendUrl($comment))->assertOk();
        $this->post(CommentReplyEmails::resendUrl($comment))
            ->assertStatus(429)
            ->assertSee('Too many links today');

        Mail::assertQueued(CommentReplyConfirmationMail::class, 1);
    }

    public function test_no_new_link_for_a_confirmed_or_hidden_comment(): void
    {
        $confirmed = $this->subscribedComment();
        $hidden = $this->pendingComment(['is_spam' => true, 'approved_at' => null]);

        $this->post(CommentReplyEmails::resendUrl($confirmed))->assertStatus(410)->assertSee('Reply emails are already on');
        $this->post(CommentReplyEmails::resendUrl($hidden))->assertStatus(410)->assertSee("This comment can't get reply emails", false);

        Mail::assertNotQueued(CommentReplyConfirmationMail::class);
    }

    public function test_an_expired_link_for_a_confirmed_comment_has_no_button(): void
    {
        $comment = $this->pendingComment();
        $path = $this->relative(CommentReplyEmails::confirmUrl($comment));
        $comment->forceFill(['notify_replies_confirmed_at' => now()])->saveQuietly();
        $this->travel(8)->days();

        $this->get($path)
            ->assertForbidden()
            ->assertSee('Reply emails are already on')
            ->assertDontSee('Send me a new link');
    }

    public function test_an_expired_resend_button_offers_a_fresh_one(): void
    {
        $comment = $this->pendingComment();
        $resend = CommentReplyEmails::resendUrl($comment);
        $this->travel(2)->hours();

        $this->post($resend)
            ->assertForbidden()
            ->assertSee('This link has expired')
            ->assertSee('Send me a new link');

        Mail::assertNotQueued(CommentReplyConfirmationMail::class);
    }

    public function test_a_tampered_link_says_it_is_not_valid(): void
    {
        $comment = $this->pendingComment();
        $tampered = str_replace('signature=', 'signature=0', $this->relative(CommentReplyEmails::confirmUrl($comment)));

        $this->get($tampered)
            ->assertForbidden()
            ->assertSee("This link isn't valid", false)
            ->assertDontSee('Send me a new link')
            ->assertDontSee('On threading');

        $this->get('/comments/'.$comment->id.'/reply-emails/unsubscribe')
            ->assertForbidden()
            ->assertSee("This link isn't valid", false);
    }

    public function test_a_forged_resend_is_refused(): void
    {
        $comment = $this->pendingComment();

        $this->post('/comments/'.$comment->id.'/reply-emails/resend')
            ->assertForbidden()
            ->assertDontSee('Send me a new link');

        Mail::assertNotQueued(CommentReplyConfirmationMail::class);
    }

    public function test_other_signature_errors_are_unaffected(): void
    {
        $handler = $this->app->make(ExceptionHandler::class);
        $request = Request::create('/somewhere-else', 'GET');

        $response = $handler->render($request, new InvalidSignatureException);

        $this->assertSame(403, $response->getStatusCode());
        $this->assertStringNotContainsString('This link isn', $response->getContent());
    }

    public function test_an_unpublished_posts_title_is_not_shown(): void
    {
        $comment = $this->subscribedComment();
        $comment->post->forceFill(['published_at' => null])->save();

        $this->get($this->relative(CommentReplyEmails::unsubscribeUrl($comment)))
            ->assertOk()
            ->assertDontSee('On threading')
            ->assertDontSee('Back to the comment');
    }

    public function test_the_confirmation_mail_is_dropped_when_its_comment_is_gone(): void
    {
        $this->assertTrue((new CommentReplyConfirmationMail(Comment::factory()->make()))->deleteWhenMissingModels);
    }

    public function test_a_missing_signature_is_refused(): void
    {
        $comment = $this->pendingComment();

        $this->post('/comments/'.$comment->id.'/reply-emails/confirm')->assertForbidden();

        $this->assertNull($comment->fresh()->notify_replies_confirmed_at);
    }

    public function test_an_altered_signature_is_refused(): void
    {
        $comment = $this->pendingComment();
        $other = $this->pendingComment();

        $forged = str_replace('/comments/'.$other->id.'/', '/comments/'.$comment->id.'/', $this->relative(CommentReplyEmails::confirmUrl($other)));

        $this->post($forged)->assertForbidden();

        $this->assertNull($comment->fresh()->notify_replies_confirmed_at);
    }

    public function test_an_expired_link_is_refused(): void
    {
        config()->set('comments.reply_notifications.confirmation_expires_days', 7);
        $comment = $this->pendingComment();
        $path = $this->relative(CommentReplyEmails::confirmUrl($comment));

        $this->travel(8)->days();

        $this->post($path)->assertForbidden();

        $this->assertNull($comment->fresh()->notify_replies_confirmed_at);
    }

    public function test_loading_the_unsubscribe_link_changes_nothing(): void
    {
        $comment = $this->subscribedComment();

        $this->get($this->relative(CommentReplyEmails::unsubscribeUrl($comment)))
            ->assertOk()
            ->assertSee('Stop emails for this comment')
            ->assertSee('Stop all comment emails')
            ->assertSee('r•••••@example.com')
            ->assertDontSee('reader@example.com');

        $this->assertTrue($comment->fresh()->hasActiveReplySubscription());
        $this->assertFalse(CommentEmailOptOut::covers('reader@example.com'));
    }

    public function test_stopping_one_comment_leaves_the_others(): void
    {
        $comment = $this->subscribedComment();
        $other = $this->subscribedComment();

        $this->post($this->relative(CommentReplyEmails::unsubscribeUrl($comment)), ['scope' => 'comment'])
            ->assertOk()
            ->assertSee('Emails stopped for this comment');

        $this->assertFalse($comment->fresh()->hasActiveReplySubscription());
        $this->assertTrue($other->fresh()->hasActiveReplySubscription());
        $this->assertFalse(CommentEmailOptOut::covers('reader@example.com'));
    }

    public function test_stopping_the_address_opts_it_out(): void
    {
        $comment = $this->subscribedComment();

        $this->post($this->relative(CommentReplyEmails::unsubscribeUrl($comment)), ['scope' => 'address'])
            ->assertOk()
            ->assertSee('All comment emails stopped');

        $this->assertTrue(CommentEmailOptOut::covers('reader@example.com'));
        $this->assertFalse($comment->fresh()->notify_replies);
    }

    public function test_an_unwanted_confirmation_can_opt_the_address_out_without_confirming(): void
    {
        $comment = $this->pendingComment(['email' => 'victim@example.com']);

        $this->post($this->relative(CommentReplyEmails::unsubscribeUrl($comment)), ['scope' => 'address'])
            ->assertOk()
            ->assertSee('To turn them back on');

        $this->assertTrue(CommentEmailOptOut::covers('victim@example.com'));
        $this->assertNull($comment->fresh()->notify_replies_confirmed_at);
    }

    public function test_a_one_click_post_unsubscribes_the_comment(): void
    {
        $comment = $this->subscribedComment();

        $this->post($this->relative(CommentReplyEmails::unsubscribeUrl($comment)), ['List-Unsubscribe' => 'One-Click'])
            ->assertOk();

        $this->assertFalse($comment->fresh()->notify_replies);
        $this->assertFalse(CommentEmailOptOut::covers('reader@example.com'));
    }

    public function test_the_one_click_path_is_exempt_from_csrf_and_confirm_is_not(): void
    {
        $middleware = $this->app->make(PreventRequestForgery::class);
        $inExceptArray = new \ReflectionMethod($middleware, 'inExceptArray');

        $this->assertTrue($inExceptArray->invoke($middleware, Request::create('/comments/12/reply-emails/unsubscribe?signature=abc', 'POST')));
        $this->assertFalse($inExceptArray->invoke($middleware, Request::create('/comments/12/reply-emails/confirm?signature=abc', 'POST')));
    }

    public function test_a_forged_unsubscribe_is_refused(): void
    {
        $comment = $this->subscribedComment();

        $this->get('/comments/'.$comment->id.'/reply-emails/unsubscribe')->assertForbidden();
        $this->post('/comments/'.$comment->id.'/reply-emails/unsubscribe?signature=deadbeef', ['scope' => 'address'])->assertForbidden();

        $this->assertTrue($comment->fresh()->hasActiveReplySubscription());
        $this->assertFalse(CommentEmailOptOut::covers('reader@example.com'));
    }

    public function test_an_unknown_scope_is_rejected(): void
    {
        $comment = $this->subscribedComment();

        $this->post($this->relative(CommentReplyEmails::unsubscribeUrl($comment)), ['scope' => 'everything'])
            ->assertSessionHasErrors('scope');

        $this->assertTrue($comment->fresh()->hasActiveReplySubscription());
    }

    public function test_unsubscribing_works_for_a_deleted_comment(): void
    {
        $comment = $this->subscribedComment();
        $path = $this->relative(CommentReplyEmails::unsubscribeUrl($comment));
        $comment->delete();

        $this->get($path)->assertOk();
        $this->post($path, ['scope' => 'address'])->assertOk();

        $this->assertTrue(CommentEmailOptOut::covers('reader@example.com'));
        $this->assertFalse(Comment::withTrashed()->find($comment->id)->notify_replies);
    }

    public function test_links_taken_from_the_rendered_emails_work_end_to_end(): void
    {
        $comment = $this->pendingComment();

        $confirmationHtml = (new CommentReplyConfirmationMail($comment))->render();
        preg_match('#href="(https?://[^"]+/reply-emails/confirm\?[^"]+)"#', $confirmationHtml, $confirm);

        $this->assertNotEmpty($confirm);
        $this->post($this->relative(html_entity_decode($confirm[1])))->assertOk();
        $this->assertTrue($comment->fresh()->hasActiveReplySubscription());

        $reply = Comment::withoutEvents(fn () => Comment::factory()->anonymous()->forPost($comment->post)->asReply($comment)->create([
            'email' => 'someone@example.com',
            'depth' => 1,
            'approved_at' => now(),
        ]));
        $mail = new CommentReplyMail($reply, $comment->fresh());

        preg_match('#href="(https?://[^"]+/reply-emails/unsubscribe\?[^"]+)"#', $mail->render(), $unsubscribe);
        $this->assertNotEmpty($unsubscribe);
        $this->get($this->relative(html_entity_decode($unsubscribe[1])))->assertOk();

        $oneClick = trim($mail->headers()->text['List-Unsubscribe'], '<>');
        $this->post($this->relative($oneClick))->assertOk();
        $this->assertFalse($comment->fresh()->notify_replies);
    }
}
