<?php

namespace Tests\Feature;

use App\Models\ResumeVersion;
use App\Models\User;
use BSPDX\Keystone\Models\KeystonePermission as Permission;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * The endpoint over its real HTTP transport: handshake, discovery, invocation,
 * the optional bearer rule and the roster boundary.
 */
class PublicMcpServerTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        RateLimiter::clear('mcp');
    }

    /**
     * @param  array<string, mixed>  $params
     * @param  array<string, string>  $headers
     */
    private function rpc(string $method, array $params = [], array $headers = [], int $id = 1): TestResponse
    {
        return $this->postJson('/mcp', [
            'jsonrpc' => '2.0',
            'id' => $id,
            'method' => $method,
            'params' => $params,
        ], array_merge([
            'Accept' => 'application/json',
        ], $headers));
    }

    /**
     * @param  array<string, string>  $headers
     */
    private function initialize(array $headers = []): TestResponse
    {
        return $this->rpc('initialize', [
            'protocolVersion' => '2025-06-18',
            'capabilities' => [],
            'clientInfo' => ['name' => 'phpunit-client', 'version' => '9.9.9'],
        ], $headers);
    }

    private function liveVersion(): ResumeVersion
    {
        $version = ResumeVersion::factory()->create(['is_current' => true]);

        $version->personalInfo()->create([
            'name' => 'Jason Vertucio',
            'title' => 'Lead Front-End Engineer',
            'email' => 'jason@example.com',
            'phone' => '(555) 123-4567',
            'linkedin' => 'linkedin.com/in/jasonvertucio',
            'url' => 'https://jasonvertucio.com',
            'summary' => 'Builds things.',
        ]);

        $version->experiences()->create([
            'job_title' => 'Engineer',
            'company' => 'Acme',
            'sort_order' => 0,
            'salary_start_amount' => '100000',
            'salary_start_period' => 'per_year',
        ]);

        return $version;
    }

    public function test_anonymous_client_completes_the_initialization_handshake(): void
    {
        $response = $this->initialize();

        $response->assertOk();
        $this->assertArrayHasKey('tools', $response->json('result.capabilities'));
        $this->assertSame('Jason Vertucio', $response->json('result.serverInfo.name'));
    }

    public function test_anonymous_client_lists_exactly_the_three_public_tools(): void
    {
        $this->initialize();

        $names = collect($this->rpc('tools/list')->assertOk()->json('result.tools'))
            ->pluck('name')
            ->sort()
            ->values()
            ->all();

        $this->assertSame([
            'get-recent-blog-posts',
            'get-resume-data',
            'get-site-info',
        ], $names);
    }

    public function test_anonymous_client_calls_a_tool_successfully(): void
    {
        $this->liveVersion();
        $this->initialize();

        $response = $this->rpc('tools/call', ['name' => 'get-resume-data', 'arguments' => []]);

        $response->assertOk();
        $this->assertNotTrue($response->json('result.isError'));
        $this->assertSame('Jason Vertucio', $response->json('result.structuredContent.personal.name'));
        $this->assertNull($response->json('result.structuredContent.personal.email'));
    }

    public function test_get_requests_are_answered_with_method_not_allowed(): void
    {
        $this->get('/mcp')->assertStatus(405)->assertHeader('Allow', 'POST');
    }

    public function test_an_unresolvable_bearer_token_is_rejected_rather_than_downgraded(): void
    {
        $this->liveVersion();

        $this->initialize(['Authorization' => 'Bearer not-a-real-token'])
            ->assertStatus(401)
            ->assertJsonPath('error', 'invalid_token');
    }

    public function test_a_valid_token_raises_the_callers_identity(): void
    {
        $this->liveVersion();

        Permission::firstOrCreate(['name' => 'save-resume']);
        $user = User::factory()->create();
        $user->givePermissionTo('save-resume');
        $token = $user->createToken('mcp-test')->plainTextToken;

        $headers = ['Authorization' => 'Bearer '.$token];

        $this->initialize($headers)->assertOk();

        $response = $this->rpc('tools/call', [
            'name' => 'get-resume-data',
            'arguments' => [],
        ], $headers);

        $response->assertOk();

        // The tool resolved the token's user: contact details are present, and
        // the permission check inside the tool saw the same identity.
        $this->assertSame('jason@example.com', $response->json('result.structuredContent.personal.email'));
        $this->assertSame('(555) 123-4567', $response->json('result.structuredContent.personal.phone'));
        $this->assertNotNull($response->json('result.structuredContent.experience.0.salaryStart'));
    }

    public function test_a_tool_outside_the_roster_is_not_reachable_by_name(): void
    {
        $this->liveVersion();

        Permission::firstOrCreate(['name' => 'edit-resume']);
        $user = User::factory()->create();
        $user->givePermissionTo('edit-resume');
        $token = $user->createToken('mcp-test')->plainTextToken;

        $headers = ['Authorization' => 'Bearer '.$token];
        $this->initialize($headers);

        $response = $this->rpc('tools/call', [
            'name' => 'update-resume-section',
            'arguments' => ['section' => 'personal', 'data' => '{}'],
        ], $headers);

        // Even holding every permission the tool would require, it is not on
        // this server's roster and therefore does not exist here.
        $this->assertTrue(
            $response->json('error') !== null || $response->json('result.isError') === true,
            'Expected an off-roster tool call to fail.'
        );
        $this->assertStringContainsString('update-resume-section', json_encode($response->json()));
    }

    public function test_a_complete_session_is_never_throttled(): void
    {
        $this->liveVersion();

        $this->initialize()->assertOk();
        $this->rpc('tools/list')->assertOk();

        foreach (['get-resume-data', 'get-recent-blog-posts', 'get-site-info'] as $tool) {
            $this->rpc('tools/call', ['name' => $tool, 'arguments' => []])
                ->assertOk();
        }
    }

    public function test_exceeding_the_anonymous_burst_limit_is_throttled(): void
    {
        config(['mcp-server.limits.anonymous.per_minute' => 3]);

        for ($i = 0; $i < 3; $i++) {
            $this->rpc('ping')->assertOk();
        }

        $this->rpc('ping')->assertStatus(429);
    }
}
