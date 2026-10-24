<?php

namespace Tests\Feature;

use App\Mcp\Servers\PublicServer;
use App\Mcp\Tools\PublicRecentBlogPostsTool;
use App\Mcp\Tools\PublicResumeDataTool;
use App\Models\ResumeVersion;
use App\Models\User;
use App\Services\Mcp\PublicMcpCache;
use BSPDX\Keystone\Models\KeystonePermission as Permission;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Mcp\Server\Testing\TestResponse;
use Tests\TestCase;

/**
 * Repeated calls are served from cache — which is what lets the rate limits be
 * sized for abuse rather than for load — without ever serving one caller's
 * projection to another.
 */
class PublicMcpCachingTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        // Each test starts from a clean generation so a previous test's stamp
        // cannot make a cache hit look like a miss.
        PublicMcpCache::flush(PublicMcpCache::GROUP_RESUME);
        PublicMcpCache::flush(PublicMcpCache::GROUP_BLOG_POSTS);
    }

    /**
     * @return array<string, mixed>
     */
    private function structured(TestResponse $response): array
    {
        return (fn (): array => $this->response->toArray()['result']['structuredContent'] ?? [])->call($response);
    }

    private function liveVersion(string $name = 'Jason Vertucio'): ResumeVersion
    {
        $version = ResumeVersion::factory()->create(['is_current' => true]);
        $version->personalInfo()->create([
            'name' => $name,
            'title' => 'Engineer',
            'email' => 'jason@example.com',
            'phone' => '(555) 123-4567',
        ]);

        return $version;
    }

    /**
     * Queries issued while running the callback.
     */
    private function countQueries(callable $callback): int
    {
        $count = 0;

        DB::listen(function () use (&$count): void {
            $count++;
        });

        $callback();

        return $count;
    }

    public function test_a_repeated_identical_anonymous_call_is_served_from_cache(): void
    {
        $this->liveVersion();

        $first = $this->countQueries(function (): void {
            PublicServer::tool(PublicResumeDataTool::class)->assertOk();
        });

        $second = $this->countQueries(function (): void {
            PublicServer::tool(PublicResumeDataTool::class)->assertOk();
        });

        $this->assertGreaterThan(0, $first, 'The first call should read from the database.');
        $this->assertSame(0, $second, 'The second identical call should not touch the database.');
    }

    public function test_anonymous_and_authenticated_results_are_cached_separately(): void
    {
        $this->liveVersion();

        Permission::firstOrCreate(['name' => 'save-resume']);
        $user = User::factory()->create();
        $user->givePermissionTo('save-resume');

        $anonymous = $this->structured(PublicServer::tool(PublicResumeDataTool::class)->assertOk());
        $authenticated = $this->structured(
            PublicServer::actingAs($user)->tool(PublicResumeDataTool::class)->assertOk()
        );

        $this->assertArrayNotHasKey('email', $anonymous['personal']);
        $this->assertSame('jason@example.com', $authenticated['personal']['email']);
    }

    public function test_the_order_of_the_two_callers_does_not_leak_either_projection(): void
    {
        $this->liveVersion();

        Permission::firstOrCreate(['name' => 'save-resume']);
        $user = User::factory()->create();
        $user->givePermissionTo('save-resume');

        // Authenticated first this time, so a shared key would hand the
        // anonymous caller the unredacted copy.
        $authenticated = $this->structured(
            PublicServer::actingAs($user)->tool(PublicResumeDataTool::class)->assertOk()
        );

        // actingAs() sets the guard for the remainder of the test process; a
        // real second request would arrive in a fresh container, so drop the
        // resolved guard or the "anonymous" call below is still authenticated.
        $this->app['auth']->forgetGuards();

        $anonymous = $this->structured(PublicServer::tool(PublicResumeDataTool::class)->assertOk());

        $this->assertSame('jason@example.com', $authenticated['personal']['email']);
        $this->assertArrayNotHasKey('email', $anonymous['personal']);
        $this->assertArrayNotHasKey('phone', $anonymous['personal']);
    }

    public function test_publishing_a_new_resume_version_is_visible_on_the_next_call(): void
    {
        $this->liveVersion('Original Name');

        $before = $this->structured(PublicServer::tool(PublicResumeDataTool::class)->assertOk());
        $this->assertSame('Original Name', $before['personal']['name']);

        ResumeVersion::query()->update(['is_current' => false]);
        $this->liveVersion('Republished Name');

        $after = $this->structured(PublicServer::tool(PublicResumeDataTool::class)->assertOk());

        $this->assertSame('Republished Name', $after['personal']['name'], 'A newly published version must not wait for a TTL.');
    }

    public function test_publishing_a_post_is_reflected_in_the_next_post_listing(): void
    {
        $before = $this->structured(PublicServer::tool(PublicRecentBlogPostsTool::class)->assertOk());
        $this->assertArrayHasKey('posts', $before);

        // The listener is what matters here, not Canvas's own publish flow:
        // firing the event the listener is registered on must orphan the entry.
        event(new \Canvas\Events\PostPublished(new \Canvas\Models\Post));

        $queriesAfterInvalidation = $this->countQueries(function (): void {
            PublicServer::tool(PublicRecentBlogPostsTool::class)->assertOk();
        });

        $this->assertGreaterThan(0, $queriesAfterInvalidation, 'Publishing a post must invalidate the cached listing.');
    }
}
