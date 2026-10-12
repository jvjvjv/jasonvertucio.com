<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class PostsIngestTest extends TestCase
{
    private function expectLog(): void
    {
        $channel = \Mockery::mock();
        Log::shouldReceive('channel')->with('posts')->andReturn($channel)->byDefault();

        $this->logged = [];
        $channel->shouldReceive('info')->andReturnUsing(function (string $message, array $context = []): void {
            $this->logged[] = $context;
        });
    }

    /** @var array<int, array<string, mixed>> */
    private array $logged = [];

    public function test_a_normal_request_is_accepted_and_logged_once(): void
    {
        $this->expectLog();

        $this->postJson('/posts', ['hello' => 'world'])->assertNoContent();

        $this->assertCount(1, $this->logged);
        $this->assertSame(['hello' => 'world'], $this->logged[0]);
    }

    public function test_a_body_over_the_cap_is_refused_and_not_logged(): void
    {
        $this->expectLog();
        config(['posts-ingest.max_body_bytes' => 100]);

        $this->postJson('/posts', ['blob' => str_repeat('x', 500)])->assertStatus(413);

        $this->assertSame([], $this->logged);
    }

    public function test_a_long_payload_is_truncated_and_marked(): void
    {
        $this->expectLog();
        config(['posts-ingest.max_entry_bytes' => 50, 'posts-ingest.max_body_bytes' => 10000]);

        $this->postJson('/posts', ['blob' => str_repeat('x', 500)])->assertNoContent();

        $this->assertCount(1, $this->logged);
        $this->assertTrue($this->logged[0]['truncated']);
        $this->assertLessThanOrEqual(50, strlen($this->logged[0]['payload']));
    }

    public function test_requests_beyond_the_limit_get_429(): void
    {
        $this->expectLog();
        config(['posts-ingest.rate_limit_per_minute' => 30]);

        for ($i = 0; $i < 30; $i++) {
            $this->postJson('/posts', ['n' => $i])->assertNoContent();
        }

        $this->postJson('/posts', ['n' => 31])->assertStatus(429);
        $this->assertCount(30, $this->logged);
    }

    public function test_the_limit_is_per_client_address(): void
    {
        $this->expectLog();
        config(['posts-ingest.rate_limit_per_minute' => 2]);

        foreach (['203.0.113.1', '203.0.113.2'] as $address) {
            for ($i = 0; $i < 2; $i++) {
                $this->postJson('/posts', ['n' => $i], ['CF-Connecting-IP' => $address])->assertNoContent();
            }
        }

        $this->postJson('/posts', [], ['CF-Connecting-IP' => '203.0.113.1'])->assertStatus(429);
    }
}
