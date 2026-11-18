<?php

namespace Tests\Feature;

use App\Jobs\NotifyCommentReplyRecipient;
use App\Mail\CommentReceivedMail;
use App\Mail\CommentReplyConfirmationMail;
use App\Mail\CommentReplyMail;
use App\Models\Comment;
use App\Models\CommentEmailOptOut;
use App\Models\User;
use Canvas\Models\Post;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CommentReplyNotificationTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        RateLimiter::clear('comments');
        config()->set('comments.owner_email', 'owner@example.com');
        config()->set('comments.reply_notifications.enabled', true);
    }

    private function makePost(bool $published = true): Post
    {
        return Post::create([
            'id' => (string) Str::uuid(),
            'title' => 'On threading',
            'slug' => 'on-threading-'.Str::random(8),
            'summary' => 'Summary',
            'body' => 'Body',
            'published_at' => $published ? now()->subMinute() : null,
            'user_id' => User::factory()->create()->id,
        ]);
    }

    /**
     * A subscribed anonymous comment and a reply to it from someone else,
     * created without running the observer's reply dispatch.
     *
     * @return array{0: Comment, 1: Comment}
     */
    private function subscribedCommentWithReply(array $parentAttributes = [], array $replyAttributes = []): array
    {
        $post = $this->makePost();

        $parent = Comment::withoutEvents(fn () => Comment::factory()->anonymous()->forPost($post)->subscribedToReplies()->create(array_merge([
            'email' => 'parent@example.com',
            'approved_at' => now(),
        ], $parentAttributes)));

        $reply = Comment::withoutEvents(fn () => Comment::factory()->anonymous()->forPost($post)->asReply($parent)->create(array_merge([
            'name' => 'Rhea Replier',
            'email' => 'replier@example.com',
            'message' => 'Good point.',
            'depth' => 1,
            'approved_at' => now(),
            'ip_address' => '203.0.113.9',
        ], $replyAttributes)));

        return [$parent, $reply];
    }

    private function runJob(Comment $reply): void
    {
        (new NotifyCommentReplyRecipient($reply->id))->handle();
    }

    public function test_subscription_state_on_the_model(): void
    {
        $active = Comment::factory()->anonymous()->subscribedToReplies()->make();
        $pending = Comment::factory()->anonymous()->wantsReplyEmails()->make();
        $unsubscribed = Comment::factory()->anonymous()->make(['notify_replies' => false, 'notify_replies_confirmed_at' => now()]);

        $this->assertTrue($active->hasActiveReplySubscription());
        $this->assertFalse($pending->hasActiveReplySubscription());
        $this->assertFalse($unsubscribed->hasActiveReplySubscription());
    }

    public function test_the_confirmed_at_column_is_not_mass_assignable(): void
    {
        $comment = new Comment(['notify_replies' => true, 'notify_replies_confirmed_at' => now()]);

        $this->assertTrue($comment->notify_replies);
        $this->assertNull($comment->notify_replies_confirmed_at);
    }

    public function test_a_verified_author_is_reached_at_their_current_address(): void
    {
        $user = User::factory()->create(['email' => 'old-'.Str::random(6).'@example.com']);
        $comment = Comment::factory()->create(['user_id' => $user->id, 'email' => $user->email]);

        $user->update(['email' => 'new-'.Str::random(6).'@example.com']);

        $this->assertSame($user->email, $comment->fresh()->replyNotificationAddress());
    }

    public function test_an_anonymous_author_is_reached_at_the_recorded_address(): void
    {
        $comment = Comment::factory()->anonymous()->make(['email' => 'anon@example.com']);

        $this->assertSame('anon@example.com', $comment->replyNotificationAddress());
    }

    public function test_opt_outs_are_stored_lowercased_and_match_any_case(): void
    {
        $optOut = CommentEmailOptOut::create(['email' => 'Mixed.Case@Example.COM']);

        $this->assertSame('mixed.case@example.com', $optOut->fresh()->email);
        $this->assertTrue(CommentEmailOptOut::covers('MIXED.case@example.com'));
        $this->assertFalse(CommentEmailOptOut::covers('someone-else@example.com'));
        $this->assertFalse(CommentEmailOptOut::covers(null));
    }

    public function test_opting_out_twice_keeps_one_row(): void
    {
        CommentEmailOptOut::optOut('twice@example.com');
        CommentEmailOptOut::optOut('TWICE@example.com');

        $this->assertSame(1, CommentEmailOptOut::query()->where('email', 'twice@example.com')->count());
    }

    public function test_the_choice_renders_unchecked_on_the_main_and_reply_forms(): void
    {
        $post = $this->makePost();
        $comment = Comment::factory()->anonymous()->forPost($post)->create(['approved_at' => now()]);

        $response = $this->get(route('post', $post->slug))->assertOk();

        $response->assertSee('id="notify-replies-root"', false);
        $response->assertSee('id="notify-replies-'.$comment->id.'"', false);
        $response->assertSee('Email me when someone replies');
        $this->assertStringNotContainsString('checked', $this->checkboxMarkup($response->getContent(), 'root'));
        $this->assertStringNotContainsString('checked', $this->checkboxMarkup($response->getContent(), (string) $comment->id));
    }

    public function test_the_choice_is_absent_when_disabled(): void
    {
        config()->set('comments.reply_notifications.enabled', false);
        $post = $this->makePost();

        $this->get(route('post', $post->slug))
            ->assertOk()
            ->assertDontSee('notify_replies', false);
    }

    public function test_a_rejected_submission_keeps_the_choice_checked(): void
    {
        $post = $this->makePost();

        $this->from(route('post', $post->slug))
            ->post(route('comments.store', $post->slug), [
                'name' => 'Ada',
                'email' => 'not-an-address',
                'message' => 'Hello',
                'notify_replies' => '1',
            ])
            ->assertSessionHasErrors('email');

        $content = $this->get(route('post', $post->slug))->getContent();

        $this->assertStringContainsString('checked', $this->checkboxMarkup($content, 'root'));
    }

    public function test_an_anonymous_opt_in_is_pending_and_queues_a_confirmation(): void
    {
        $post = $this->makePost();

        $this->post(route('comments.store', $post->slug), [
            'name' => 'Ada',
            'email' => 'ada@example.com',
            'message' => 'Hello',
            'notify_replies' => '1',
        ])->assertRedirect();

        $comment = Comment::query()->where('post_id', $post->id)->firstOrFail();

        $this->assertTrue($comment->notify_replies);
        $this->assertNull($comment->notify_replies_confirmed_at);
        Mail::assertQueued(CommentReplyConfirmationMail::class, fn (CommentReplyConfirmationMail $mail): bool => $mail->hasTo('ada@example.com') && $mail->comment->is($comment));
    }

    public function test_a_verified_signed_in_opt_in_is_active_and_sends_no_confirmation(): void
    {
        $post = $this->makePost();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('comments.store', $post->slug), ['message' => 'Hello', 'notify_replies' => '1'])
            ->assertRedirect();

        $comment = Comment::query()->where('post_id', $post->id)->firstOrFail();

        $this->assertTrue($comment->hasActiveReplySubscription());
        Mail::assertNotQueued(CommentReplyConfirmationMail::class);
    }

    public function test_not_opting_in_records_nothing_and_sends_nothing(): void
    {
        $post = $this->makePost();

        $this->post(route('comments.store', $post->slug), [
            'name' => 'Ada',
            'email' => 'ada@example.com',
            'message' => 'Hello',
        ])->assertRedirect();

        $comment = Comment::query()->where('post_id', $post->id)->firstOrFail();

        $this->assertFalse($comment->notify_replies);
        $this->assertNull($comment->notify_replies_confirmed_at);
        Mail::assertNotQueued(CommentReplyConfirmationMail::class);
    }

    public function test_an_opt_in_while_disabled_is_stored_as_off(): void
    {
        config()->set('comments.reply_notifications.enabled', false);
        $post = $this->makePost();

        $this->post(route('comments.store', $post->slug), [
            'name' => 'Ada',
            'email' => 'ada@example.com',
            'message' => 'Hello',
            'notify_replies' => '1',
        ])->assertRedirect();

        $this->assertFalse(Comment::query()->where('post_id', $post->id)->firstOrFail()->notify_replies);
        Mail::assertNotQueued(CommentReplyConfirmationMail::class);
    }

    public function test_the_confirmation_mail_is_queueable(): void
    {
        $this->assertInstanceOf(ShouldQueue::class, new CommentReplyConfirmationMail(Comment::factory()->make()));
    }

    public function test_an_opted_out_address_can_opt_back_in(): void
    {
        CommentEmailOptOut::optOut('gone@example.com');

        $comment = Comment::factory()->anonymous()->wantsReplyEmails()->create(['email' => 'Gone@Example.com']);

        Mail::assertQueued(CommentReplyConfirmationMail::class, fn (CommentReplyConfirmationMail $mail): bool => $mail->comment->is($comment));
        $this->assertTrue(CommentEmailOptOut::covers('gone@example.com'));
        $this->assertNull($comment->fresh()->notify_replies_confirmed_at);
    }

    public function test_the_confirmation_for_an_opted_out_address_says_it_turns_emails_back_on(): void
    {
        $comment = Comment::withoutEvents(fn () => Comment::factory()->anonymous()->wantsReplyEmails()->create(['email' => 'back@example.com']));

        (new CommentReplyConfirmationMail($comment))->assertDontSeeInHtml('turns them back on');

        CommentEmailOptOut::optOut('back@example.com');

        $mail = new CommentReplyConfirmationMail($comment);
        $mail->assertSeeInHtml('confirming turns them back on');
        $mail->assertSeeInText('confirming turns them back on');
    }

    public function test_a_verified_signed_in_opt_in_lifts_an_earlier_opt_out(): void
    {
        $post = $this->makePost();
        $user = User::factory()->create(['email' => 'member-'.Str::random(6).'@example.com']);
        CommentEmailOptOut::optOut(strtoupper($user->email));

        $this->actingAs($user)
            ->post(route('comments.store', $post->slug), ['message' => 'Back again', 'notify_replies' => '1'])
            ->assertRedirect();

        $this->assertFalse(CommentEmailOptOut::covers($user->email));
        $this->assertTrue(Comment::query()->where('post_id', $post->id)->firstOrFail()->hasActiveReplySubscription());
    }

    public function test_an_unverified_signed_in_opt_in_must_confirm_by_email(): void
    {
        $post = $this->makePost();
        $user = User::factory()->unverified()->create(['email' => 'unverified-'.Str::random(6).'@example.com']);
        CommentEmailOptOut::optOut($user->email);

        $this->actingAs($user)
            ->post(route('comments.store', $post->slug), ['message' => 'Hello', 'notify_replies' => '1'])
            ->assertRedirect();

        $comment = Comment::query()->where('post_id', $post->id)->firstOrFail();

        $this->assertTrue($comment->notify_replies);
        $this->assertNull($comment->notify_replies_confirmed_at);
        $this->assertTrue(CommentEmailOptOut::covers($user->email));
        Mail::assertQueued(CommentReplyConfirmationMail::class, fn (CommentReplyConfirmationMail $mail): bool => $mail->hasTo($user->email) && $mail->comment->is($comment));
    }

    public function test_an_unverified_signed_in_user_sees_the_confirm_hint(): void
    {
        $post = $this->makePost();

        $this->actingAs(User::factory()->unverified()->create())
            ->get(route('post', $post->slug))
            ->assertSee("We'll send a link to confirm first", false);

        $this->actingAs(User::factory()->create())
            ->get(route('post', $post->slug))
            ->assertDontSee("We'll send a link to confirm first", false);
    }

    public function test_an_unverified_address_change_does_not_redirect_reply_emails(): void
    {
        $user = User::factory()->create(['email' => 'before-'.Str::random(6).'@example.com']);
        [, $reply] = $this->subscribedCommentWithReply(parentAttributes: ['user_id' => $user->id, 'email' => $user->email]);
        $original = $user->email;

        $user->forceFill(['email' => 'after-'.Str::random(6).'@example.com', 'email_verified_at' => null])->save();

        $this->runJob($reply);

        Mail::assertSent(CommentReplyMail::class, fn (CommentReplyMail $mail): bool => $mail->hasTo($original) && ! $mail->hasTo($user->email));
    }

    public function test_a_signed_in_comment_without_the_box_keeps_the_opt_out(): void
    {
        $post = $this->makePost();
        $user = User::factory()->create(['email' => 'quiet-'.Str::random(6).'@example.com']);
        CommentEmailOptOut::optOut($user->email);

        $this->actingAs($user)
            ->post(route('comments.store', $post->slug), ['message' => 'Just commenting'])
            ->assertRedirect();

        $this->assertTrue(CommentEmailOptOut::covers($user->email));
    }

    public function test_lifting_an_opt_out_ignores_case_and_blank_addresses(): void
    {
        CommentEmailOptOut::optOut('lift@example.com');

        CommentEmailOptOut::lift(null);
        $this->assertTrue(CommentEmailOptOut::covers('lift@example.com'));

        CommentEmailOptOut::lift('LIFT@Example.com');
        $this->assertFalse(CommentEmailOptOut::covers('lift@example.com'));
    }

    public function test_confirmations_are_capped_per_address_per_day_ignoring_case(): void
    {
        config()->set('comments.reply_notifications.confirmations_per_address_per_day', 2);

        Comment::factory()->anonymous()->wantsReplyEmails()->create(['email' => 'capped@example.com']);
        Comment::factory()->anonymous()->wantsReplyEmails()->create(['email' => 'CAPPED@example.com']);
        $third = Comment::factory()->anonymous()->wantsReplyEmails()->create(['email' => 'Capped@Example.com']);

        Mail::assertQueued(CommentReplyConfirmationMail::class, 2);
        $this->assertNull($third->fresh()->notify_replies_confirmed_at);
        $this->assertTrue($third->fresh()->notify_replies);
    }

    public function test_the_confirmation_mail_names_the_post_and_carries_both_links(): void
    {
        $post = $this->makePost();
        $comment = Comment::factory()->anonymous()->forPost($post)->wantsReplyEmails()->create(['message' => '[click me](https://evil.example)']);

        $html = (new CommentReplyConfirmationMail($comment))->render();

        $this->assertStringContainsString('On threading', $html);
        $this->assertStringContainsString('/comments/'.$comment->id.'/reply-emails/confirm?expires=', $html);
        $this->assertStringContainsString('/comments/'.$comment->id.'/reply-emails/unsubscribe?signature=', $html);
        $this->assertStringNotContainsString('evil.example', $html);
    }

    public function test_the_confirmation_text_view_carries_both_links(): void
    {
        $post = $this->makePost();
        $comment = Comment::factory()->anonymous()->forPost($post)->wantsReplyEmails()->create(['message' => '[click me](https://evil.example)']);

        $mail = new CommentReplyConfirmationMail($comment);

        $mail->assertSeeInText('On threading');
        $mail->assertSeeInText('/comments/'.$comment->id.'/reply-emails/confirm?expires=');
        $mail->assertSeeInText('/comments/'.$comment->id.'/reply-emails/unsubscribe?signature=');
        $mail->assertDontSeeInText('evil.example');
    }

    public function test_the_reply_mail_contents(): void
    {
        [$parent, $reply] = $this->subscribedCommentWithReply();

        $mail = new CommentReplyMail($reply, $parent);
        $html = $mail->render();

        $this->assertStringContainsString('On threading', $html);
        $this->assertStringContainsString('Rhea Replier', $html);
        $this->assertStringContainsString('Good point.', $html);
        $this->assertStringContainsString(route('post', $parent->post->slug).'#comment-'.$reply->id, $html);
        $this->assertStringNotContainsString('replier@example.com', $html);
        $this->assertStringNotContainsString('203.0.113.9', $html);
        $mail->assertHasSubject('Rhea Replier replied to your comment on On threading');
    }

    public function test_links_written_in_a_reply_are_literal_text(): void
    {
        [$parent, $reply] = $this->subscribedCommentWithReply(replyAttributes: [
            'message' => "See [this](https://evil.example/md)\nor <a href=\"https://evil.example/html\">that</a>",
        ]);

        $html = (new CommentReplyMail($reply, $parent))->render();

        $this->assertStringContainsString('[this](https://evil.example/md)', $html);
        $this->assertStringContainsString('&lt;a href=&quot;https://evil.example/html&quot;&gt;that&lt;/a&gt;', $html);
        $this->assertStringNotContainsString('href="https://evil.example', $html);
    }

    public function test_the_reply_text_view_holds_the_reply_verbatim_and_nothing_private(): void
    {
        [$parent, $reply] = $this->subscribedCommentWithReply(replyAttributes: [
            'message' => "See [this](https://evil.example/md)\nor <a href=\"https://evil.example/html\">that</a>",
        ]);

        $mail = new CommentReplyMail($reply, $parent);

        $mail->assertSeeInText('Rhea Replier replied to your comment on "On threading"', false);
        $mail->assertSeeInText("See [this](https://evil.example/md)\nor <a href=\"https://evil.example/html\">that</a>", false);
        $mail->assertSeeInText(route('post', $parent->post->slug).'#comment-'.$reply->id, false);
        $mail->assertSeeInText('/comments/'.$parent->id.'/reply-emails/unsubscribe?signature=', false);
        $mail->assertDontSeeInText('replier@example.com', false);
        $mail->assertDontSeeInText('203.0.113.9', false);
    }

    public function test_the_reply_mail_carries_one_click_unsubscribe_headers(): void
    {
        [$parent, $reply] = $this->subscribedCommentWithReply();

        $headers = (new CommentReplyMail($reply, $parent))->headers()->text;

        $this->assertSame('List-Unsubscribe=One-Click', $headers['List-Unsubscribe-Post']);
        $this->assertMatchesRegularExpression('#^<https?://[^>]+/comments/'.$parent->id.'/reply-emails/unsubscribe\?signature=[0-9a-f]+>$#', $headers['List-Unsubscribe']);
    }

    public function test_an_active_subscription_is_emailed_about_a_reply(): void
    {
        [$parent, $reply] = $this->subscribedCommentWithReply();

        $this->runJob($reply);

        Mail::assertSent(CommentReplyMail::class, fn (CommentReplyMail $mail): bool => $mail->hasTo('parent@example.com') && $mail->reply->is($reply));
    }

    public function test_a_signed_in_parent_is_emailed_at_the_account_address(): void
    {
        $user = User::factory()->create(['email' => 'account-'.Str::random(6).'@example.com']);
        [, $reply] = $this->subscribedCommentWithReply(parentAttributes: ['user_id' => $user->id, 'email' => 'stale@example.com']);

        $this->runJob($reply);

        Mail::assertSent(CommentReplyMail::class, fn (CommentReplyMail $mail): bool => $mail->hasTo($user->email));
    }

    /**
     * @return array<string, array{0: \Closure(Comment, Comment): void}>
     */
    public static function withheldCases(): array
    {
        return [
            'pending' => [fn (Comment $parent, Comment $reply) => $parent->forceFill(['notify_replies_confirmed_at' => null])->save()],
            'unsubscribed' => [fn (Comment $parent, Comment $reply) => $parent->forceFill(['notify_replies' => false])->save()],
            'reply marked spam' => [fn (Comment $parent, Comment $reply) => $reply->forceFill(['is_spam' => true, 'approved_at' => null])->saveQuietly()],
            'reply deleted' => [fn (Comment $parent, Comment $reply) => $reply->delete()],
            'parent marked spam' => [fn (Comment $parent, Comment $reply) => $parent->forceFill(['is_spam' => true, 'approved_at' => null])->saveQuietly()],
            'parent deleted' => [fn (Comment $parent, Comment $reply) => $parent->delete()],
            'post unpublished' => [fn (Comment $parent, Comment $reply) => $parent->post->forceFill(['published_at' => null])->save()],
            'address opted out' => [fn (Comment $parent, Comment $reply) => CommentEmailOptOut::optOut('PARENT@example.com')],
            'self reply by address' => [fn (Comment $parent, Comment $reply) => $reply->forceFill(['email' => 'Parent@Example.com'])->saveQuietly()],
            'owner as parent' => [fn (Comment $parent, Comment $reply) => $parent->forceFill(['email' => 'Owner@example.com'])->saveQuietly()],
            'feature disabled' => [fn (Comment $parent, Comment $reply) => config()->set('comments.reply_notifications.enabled', false)],
        ];
    }

    /**
     * @param  \Closure(Comment, Comment): void  $arrange
     */
    #[DataProvider('withheldCases')]
    public function test_a_reply_email_is_withheld(\Closure $arrange): void
    {
        [$parent, $reply] = $this->subscribedCommentWithReply();

        $arrange($parent, $reply);

        $this->runJob($reply);

        Mail::assertNotSent(CommentReplyMail::class);
    }

    public function test_a_self_reply_by_the_same_account_is_withheld(): void
    {
        $user = User::factory()->create();
        [, $reply] = $this->subscribedCommentWithReply(
            parentAttributes: ['user_id' => $user->id],
            replyAttributes: ['user_id' => $user->id, 'email' => 'different@example.com'],
        );

        $this->runJob($reply);

        Mail::assertNotSent(CommentReplyMail::class);
    }

    public function test_a_missing_reply_is_a_quiet_no_op(): void
    {
        (new NotifyCommentReplyRecipient(PHP_INT_MAX))->handle();

        Mail::assertNotSent(CommentReplyMail::class);
    }

    public function test_reply_emails_are_capped_per_address_per_hour(): void
    {
        config()->set('comments.reply_notifications.notifications_per_address_per_hour', 2);
        [$parent, $first] = $this->subscribedCommentWithReply();

        $replies = [$first];

        foreach (range(1, 2) as $i) {
            $replies[] = Comment::withoutEvents(fn () => Comment::factory()->anonymous()->forPost($parent->post)->asReply($parent)->create([
                'email' => "other{$i}@example.com",
                'depth' => 1,
                'approved_at' => now(),
            ]));
        }

        foreach ($replies as $reply) {
            $this->runJob($reply);
        }

        Mail::assertSent(CommentReplyMail::class, 2);
        $this->assertSame(3, Comment::query()->where('parent_id', $parent->id)->count());
    }

    public function test_the_hourly_cap_is_shared_across_letter_case(): void
    {
        config()->set('comments.reply_notifications.notifications_per_address_per_hour', 1);
        [, $first] = $this->subscribedCommentWithReply(parentAttributes: ['email' => 'shared@example.com']);
        [, $second] = $this->subscribedCommentWithReply(parentAttributes: ['email' => 'SHARED@Example.com']);

        $this->runJob($first);
        $this->runJob($second);

        Mail::assertSent(CommentReplyMail::class, 1);
    }

    public function test_a_reply_dispatches_the_job_with_the_configured_delay(): void
    {
        Queue::fake();
        config()->set('comments.reply_notifications.delay_minutes', 10);
        $this->freezeTime();

        $parent = Comment::factory()->anonymous()->create(['approved_at' => now()]);
        $reply = Comment::factory()->anonymous()->asReply($parent)->create(['depth' => 1, 'approved_at' => now()]);

        Queue::assertPushed(NotifyCommentReplyRecipient::class, function (NotifyCommentReplyRecipient $job) use ($reply): bool {
            return $job->replyId === $reply->id
                && $job->delay instanceof \DateTimeInterface
                && $job->delay->getTimestamp() === now()->addMinutes(10)->getTimestamp();
        });
        Queue::assertPushed(NotifyCommentReplyRecipient::class, 1);
    }

    public function test_a_top_level_comment_dispatches_no_job(): void
    {
        Queue::fake();

        Comment::factory()->anonymous()->subscribedToReplies()->create();

        Queue::assertNotPushed(NotifyCommentReplyRecipient::class);
    }

    public function test_the_owner_notification_is_unaffected(): void
    {
        Queue::fake();
        config()->set('comments.notification_email', 'inbox@example.com');

        $parent = Comment::factory()->anonymous()->create(['approved_at' => now()]);
        Comment::factory()->anonymous()->asReply($parent)->create(['depth' => 1, 'approved_at' => now()]);

        Mail::assertQueued(CommentReceivedMail::class, 2);
    }

    public function test_a_reply_to_the_subscribed_owner_notifies_the_owner_only_as_before(): void
    {
        config()->set('comments.notification_email', 'inbox@example.com');
        $post = $this->makePost();
        $ownersComment = Comment::withoutEvents(fn () => Comment::factory()->anonymous()->forPost($post)->subscribedToReplies()->create([
            'email' => 'owner@example.com',
            'approved_at' => now(),
        ]));

        $this->post(route('comments.store', $post->slug), [
            'name' => 'Visitor',
            'email' => 'visitor@example.com',
            'message' => 'Replying to the owner',
            'parent_id' => $ownersComment->id,
        ])->assertRedirect();

        Mail::assertQueued(CommentReceivedMail::class, fn (CommentReceivedMail $mail): bool => $mail->hasTo('inbox@example.com'));
        Mail::assertNotSent(CommentReplyMail::class);
    }

    public function test_only_the_direct_parent_is_emailed_end_to_end(): void
    {
        $post = $this->makePost();
        $grandparent = Comment::factory()->anonymous()->forPost($post)->subscribedToReplies()->create([
            'email' => 'grandparent@example.com',
            'approved_at' => now(),
        ]);
        $parent = Comment::factory()->anonymous()->forPost($post)->asReply($grandparent)->subscribedToReplies()->create([
            'email' => 'middle@example.com',
            'depth' => 1,
            'approved_at' => now(),
        ]);
        Mail::fake();

        $this->post(route('comments.store', $post->slug), [
            'name' => 'Deep',
            'email' => 'deep@example.com',
            'message' => 'Going deeper',
            'parent_id' => $parent->id,
        ])->assertRedirect();

        Mail::assertSent(CommentReplyMail::class, 1);
        Mail::assertSent(CommentReplyMail::class, fn (CommentReplyMail $mail): bool => $mail->hasTo('middle@example.com'));
    }

    public function test_a_top_level_comment_emails_no_commenter(): void
    {
        $post = $this->makePost();
        Comment::factory()->anonymous()->forPost($post)->subscribedToReplies()->create(['approved_at' => now()]);

        $this->post(route('comments.store', $post->slug), [
            'name' => 'Top',
            'email' => 'top@example.com',
            'message' => 'New thread',
        ])->assertRedirect();

        Mail::assertNotSent(CommentReplyMail::class);
    }

    public function test_a_disabled_feature_keeps_existing_subscriptions(): void
    {
        config()->set('comments.reply_notifications.enabled', false);
        [$parent, $reply] = $this->subscribedCommentWithReply();

        $this->runJob($reply);

        Mail::assertNotSent(CommentReplyMail::class);
        $this->assertTrue($parent->fresh()->hasActiveReplySubscription());
    }

    /**
     * The opening tag of a form's reply-email checkbox.
     */
    private function checkboxMarkup(string $html, string $suffix): string
    {
        preg_match('/<input type="checkbox"\s+id="notify-replies-'.preg_quote($suffix, '/').'"[^>]*>/', $html, $matches);

        $this->assertNotEmpty($matches, "No reply-email checkbox for form {$suffix}.");

        return $matches[0];
    }
}
