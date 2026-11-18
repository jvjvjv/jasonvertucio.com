<?php

namespace Tests\Feature;

use App\Jobs\NotifyCommentReplyRecipient;
use App\Mail\CommentReceivedMail;
use App\Mail\CommentReplyConfirmationMail;
use App\Mail\CommentReplyMail;
use App\Models\Comment;
use App\Models\CommentEmailOptOut;
use App\Models\User;
use App\Support\ClientAddress;
use App\Support\CommentReplyEmails;
use Canvas\Models\Post;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Comment emails compare mailboxes, not strings, so spellings of one inbox
 * cannot each get their own cap or slip past an opt-out; and confirmation
 * emails are also capped per client IP.
 */
class CommentEmailMailboxTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        config()->set('comments.owner_email', 'owner@example.com');
        config()->set('comments.reply_notifications.enabled', true);
    }

    private function makePost(): Post
    {
        return Post::create([
            'id' => (string) Str::uuid(),
            'title' => 'On mailboxes',
            'slug' => 'on-mailboxes-'.Str::random(8),
            'summary' => 'Summary',
            'body' => 'Body',
            'published_at' => now()->subMinute(),
            'user_id' => User::factory()->create()->id,
        ]);
    }

    /**
     * A subscribed comment at the given address and a reply to it, created
     * without running the observer.
     *
     * @return array{0: Comment, 1: Comment}
     */
    private function subscribedWithReply(string $parentEmail, string $replyEmail = 'replier@example.com'): array
    {
        $post = $this->makePost();

        $parent = Comment::withoutEvents(fn () => Comment::factory()->anonymous()->forPost($post)->subscribedToReplies()->create([
            'email' => $parentEmail,
            'approved_at' => now(),
        ]));

        $reply = Comment::withoutEvents(fn () => Comment::factory()->anonymous()->forPost($post)->asReply($parent)->create([
            'email' => $replyEmail,
            'depth' => 1,
            'approved_at' => now(),
        ]));

        return [$parent, $reply];
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function mailboxKeys(): array
    {
        return [
            'case and whitespace' => ['  Name@Example.COM ', 'name@example.com'],
            'plus tag' => ['name+blog@example.com', 'name@example.com'],
            'plus tag with dots kept outside gmail' => ['first.last+x@example.com', 'first.last@example.com'],
            'gmail dots' => ['f.i.r.s.t.last@gmail.com', 'firstlast@gmail.com'],
            'googlemail folds into gmail' => ['First.Last+news@GoogleMail.com', 'firstlast@gmail.com'],
            'last at sign splits' => ['"odd@local"+x@example.com', '"odd@local"@example.com'],
            'empty local part kept' => ['+tag@example.com', '+tag@example.com'],
            'no at sign' => ['Not-An-Address', 'not-an-address'],
            'fullwidth domain letters' => ['victim@ｇｍａｉｌ.com', 'victim@gmail.com'],
            'ideographic full stop in the domain' => ['vic.tim@gmail。com', 'victim@gmail.com'],
            'internationalised domain' => ['Name@Bücher.de', 'name@xn--bcher-kva.de'],
            'domain idna cannot convert kept as given' => ['name@-bad-.com', 'name@-bad-.com'],
            'trailing fullwidth full stop' => ['victim@gmail.com．', 'victim@gmail.com'],
            'trailing ideographic full stop on googlemail' => ['A.B@googlemail.com。', 'ab@gmail.com'],
            'trailing ascii dot' => ['name@example.com.', 'name@example.com'],
            'tag-only local part keeps its tag but folds the domain' => ['+a@GoogleMail.com', '+a@gmail.com'],
        ];
    }

    #[DataProvider('mailboxKeys')]
    public function test_the_mailbox_key(string $address, string $expected): void
    {
        $this->assertSame($expected, CommentEmailOptOut::normalize($address));
    }

    public function test_dots_matter_outside_gmail(): void
    {
        $this->assertFalse(CommentReplyEmails::sameAddress('first.last@example.com', 'firstlast@example.com'));
        $this->assertTrue(CommentReplyEmails::sameAddress('first.last@googlemail.com', 'firstlast@gmail.com'));
    }

    public function test_an_opt_out_keeps_the_address_and_its_mailbox_and_covers_every_spelling(): void
    {
        $optOut = CommentEmailOptOut::optOut('Name+Blog@Example.com')->fresh();

        $this->assertSame('name+blog@example.com', $optOut->email);
        $this->assertSame('name@example.com', $optOut->mailbox);
        $this->assertTrue(CommentEmailOptOut::covers('name@example.com'));
        $this->assertTrue(CommentEmailOptOut::covers('name+other@example.com'));
        $this->assertFalse(CommentEmailOptOut::covers('names@example.com'));
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function addressKeys(): array
    {
        return [
            'tag kept on an unknown domain' => ['Bob+X@Corp.Example', 'bob+x@corp.example'],
            'gmail tag and dots dropped' => ['First.Last+news@GoogleMail.com', 'firstlast@gmail.com'],
            'outlook tag dropped, dots kept' => ['first.last+news@outlook.com', 'first.last@outlook.com'],
            'icloud tag dropped' => ['name+x@iCloud.com', 'name@icloud.com'],
            'fullwidth domain still converted' => ['victim+1@ｇmail.com', 'victim@gmail.com'],
            'tag-only local part kept' => ['+a@gmail.com', '+a@gmail.com'],
        ];
    }

    #[DataProvider('addressKeys')]
    public function test_the_address_key(string $address, string $expected): void
    {
        $this->assertSame($expected, CommentEmailOptOut::addressKey($address));
    }

    public function test_an_opt_out_from_another_spelling_is_told_apart_from_the_addresss_own(): void
    {
        CommentEmailOptOut::optOut('bob+x@corp.example');

        $this->assertTrue(CommentEmailOptOut::coveredByAnotherAddress('bob@corp.example'));
        $this->assertFalse(CommentEmailOptOut::coveredByAnotherAddress('bob+x@corp.example'));
        $this->assertFalse(CommentEmailOptOut::coveredByAnotherAddress('alice@corp.example'));
        $this->assertFalse(CommentEmailOptOut::coveredByAnotherAddress(null));
    }

    public function test_lifting_removes_only_the_addresss_own_opt_out(): void
    {
        CommentEmailOptOut::optOut('bob@corp.example');
        CommentEmailOptOut::optOut('bob+x@corp.example');

        CommentEmailOptOut::lift('bob+x@corp.example');

        $this->assertFalse(CommentEmailOptOut::query()->where('email', 'bob+x@corp.example')->exists());
        $this->assertTrue(CommentEmailOptOut::query()->where('email', 'bob@corp.example')->exists());
        $this->assertTrue(CommentEmailOptOut::covers('bob+x@corp.example'));
    }

    public function test_an_opt_out_covers_a_fullwidth_spelling_of_the_domain(): void
    {
        CommentEmailOptOut::optOut('victim@gmail.com');

        $this->assertTrue(CommentEmailOptOut::covers('victim@ｇmail.com'));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function addressesThatHideTheirMailbox(): array
    {
        return [
            'quoted local part' => ['"vic.tim"@gmail.com'],
            'comment in the local part' => ['victim(c)@gmail.com'],
            'ideographic full stop in the domain' => ['victim@gmail。com'],
        ];
    }

    #[DataProvider('addressesThatHideTheirMailbox')]
    public function test_a_comment_cannot_use_an_address_whose_mailbox_is_obscured(string $address): void
    {
        $post = $this->makePost();

        $this->post(route('comments.store', $post->slug), [
            'name' => 'Ada',
            'email' => $address,
            'message' => 'Hello',
            'notify_replies' => '1',
        ])->assertSessionHasErrors('email');

        $this->assertFalse(Comment::query()->where('post_id', $post->id)->exists());
        Mail::assertNothingQueued();
    }

    public function test_a_comment_accepts_tagged_dotted_and_international_addresses(): void
    {
        $post = $this->makePost();

        foreach (['first.last+blog@gmail.com', 'name@bücher.de'] as $address) {
            $this->post(route('comments.store', $post->slug), [
                'name' => 'Ada',
                'email' => $address,
                'message' => 'Hello',
                'notify_replies' => '1',
            ])->assertSessionHasNoErrors();
        }

        $this->assertSame(2, Comment::query()->where('post_id', $post->id)->count());
        Mail::assertQueued(CommentReplyConfirmationMail::class, fn (CommentReplyConfirmationMail $mail): bool => $mail->hasTo('first.last+blog@gmail.com'));
        Mail::assertQueued(CommentReplyConfirmationMail::class, fn (CommentReplyConfirmationMail $mail): bool => $mail->hasTo('name@bücher.de'));
    }

    public function test_an_opt_out_stops_reply_emails_to_another_spelling(): void
    {
        CommentEmailOptOut::optOut('name@example.com');
        [, $reply] = $this->subscribedWithReply('name+blog@example.com');

        (new NotifyCommentReplyRecipient($reply->id))->handle();

        Mail::assertNotSent(CommentReplyMail::class);
    }

    public function test_a_gmail_opt_out_stops_a_dotted_googlemail_spelling(): void
    {
        CommentEmailOptOut::optOut('firstlast@gmail.com');
        [, $reply] = $this->subscribedWithReply('First.Last@googlemail.com');

        (new NotifyCommentReplyRecipient($reply->id))->handle();

        Mail::assertNotSent(CommentReplyMail::class);
    }

    public function test_spellings_of_one_mailbox_share_the_daily_confirmation_cap(): void
    {
        config()->set('comments.reply_notifications.confirmations_per_address_per_day', 2);
        config()->set('comments.reply_notifications.confirmations_per_ip_per_day', 100);

        foreach (['victim@gmail.com', 'v.ictim@gmail.com', 'victim+1@gmail.com', 'Vic.tim+2@googlemail.com'] as $i => $address) {
            Comment::factory()->anonymous()->wantsReplyEmails()->create([
                'email' => $address,
                'ip_address' => "198.51.100.{$i}",
            ]);
        }

        Mail::assertQueued(CommentReplyConfirmationMail::class, 2);
    }

    public function test_spellings_of_one_mailbox_share_the_hourly_reply_cap(): void
    {
        config()->set('comments.reply_notifications.notifications_per_address_per_hour', 1);
        [, $first] = $this->subscribedWithReply('name@example.com');
        [, $second] = $this->subscribedWithReply('name+blog@example.com');

        (new NotifyCommentReplyRecipient($first->id))->handle();
        (new NotifyCommentReplyRecipient($second->id))->handle();

        Mail::assertSent(CommentReplyMail::class, 1);
    }

    public function test_a_self_reply_from_another_spelling_is_withheld(): void
    {
        [, $reply] = $this->subscribedWithReply('firstlast@gmail.com', 'first.last+again@googlemail.com');

        (new NotifyCommentReplyRecipient($reply->id))->handle();

        Mail::assertNotSent(CommentReplyMail::class);
    }

    public function test_the_owner_is_not_told_about_their_own_comment_from_a_tagged_address(): void
    {
        Comment::factory()->anonymous()->create(['email' => 'Owner+blog@example.com']);
        Comment::factory()->anonymous()->create(['email' => 'reader@example.com']);

        Mail::assertQueued(CommentReceivedMail::class, 1);
    }

    public function test_the_owners_tagged_address_counts_as_the_owner(): void
    {
        [, $reply] = $this->subscribedWithReply('owner+blog@example.com');

        (new NotifyCommentReplyRecipient($reply->id))->handle();

        Mail::assertNotSent(CommentReplyMail::class);
    }

    public function test_mail_goes_to_the_address_as_entered(): void
    {
        $comment = Comment::factory()->anonymous()->wantsReplyEmails()->create(['email' => 'Name+Blog@Example.com']);
        [, $reply] = $this->subscribedWithReply('First.Last+news@googlemail.com');

        (new NotifyCommentReplyRecipient($reply->id))->handle();

        Mail::assertQueued(CommentReplyConfirmationMail::class, fn (CommentReplyConfirmationMail $mail): bool => $mail->hasTo('Name+Blog@Example.com') && $mail->comment->is($comment));
        Mail::assertSent(CommentReplyMail::class, fn (CommentReplyMail $mail): bool => $mail->hasTo('First.Last+news@googlemail.com'));
    }

    private function pendingComment(string $email): Comment
    {
        return Comment::withoutEvents(fn () => Comment::factory()->anonymous()->forPost($this->makePost())->wantsReplyEmails()->create([
            'email' => $email,
            'approved_at' => now(),
        ]));
    }

    private function confirm(Comment $comment): TestResponse
    {
        $url = parse_url(CommentReplyEmails::confirmUrl($comment));

        return $this->post($url['path'].'?'.$url['query']);
    }

    public function test_confirming_a_tagged_gmail_spelling_lifts_the_mailboxs_opt_out(): void
    {
        CommentEmailOptOut::optOut('victim@gmail.com');

        $this->confirm($this->pendingComment('Vic.Tim+again@googlemail.com'))
            ->assertOk()
            ->assertSee('Reply emails confirmed')
            ->assertDontSee('won\'t get them yet', false);

        $this->assertFalse(CommentEmailOptOut::covers('victim@gmail.com'));
    }

    public function test_a_tagged_spelling_on_another_domain_cannot_lift_someone_elses_opt_out(): void
    {
        CommentEmailOptOut::optOut('bob@corp.example');

        $this->confirm($this->pendingComment('bob+x@corp.example'))->assertOk();

        $this->assertTrue(CommentEmailOptOut::covers('bob@corp.example'));
        $this->assertTrue(CommentEmailOptOut::query()->where('email', 'bob@corp.example')->exists());
    }

    public function test_confirming_while_another_spelling_is_opted_out_says_so_and_sends_nothing(): void
    {
        CommentEmailOptOut::optOut('bob+x@corp.example');
        $parent = $this->pendingComment('bob@corp.example');

        $this->confirm($parent)
            ->assertOk()
            ->assertSee('Reply emails confirmed')
            ->assertSee('won\'t get them yet', false);

        $this->assertNotNull($parent->fresh()->notify_replies_confirmed_at);
        $this->assertTrue(CommentEmailOptOut::covers('bob@corp.example'));

        $reply = Comment::withoutEvents(fn () => Comment::factory()->anonymous()->forPost($parent->post)->asReply($parent)->create([
            'email' => 'replier@example.com',
            'depth' => 1,
            'approved_at' => now(),
        ]));
        (new NotifyCommentReplyRecipient($reply->id))->handle();

        Mail::assertNotSent(CommentReplyMail::class);
    }

    public function test_the_confirmation_email_promises_a_lift_only_when_confirming_would_lift(): void
    {
        CommentEmailOptOut::optOut('own@corp.example');
        CommentEmailOptOut::optOut('bob+x@corp.example');

        $own = (new CommentReplyConfirmationMail($this->pendingComment('own@corp.example')))->render();
        $other = (new CommentReplyConfirmationMail($this->pendingComment('bob@corp.example')))->render();

        $this->assertStringContainsString('confirming turns them back on', $own);
        $this->assertStringNotContainsString('confirming turns them back on', $other);
    }

    public function test_a_verified_users_tagged_address_cannot_lift_another_spellings_opt_out(): void
    {
        CommentEmailOptOut::optOut('bob@corp.example');
        $user = User::factory()->create(['email' => 'bob+'.Str::random(5).'@corp.example']);

        $this->actingAs($user)
            ->post(route('comments.store', $this->makePost()->slug), ['message' => 'Hello', 'notify_replies' => '1'])
            ->assertRedirect();

        $this->assertTrue(CommentEmailOptOut::covers('bob@corp.example'));
    }

    public function test_one_ip_is_capped_across_many_mailboxes(): void
    {
        config()->set('comments.reply_notifications.confirmations_per_ip_per_day', 3);

        $comments = collect(range(1, 5))->map(fn (int $i): Comment => Comment::factory()->anonymous()->wantsReplyEmails()->create([
            'email' => "person{$i}@example.com",
            'ip_address' => '203.0.113.50',
        ]));

        Mail::assertQueued(CommentReplyConfirmationMail::class, 3);
        $this->assertSame(5, Comment::query()->whereIn('id', $comments->pluck('id'))->count());
        $this->assertTrue($comments->every(fn (Comment $comment): bool => $comment->fresh()->notify_replies));
    }

    public function test_different_ips_have_separate_caps(): void
    {
        config()->set('comments.reply_notifications.confirmations_per_ip_per_day', 1);

        Comment::factory()->anonymous()->wantsReplyEmails()->create(['email' => 'a@example.com', 'ip_address' => '203.0.113.1']);
        Comment::factory()->anonymous()->wantsReplyEmails()->create(['email' => 'b@example.com', 'ip_address' => '203.0.113.2']);

        Mail::assertQueued(CommentReplyConfirmationMail::class, 2);
    }

    public function test_a_blank_ip_shares_one_bucket(): void
    {
        config()->set('comments.reply_notifications.confirmations_per_ip_per_day', 1);

        Comment::factory()->anonymous()->wantsReplyEmails()->create(['email' => 'a@example.com', 'ip_address' => null]);
        Comment::factory()->anonymous()->wantsReplyEmails()->create(['email' => 'b@example.com', 'ip_address' => '']);

        Mail::assertQueued(CommentReplyConfirmationMail::class, 1);
    }

    public function test_send_me_a_new_link_counts_against_the_requesters_ip(): void
    {
        config()->set('comments.reply_notifications.confirmations_per_ip_per_day', 1);
        $first = Comment::withoutEvents(fn () => Comment::factory()->anonymous()->forPost($this->makePost())->wantsReplyEmails()->create(['email' => 'one@example.com', 'approved_at' => now()]));
        $second = Comment::withoutEvents(fn () => Comment::factory()->anonymous()->forPost($this->makePost())->wantsReplyEmails()->create(['email' => 'two@example.com', 'approved_at' => now()]));

        $this->withHeader('CF-Connecting-IP', '192.0.2.77')->post(CommentReplyEmails::resendUrl($first))->assertOk();
        $this->withHeader('CF-Connecting-IP', '192.0.2.77')->post(CommentReplyEmails::resendUrl($second))
            ->assertStatus(429)
            ->assertSee('Too many links today');
        $this->withHeader('CF-Connecting-IP', '192.0.2.78')->post(CommentReplyEmails::resendUrl($second))->assertOk();

        Mail::assertQueued(CommentReplyConfirmationMail::class, 2);
    }

    public function test_a_client_over_its_cap_does_not_spend_the_mailboxs_share(): void
    {
        config()->set('comments.reply_notifications.confirmations_per_ip_per_day', 1);
        config()->set('comments.reply_notifications.confirmations_per_address_per_day', 1);

        Comment::factory()->anonymous()->wantsReplyEmails()->create(['email' => 'attacker@example.com', 'ip_address' => '203.0.113.9']);
        Comment::factory()->anonymous()->wantsReplyEmails()->create(['email' => 'victim@example.com', 'ip_address' => '203.0.113.9']);
        $victimsOwn = Comment::factory()->anonymous()->wantsReplyEmails()->create(['email' => 'victim@example.com', 'ip_address' => '198.51.100.20']);

        Mail::assertQueued(CommentReplyConfirmationMail::class, 2);
        Mail::assertQueued(CommentReplyConfirmationMail::class, fn (CommentReplyConfirmationMail $mail): bool => $mail->comment->is($victimsOwn));
    }

    public function test_send_me_a_new_link_is_refused_once_the_mailbox_has_had_its_share_under_any_spelling(): void
    {
        config()->set('comments.reply_notifications.confirmations_per_address_per_day', 1);
        config()->set('comments.reply_notifications.confirmations_per_ip_per_day', 100);
        $first = Comment::withoutEvents(fn () => Comment::factory()->anonymous()->forPost($this->makePost())->wantsReplyEmails()->create(['email' => 'victim+1@gmail.com', 'approved_at' => now()]));
        $second = Comment::withoutEvents(fn () => Comment::factory()->anonymous()->forPost($this->makePost())->wantsReplyEmails()->create(['email' => 'V.ictim@googlemail.com', 'approved_at' => now()]));

        $this->withHeader('CF-Connecting-IP', '192.0.2.10')->post(CommentReplyEmails::resendUrl($first))->assertOk();
        $this->withHeader('CF-Connecting-IP', '192.0.2.11')->post(CommentReplyEmails::resendUrl($second))
            ->assertStatus(429)
            ->assertSee('Too many links today');

        Mail::assertQueued(CommentReplyConfirmationMail::class, 1);
    }

    /**
     * @return array<string, array{0: string|null, 1: string}>
     */
    public static function cloudflareHeaders(): array
    {
        return [
            'valid IPv4 header' => ['203.0.113.7', '203.0.113.7'],
            'valid IPv6 header' => ['2001:db8::1', '2001:db8::1'],
            'long-form IPv6 header is canonicalised' => ['2001:0DB8:0:0:0:0:0:1', '2001:db8::1'],
            'header padded with spaces' => [' 203.0.113.7 ', '203.0.113.7'],
            'no header' => [null, '10.0.0.5'],
            'empty header' => ['', '10.0.0.5'],
            'blank header' => ['   ', '10.0.0.5'],
            'header that is not an IP' => ['not-an-ip', '10.0.0.5'],
        ];
    }

    #[DataProvider('cloudflareHeaders')]
    public function test_the_client_address(?string $header, string $expected): void
    {
        $server = ['REMOTE_ADDR' => '10.0.0.5'];

        if ($header !== null) {
            $server['HTTP_CF_CONNECTING_IP'] = $header;
        }

        $this->assertSame($expected, ClientAddress::from(Request::create('/', 'GET', server: $server)));
    }

    public function test_an_empty_cloudflare_header_is_counted_against_the_connecting_ip(): void
    {
        config()->set('comments.reply_notifications.confirmations_per_ip_per_day', 1);
        $first = Comment::withoutEvents(fn () => Comment::factory()->anonymous()->forPost($this->makePost())->wantsReplyEmails()->create(['email' => 'one@example.com', 'approved_at' => now()]));
        $second = Comment::withoutEvents(fn () => Comment::factory()->anonymous()->forPost($this->makePost())->wantsReplyEmails()->create(['email' => 'two@example.com', 'approved_at' => now()]));

        $this->withHeader('CF-Connecting-IP', '')
            ->withServerVariables(['REMOTE_ADDR' => '198.51.100.40'])
            ->post(CommentReplyEmails::resendUrl($first))
            ->assertOk();
        $this->withHeader('CF-Connecting-IP', '')
            ->withServerVariables(['REMOTE_ADDR' => '198.51.100.41'])
            ->post(CommentReplyEmails::resendUrl($second))
            ->assertOk();

        Mail::assertQueued(CommentReplyConfirmationMail::class, 2);
    }

    public function test_a_comment_records_the_connecting_ip_when_the_cloudflare_header_is_not_an_ip(): void
    {
        $post = $this->makePost();

        $this->withHeader('CF-Connecting-IP', 'not-an-ip')
            ->withServerVariables(['REMOTE_ADDR' => '198.51.100.42'])
            ->post(route('comments.store', $post->slug), ['name' => 'Ada', 'email' => 'ada@example.com', 'message' => 'Hello'])
            ->assertSessionHasNoErrors();

        $this->assertSame('198.51.100.42', Comment::query()->where('post_id', $post->id)->firstOrFail()->ip_address);
    }

    public function test_a_signed_in_users_quoted_account_address_gets_no_confirmation(): void
    {
        $post = $this->makePost();
        $user = User::factory()->unverified()->create(['email' => '"vic.tim'.Str::random(4).'"@gmail.com']);

        $this->actingAs($user)
            ->post(route('comments.store', $post->slug), ['message' => 'Hello', 'notify_replies' => '1'])
            ->assertRedirect();

        $comment = Comment::query()->where('post_id', $post->id)->firstOrFail();

        $this->assertTrue($comment->notify_replies);
        $this->assertNull($comment->notify_replies_confirmed_at);
        Mail::assertNotQueued(CommentReplyConfirmationMail::class);
    }

    public function test_a_send_the_mailbox_cap_refuses_still_counts_against_the_client(): void
    {
        config()->set('comments.reply_notifications.confirmations_per_address_per_day', 1);
        config()->set('comments.reply_notifications.confirmations_per_ip_per_day', 2);

        foreach (['victim@example.com', 'victim@example.com', 'other@example.com'] as $address) {
            Comment::factory()->anonymous()->wantsReplyEmails()->create(['email' => $address, 'ip_address' => '203.0.113.60']);
        }

        Mail::assertQueued(CommentReplyConfirmationMail::class, 1);
        Mail::assertQueued(CommentReplyConfirmationMail::class, fn (CommentReplyConfirmationMail $mail): bool => $mail->hasTo('victim@example.com'));
    }

    public function test_a_cap_whose_lock_cannot_be_had_refuses_without_counting(): void
    {
        $key = 'comment-reply-confirm:'.Str::random(12);
        $lock = Cache::lock('lock:'.$key, 10);
        $this->assertTrue($lock->get());

        try {
            $this->assertFalse(CommentReplyEmails::takeFromCap($key, 5, 60));
            $this->assertSame(0, RateLimiter::attempts($key));
        } finally {
            $lock->release();
        }

        $this->assertTrue(CommentReplyEmails::takeFromCap($key, 5, 60));
        $this->assertSame(1, RateLimiter::attempts($key));
    }
}
