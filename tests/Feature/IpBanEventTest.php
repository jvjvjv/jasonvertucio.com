<?php

namespace Tests\Feature;

use App\Enums\IpBanSource;
use App\Events\IpBanned;
use App\Listeners\SendIpBanAlert;
use App\Models\Comment;
use App\Models\IpBan;
use App\Models\User;
use BSPDX\Keystone\Models\KeystonePermission as Permission;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class IpBanEventTest extends TestCase
{
    use DatabaseTransactions;

    private const IP = '203.0.113.88';

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        config(['cache.default' => 'array']);
    }

    private function userWith(string ...$permissions): User
    {
        $user = User::factory()->create();

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
            $user->givePermissionTo($permission);
        }

        return $user;
    }

    public function test_creating_an_ip_ban_dispatches_the_event_once(): void
    {
        Event::fake([IpBanned::class]);

        $ban = IpBan::create(['ip' => self::IP, 'banned_method' => 'POST', 'banned_url' => '/x', 'banned_body' => '{}']);

        Event::assertDispatchedTimes(IpBanned::class, 1);
        Event::assertDispatched(IpBanned::class, fn (IpBanned $e) => $e->ip === self::IP
            && $e->source === IpBanSource::Honeypot
            && $e->ipBan->is($ban));
    }

    public function test_first_or_create_on_an_existing_ip_does_not_fire_again(): void
    {
        IpBan::firstOrCreate(['ip' => self::IP]);
        Event::fake([IpBanned::class]);

        IpBan::firstOrCreate(['ip' => self::IP]);

        Event::assertNotDispatched(IpBanned::class);
    }

    public function test_the_listener_writes_a_structured_audit_entry(): void
    {
        Log::spy();
        $ban = IpBan::factory()->make(['ip' => self::IP, 'source' => IpBanSource::Admin]);

        app(SendIpBanAlert::class)->handle(new IpBanned(self::IP, IpBanSource::Admin, $ban));

        Log::shouldHaveReceived('notice')->once()->withArgs(
            fn (string $message, array $context) => $message === 'IP banned'
                && $context['ip'] === self::IP
                && $context['source'] === 'admin'
                && isset($context['banned_at'])
        );
    }

    public function test_a_real_ban_is_logged_exactly_once(): void
    {
        Log::spy();

        IpBan::create(['ip' => self::IP, 'source' => IpBanSource::Admin]);

        Log::shouldHaveReceived('notice')->once()->with('IP banned', \Mockery::on(fn (array $c) => $c['ip'] === self::IP));
    }

    public function test_creating_or_deleting_a_ban_clears_the_enforcement_cache(): void
    {
        Cache::put('banned_ip_list', [], 300);

        $ban = IpBan::create(['ip' => self::IP]);
        $this->assertFalse(Cache::has('banned_ip_list'));

        Cache::put('banned_ip_list', [self::IP], 300);
        $ban->delete();
        $this->assertFalse(Cache::has('banned_ip_list'));
    }

    public function test_a_moderator_ban_is_enforced_on_the_next_request(): void
    {
        $comment = Comment::factory()->create(['ip_address' => self::IP]);
        Cache::put('banned_ip_list', [], 300);

        $this->actingAs($this->userWith('manage-blog'))
            ->post(route('admin.comments.ban-ip', $comment));

        $this->assertNotContains(self::IP, Cache::get('banned_ip_list', []));
        $this->assertFalse(Cache::has('banned_ip_list'));
    }

    public function test_the_honeypot_still_bans_and_now_fires_the_event(): void
    {
        Event::fake([IpBanned::class]);

        $this->withServerVariables(['REMOTE_ADDR' => self::IP])
            ->post('/wp-login.php', ['log' => 'admin', 'pwd' => 'x'])
            ->assertOk();

        $ban = IpBan::where('ip', self::IP)->sole();
        $this->assertSame(IpBanSource::Honeypot, $ban->source);
        $this->assertSame('POST', $ban->banned_method);
        Event::assertDispatched(IpBanned::class, fn (IpBanned $e) => $e->source === IpBanSource::Honeypot);
    }

    public function test_a_moderator_can_ban_the_ip_behind_a_comment(): void
    {
        Event::fake([IpBanned::class]);
        $comment = Comment::factory()->create(['ip_address' => self::IP]);

        $this->actingAs($this->userWith('manage-blog'))
            ->post(route('admin.comments.ban-ip', $comment))
            ->assertRedirect()
            ->assertSessionHas('success');

        $ban = IpBan::where('ip', self::IP)->sole();
        $this->assertSame(IpBanSource::Admin, $ban->source);
        Event::assertDispatched(IpBanned::class, fn (IpBanned $e) => $e->source === IpBanSource::Admin);
    }

    public function test_a_user_without_manage_blog_is_refused(): void
    {
        $comment = Comment::factory()->create(['ip_address' => self::IP]);

        $this->actingAs($this->userWith('manage-comments'))
            ->post(route('admin.comments.ban-ip', $comment))
            ->assertForbidden();

        $this->assertDatabaseMissing('ip_ban', ['ip' => self::IP]);
    }

    public function test_a_guest_is_refused(): void
    {
        $comment = Comment::factory()->create(['ip_address' => self::IP]);

        $this->post(route('admin.comments.ban-ip', $comment))->assertRedirect();

        $this->assertDatabaseMissing('ip_ban', ['ip' => self::IP]);
    }

    public function test_banning_an_already_banned_ip_is_a_no_op(): void
    {
        IpBan::factory()->create(['ip' => self::IP]);
        Event::fake([IpBanned::class]);
        $comment = Comment::factory()->create(['ip_address' => self::IP]);

        $this->actingAs($this->userWith('manage-blog'))
            ->post(route('admin.comments.ban-ip', $comment))
            ->assertRedirect();

        $this->assertSame(1, IpBan::where('ip', self::IP)->count());
        Event::assertNotDispatched(IpBanned::class);
    }

    public function test_a_comment_without_an_ip_cannot_be_used_to_ban(): void
    {
        $comment = Comment::factory()->create(['ip_address' => null]);
        $before = IpBan::count();

        $this->actingAs($this->userWith('manage-blog'))
            ->post(route('admin.comments.ban-ip', $comment))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame($before, IpBan::count());
    }
}
