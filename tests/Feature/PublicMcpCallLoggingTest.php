<?php

namespace Tests\Feature;

use App\Models\McpCall;
use App\Models\ResumeVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * What the endpoint records, including for the calls it refuses.
 */
class PublicMcpCallLoggingTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        RateLimiter::clear('mcp');
        McpCall::query()->delete();
    }

    /**
     * The session id the handshake minted, echoed back on later calls the way
     * a real MCP client does — it is what attributes a session's calls to the
     * client identity captured at initialization.
     */
    private ?string $sessionId = null;

    /**
     * @param  array<string, mixed>  $params
     * @param  array<string, string>  $headers
     */
    private function rpc(string $method, array $params = [], array $headers = []): TestResponse
    {
        if ($this->sessionId !== null && ! isset($headers['MCP-Session-Id'])) {
            $headers['MCP-Session-Id'] = $this->sessionId;
        }

        return $this->postJson('/mcp', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => $method,
            'params' => $params,
        ], array_merge(['Accept' => 'application/json'], $headers));
    }

    /**
     * @param  array<string, string>  $headers
     * @param  array<string, mixed>|null  $clientInfo
     */
    private function initialize(array $headers = [], ?array $clientInfo = ['name' => 'phpunit-client', 'version' => '9.9.9']): TestResponse
    {
        $params = ['protocolVersion' => '2025-06-18', 'capabilities' => []];

        if ($clientInfo !== null) {
            $params['clientInfo'] = $clientInfo;
        }

        $response = $this->rpc('initialize', $params, $headers);

        $this->sessionId = $response->headers->get('MCP-Session-Id');

        return $response;
    }

    private function liveVersion(): ResumeVersion
    {
        $version = ResumeVersion::factory()->create(['is_current' => true]);
        $version->personalInfo()->create([
            'name' => 'Jason Vertucio',
            'title' => 'Engineer',
            'email' => 'jason@example.com',
        ]);

        return $version;
    }

    public function test_an_anonymous_tool_call_is_recorded(): void
    {
        $this->liveVersion();
        $this->initialize();

        $this->rpc('tools/call', ['name' => 'get-resume-data', 'arguments' => []])->assertOk();

        $record = McpCall::query()->where('method', 'tools/call')->firstOrFail();

        $this->assertSame('get-resume-data', $record->tool_name);
        $this->assertTrue($record->isAnonymous());
        $this->assertSame(McpCall::OUTCOME_OK, $record->outcome);
        $this->assertSame(200, $record->status_code);
        $this->assertNotNull($record->client_address);
        $this->assertNotNull($record->session_id);
        $this->assertIsInt($record->duration_ms);
    }

    public function test_an_authenticated_call_records_the_identity_and_no_credential(): void
    {
        $this->liveVersion();

        $user = User::factory()->create();
        $plainTextToken = $user->createToken('mcp-test')->plainTextToken;
        $headers = ['Authorization' => 'Bearer '.$plainTextToken];

        $this->initialize($headers);
        $this->rpc('tools/call', ['name' => 'get-resume-data', 'arguments' => []], $headers)->assertOk();

        $record = McpCall::query()->where('method', 'tools/call')->firstOrFail();

        $this->assertSame($user->getAuthIdentifier(), $record->user_id);

        // No part of the credential may appear anywhere in the row.
        [, $secret] = explode('|', $plainTextToken, 2);
        $serialized = json_encode($record->toArray());

        $this->assertStringNotContainsString($plainTextToken, $serialized);
        $this->assertStringNotContainsString($secret, $serialized);
    }

    public function test_a_rejected_unauthenticated_call_is_still_recorded(): void
    {
        $this->rpc('tools/list', [], ['Authorization' => 'Bearer nonsense'])->assertStatus(401);

        $record = McpCall::query()->firstOrFail();

        $this->assertSame(McpCall::OUTCOME_UNAUTHENTICATED, $record->outcome);
        $this->assertSame(401, $record->status_code);
        $this->assertTrue($record->isAnonymous());
    }

    public function test_a_throttled_call_is_still_recorded(): void
    {
        config(['mcp-server.limits.anonymous.per_minute' => 2]);

        $this->rpc('ping')->assertOk();
        $this->rpc('ping')->assertOk();
        $this->rpc('ping')->assertStatus(429);

        $throttled = McpCall::query()->where('outcome', McpCall::OUTCOME_THROTTLED)->get();

        $this->assertCount(1, $throttled);
        $this->assertSame(429, $throttled->first()->status_code);
    }

    public function test_the_clients_reported_identity_is_captured_and_attributed_to_later_calls(): void
    {
        $this->liveVersion();
        $this->initialize();

        $this->rpc('tools/call', ['name' => 'get-site-info', 'arguments' => []])->assertOk();

        $handshake = McpCall::query()->where('method', 'initialize')->firstOrFail();
        $this->assertSame('phpunit-client', $handshake->client_name);
        $this->assertSame('9.9.9', $handshake->client_version);

        $later = McpCall::query()->where('method', 'tools/call')->firstOrFail();
        $this->assertSame('phpunit-client', $later->client_name);
        $this->assertSame($handshake->session_id, $later->session_id);
    }

    public function test_a_client_reporting_no_identity_is_tolerated(): void
    {
        $this->initialize(clientInfo: null)->assertOk();

        $record = McpCall::query()->where('method', 'initialize')->firstOrFail();

        $this->assertNull($record->client_name);
        $this->assertNull($record->client_version);
    }

    public function test_the_sweep_deletes_records_past_the_retention_period(): void
    {
        config(['mcp-server.log_retention_days' => 30]);

        $old = McpCall::factory()->create(['created_at' => now()->subDays(31)]);
        $recent = McpCall::factory()->create(['created_at' => now()->subDays(29)]);

        $this->artisan('mcp:sweep-call-log')->assertSuccessful();

        $this->assertNull(McpCall::find($old->id));
        $this->assertNotNull(McpCall::find($recent->id));
    }
}
