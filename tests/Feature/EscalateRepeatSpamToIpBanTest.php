<?php

namespace Tests\Feature;

use App\Enums\IpBanSource;
use App\Events\CommentMarkedAsSpam;
use App\Listeners\EscalateRepeatSpamToIpBan;
use App\Models\Comment;
use App\Models\IpBan;
use App\Models\User;
use BSPDX\Keystone\Models\KeystonePermission as Permission;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class EscalateRepeatSpamToIpBanTest extends TestCase
{
    use DatabaseTransactions;

    private const IP = '203.0.113.77';

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        config([
            'comments.spam_escalation' => ['enabled' => true, 'threshold' => 3, 'window_hours' => 24],
        ]);
    }

    private function spamFrom(string $ip, int $count, ?\DateTimeInterface $createdAt = null): Comment
    {
        $last = null;

        for ($i = 0; $i < $count; $i++) {
            $last = Comment::factory()->create([
                'ip_address' => $ip,
                'is_spam' => true,
                'approved_at' => null,
                'created_at' => $createdAt ?? now(),
            ]);
        }

        return $last;
    }

    private function handle(Comment $comment): void
    {
        app(EscalateRepeatSpamToIpBan::class)->handle(new CommentMarkedAsSpam($comment));
    }

    public function test_reaching_the_threshold_creates_an_ip_ban(): void
    {
        $comment = $this->spamFrom(self::IP, 3);

        $this->handle($comment);

        $ban = IpBan::where('ip', self::IP)->sole();
        $this->assertSame(IpBanSource::SpamEscalation, $ban->source);
        $this->assertSame('ESCALATION', $ban->banned_method);
        $this->assertStringContainsString('3 spam comments', $ban->banned_body);
    }

    public function test_below_the_threshold_creates_no_ban(): void
    {
        $this->handle($this->spamFrom(self::IP, 2));

        $this->assertDatabaseMissing('ip_ban', ['ip' => self::IP]);
    }

    public function test_spam_outside_the_window_does_not_count(): void
    {
        $this->spamFrom(self::IP, 2, now()->subHours(48));
        $comment = $this->spamFrom(self::IP, 1);

        $this->handle($comment);

        $this->assertDatabaseMissing('ip_ban', ['ip' => self::IP]);
    }

    public function test_an_already_banned_ip_is_not_banned_again(): void
    {
        IpBan::factory()->create(['ip' => self::IP]);

        $this->handle($this->spamFrom(self::IP, 3));

        $this->assertSame(1, IpBan::where('ip', self::IP)->count());
    }

    public function test_disabled_escalation_creates_no_ban(): void
    {
        config(['comments.spam_escalation.enabled' => false]);

        $this->handle($this->spamFrom(self::IP, 3));

        $this->assertDatabaseMissing('ip_ban', ['ip' => self::IP]);
    }

    public function test_a_comment_without_an_ip_is_ignored(): void
    {
        $comment = Comment::factory()->create(['ip_address' => null, 'is_spam' => true]);
        $before = IpBan::count();

        $this->handle($comment);

        $this->assertSame($before, IpBan::count());
    }

    public function test_the_threshold_comes_from_configuration(): void
    {
        config(['comments.spam_escalation.threshold' => 1]);

        $this->handle($this->spamFrom(self::IP, 1));

        $this->assertDatabaseHas('ip_ban', ['ip' => self::IP]);
    }

    public function test_mark_as_spam_dispatches_the_event_and_mark_as_not_spam_does_not(): void
    {
        Event::fake([CommentMarkedAsSpam::class]);
        $comment = Comment::factory()->create(['is_spam' => false, 'approved_at' => now()]);

        $comment->markAsNotSpam();
        Event::assertNotDispatched(CommentMarkedAsSpam::class);

        $comment->markAsSpam();
        Event::assertDispatched(CommentMarkedAsSpam::class, fn ($e) => $e->comment->is($comment) && $e->comment->is_spam);
    }

    public function test_moderating_three_comments_as_spam_bans_the_ip_end_to_end(): void
    {
        $moderator = User::factory()->create();
        Permission::firstOrCreate(['name' => 'manage-blog']);
        $moderator->givePermissionTo('manage-blog');

        $comments = Comment::factory()->count(3)->create(['ip_address' => self::IP, 'is_spam' => false, 'approved_at' => now()]);

        foreach ($comments as $comment) {
            $this->actingAs($moderator)->post(route('admin.comments.spam', $comment))->assertRedirect();
        }

        $this->assertDatabaseHas('ip_ban', ['ip' => self::IP, 'source' => 'spam_escalation']);
    }
}
