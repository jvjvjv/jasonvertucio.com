<?php

namespace Tests\Feature\Media;

use App\Models\LocalMedia;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class WebhookAuthenticationTest extends TestCase
{
    use DatabaseTransactions;

    private const SECRET = 'test-webhook-secret';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.jellyfin.webhook_secret' => self::SECRET]);
    }

    private function payload(): array
    {
        return [
            'NotificationType' => 'PlaybackStart',
            'ItemId' => 'item-auth-test',
            'ItemType' => 'Movie',
            'Name' => 'Auth Test Movie',
        ];
    }

    public function test_correct_secret_is_processed(): void
    {
        $this->postJson('/api/event/@2028', $this->payload(), ['X-Webhook-Secret' => self::SECRET])
            ->assertOk();

        $this->assertDatabaseHas('local_media', ['jellyfin_item_id' => 'item-auth-test']);
    }

    public function test_missing_secret_is_refused(): void
    {
        $this->postJson('/api/event/@2028', $this->payload())->assertUnauthorized();

        $this->assertDatabaseMissing('local_media', ['jellyfin_item_id' => 'item-auth-test']);
    }

    public function test_wrong_secret_is_refused(): void
    {
        $this->postJson('/api/event/@2028', $this->payload(), ['X-Webhook-Secret' => 'nope'])
            ->assertUnauthorized();

        $this->assertDatabaseMissing('local_media', ['jellyfin_item_id' => 'item-auth-test']);
    }

    public function test_unconfigured_secret_refuses_everything(): void
    {
        config(['services.jellyfin.webhook_secret' => null]);

        $this->postJson('/api/event/@2028', $this->payload(), ['X-Webhook-Secret' => ''])
            ->assertUnauthorized();
        $this->postJson('/api/event/@2028', $this->payload())->assertUnauthorized();

        $this->assertSame(0, LocalMedia::where('jellyfin_item_id', 'item-auth-test')->count());
    }

    public function test_webhook_is_rate_limited_per_address(): void
    {
        for ($i = 0; $i < 120; $i++) {
            $this->postJson('/api/event/@2028', $this->payload(), ['X-Webhook-Secret' => self::SECRET])
                ->assertOk();
        }

        $this->postJson('/api/event/@2028', $this->payload(), ['X-Webhook-Secret' => self::SECRET])
            ->assertStatus(429);
    }

    public function test_read_endpoints_are_rate_limited(): void
    {

        for ($i = 0; $i < 60; $i++) {
            $this->getJson('/api/currently-watching')->assertOk();
        }

        $this->getJson('/api/currently-watching')->assertStatus(429);
    }
}
