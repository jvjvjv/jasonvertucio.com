<?php

namespace Tests\Feature;

use App\Models\User;
use BSPDX\Keystone\Models\KeystonePermission as Permission;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Inertia\Testing\AssertableInertia as Assert;
use Jvjvjv\CodeTalker\Models\AiMcpServer;
use Jvjvjv\CodeTalker\Models\AiMcpServerTool;
use Jvjvjv\CodeTalker\Models\AiSystem;
use Jvjvjv\CodeTalker\Services\Mcp\Remote\RemoteToolCatalogSync;
use Mockery;
use Tests\TestCase;

class AiMcpServerAdminTest extends TestCase
{
    use DatabaseTransactions;

    private const TOKEN = 'secret-bearer-token-8f3a';

    private function administrator(): User
    {
        Permission::firstOrCreate(['name' => 'manage-ai-tools']);
        $user = User::factory()->create();
        $user->givePermissionTo('manage-ai-tools');

        return $user;
    }

    /**
     * The package's FakeMcpServer is test-only and not autoloaded here, so the
     * sync step is replaced: it optionally stores the given tools, then
     * returns the report a real sync would.
     *
     * @param  array<int, array{name: string, available?: bool, reason?: string}>  $tools
     */
    private function fakeSync(array $tools = [], ?string $error = null): void
    {
        $sync = Mockery::mock(RemoteToolCatalogSync::class);
        $sync->shouldReceive('sync')->andReturnUsing(function (AiMcpServer $server) use ($tools, $error): array {
            if ($error !== null) {
                $server->forceFill(['last_sync_error' => $error, 'last_synced_at' => now()])->save();

                return ['server' => $server->slug, 'stored' => 0, 'unrepresentable' => 0, 'error' => $error];
            }

            foreach ($tools as $tool) {
                $this->storeTool($server, $tool['name'], $tool['available'] ?? true, $tool['reason'] ?? null);
            }

            $server->forceFill(['last_sync_error' => null, 'last_synced_at' => now()])->save();

            return [
                'server' => $server->slug,
                'stored' => count($tools),
                'unrepresentable' => count(array_filter($tools, fn (array $t): bool => ! ($t['available'] ?? true))),
                'error' => null,
            ];
        });

        $this->app->instance(RemoteToolCatalogSync::class, $sync);
    }

    private function storeTool(AiMcpServer $server, string $name, bool $available = true, ?string $reason = null): AiMcpServerTool
    {
        return AiMcpServerTool::query()->updateOrCreate(
            ['ai_mcp_server_id' => $server->id, 'remote_name' => $name],
            [
                'exposed_name' => $server->slug.'__'.$name,
                'description' => "The {$name} tool.",
                'input_schema' => ['type' => 'object', 'properties' => []],
                'representable' => $available,
                'unrepresentable_reason' => $reason,
                'definition_hash' => sha1($name),
                'synced_at' => now(),
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function server(array $attributes = []): AiMcpServer
    {
        return AiMcpServer::create([
            'slug' => 'docs',
            'name' => 'Docs Server',
            'transport' => AiMcpServer::TRANSPORT_HTTP,
            'url' => 'https://docs.example.test/mcp',
            'auth' => ['type' => 'bearer', 'token' => self::TOKEN],
            'enabled' => true,
            ...$attributes,
        ]);
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('admin.ai.mcp-servers.index'))->assertRedirect(route('login'));
    }

    public function test_users_without_permission_are_forbidden_everywhere(): void
    {
        $server = $this->server();
        $this->actingAs(User::factory()->create());

        $this->get(route('admin.ai.mcp-servers.index'))->assertForbidden();
        $this->get(route('admin.ai.mcp-servers.create'))->assertForbidden();
        $this->post(route('admin.ai.mcp-servers.store'), ['slug' => 'other', 'name' => 'Other', 'url' => 'https://other.test/mcp'])->assertForbidden();
        $this->get(route('admin.ai.mcp-servers.edit', $server))->assertForbidden();
        $this->put(route('admin.ai.mcp-servers.update', $server), ['name' => 'Changed'])->assertForbidden();
        $this->post(route('admin.ai.mcp-servers.sync', $server))->assertForbidden();
        $this->delete(route('admin.ai.mcp-servers.destroy', $server))->assertForbidden();

        $this->assertDatabaseMissing('ai_mcp_servers', ['slug' => 'other']);
        $this->assertSame('Docs Server', $server->fresh()->name);
        $this->assertNull($server->fresh()->deleted_at);
    }

    public function test_index_lists_sync_health_without_credentials(): void
    {
        $server = $this->server(['last_sync_error' => 'Connection refused', 'last_synced_at' => now()]);
        $this->storeTool($server, 'search');

        $response = $this->actingAs($this->administrator())->get(route('admin.ai.mcp-servers.index'));

        $response->assertOk();
        $response->assertDontSee(self::TOKEN);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('ai/mcp-servers/Index', false)
            ->where('servers', fn ($servers) => collect($servers)->contains(fn ($s) => $s['slug'] === 'docs'
                && $s['auth_type'] === 'bearer'
                && $s['last_sync_error'] === 'Connection refused'
                && $s['last_synced_at'] !== null
                && $s['tool_count'] === 1
                && ! array_key_exists('auth', $s)))
        );
    }

    public function test_create_syncs_and_reports_success(): void
    {
        $this->fakeSync([['name' => 'search'], ['name' => 'lookup'], ['name' => 'fetch']]);

        $response = $this->actingAs($this->administrator())->post(route('admin.ai.mcp-servers.store'), [
            'slug' => 'mdn',
            'name' => 'MDN',
            'url' => 'https://mdn.example.test/mcp',
            'auth' => ['type' => 'none'],
            'enabled' => true,
        ]);

        $server = AiMcpServer::query()->where('slug', 'mdn')->sole();
        $response->assertRedirect(route('admin.ai.mcp-servers.edit', $server));
        $response->assertSessionHas('success', fn (string $message) => str_contains($message, 'Synced 3 tool(s)'));
        $this->assertSame(3, $server->tools()->count());
    }

    public function test_create_saves_even_when_sync_fails(): void
    {
        $this->fakeSync(error: 'cURL error 7: Failed to connect');

        $response = $this->actingAs($this->administrator())->post(route('admin.ai.mcp-servers.store'), [
            'slug' => 'down',
            'name' => 'Down Server',
            'url' => 'https://down.example.test/mcp',
            'enabled' => true,
        ]);

        $server = AiMcpServer::query()->where('slug', 'down')->sole();
        $response->assertRedirect(route('admin.ai.mcp-servers.edit', $server));
        $response->assertSessionHas('error', fn (string $message) => str_contains($message, 'Failed to connect'));
        $this->assertSame('cURL error 7: Failed to connect', $server->last_sync_error);
    }

    public function test_create_rejects_invalid_input(): void
    {
        $this->fakeSync();
        $this->server();
        $this->actingAs($this->administrator());

        $cases = [
            ['slug', ['slug' => 'Bad_Slug']],
            ['slug', ['slug' => 'docs']],
            ['url', ['url' => 'ftp://files.example.test']],
            ['auth', ['auth' => ['type' => 'bearer']]],
        ];

        foreach ($cases as [$field, $override]) {
            $this->from(route('admin.ai.mcp-servers.create'))
                ->post(route('admin.ai.mcp-servers.store'), [
                    'slug' => 'fresh',
                    'name' => 'Fresh',
                    'url' => 'https://fresh.example.test/mcp',
                    ...$override,
                ])
                ->assertSessionHasErrors($field);
        }

        $this->assertDatabaseMissing('ai_mcp_servers', ['slug' => 'fresh']);
        $this->assertSame(1, AiMcpServer::query()->where('slug', 'docs')->count());
    }

    public function test_update_without_auth_keeps_the_stored_secret_and_ignores_slug(): void
    {
        $this->fakeSync();
        $server = $this->server();

        $this->actingAs($this->administrator())
            ->put(route('admin.ai.mcp-servers.update', $server), [
                'slug' => 'renamed',
                'name' => 'Docs Renamed',
                'url' => 'https://docs.example.test/mcp',
            ])
            ->assertRedirect(route('admin.ai.mcp-servers.edit', $server));

        $server->refresh();
        $this->assertSame('Docs Renamed', $server->name);
        $this->assertSame('docs', $server->slug);
        $this->assertSame(['type' => 'bearer', 'token' => self::TOKEN], $server->auth);
    }

    public function test_switching_auth_type_replaces_credentials(): void
    {
        $this->fakeSync();
        $server = $this->server();

        $this->actingAs($this->administrator())
            ->put(route('admin.ai.mcp-servers.update', $server), [
                'name' => 'Docs Server',
                'auth' => ['type' => 'none'],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(['type' => 'none'], $server->fresh()->auth);
    }

    public function test_manual_sync_reports_counts(): void
    {
        $this->fakeSync([['name' => 'search'], ['name' => 'broken', 'available' => false, 'reason' => 'Unsupported schema']]);
        $server = $this->server();

        $this->actingAs($this->administrator())
            ->post(route('admin.ai.mcp-servers.sync', $server))
            ->assertRedirect(route('admin.ai.mcp-servers.edit', $server))
            ->assertSessionHas('success', fn (string $message) => str_contains($message, 'Synced 2 tool(s), 1 unavailable'));
    }

    public function test_edit_shows_catalog_including_unavailable_tools(): void
    {
        $server = $this->server();
        $this->storeTool($server, 'search');
        $this->storeTool($server, 'broken', available: false, reason: 'Schema uses an unsupported keyword');

        $response = $this->actingAs($this->administrator())->get(route('admin.ai.mcp-servers.edit', $server));

        $response->assertOk();
        $response->assertDontSee(self::TOKEN);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('ai/mcp-servers/Edit', false)
            ->where('server.slug', 'docs')
            ->has('server.tools', 2)
            ->where('server.tools.0.exposed_name', 'docs__broken')
            ->where('server.tools.0.available', false)
            ->where('server.tools.0.unavailable_reason', 'Schema uses an unsupported keyword')
            ->where('server.tools.1.exposed_name', 'docs__search')
            ->where('server.tools.1.available', true)
        );
    }

    public function test_delete_reports_how_many_systems_had_granted_its_tools(): void
    {
        $server = $this->server();
        AiSystem::factory()->create(['allowed_tools' => ['docs__search']]);
        AiSystem::factory()->create(['allowed_tools' => ['docs__lookup', 'fetch-web-page']]);
        AiSystem::factory()->create(['allowed_tools' => ['fetch-web-page']]);

        $this->actingAs($this->administrator())
            ->delete(route('admin.ai.mcp-servers.destroy', $server))
            ->assertRedirect(route('admin.ai.mcp-servers.index'))
            ->assertSessionHas('success', fn (string $message) => str_contains($message, '2 AI system(s)'));

        $this->assertSoftDeleted('ai_mcp_servers', ['id' => $server->id]);
    }

    public function test_ai_landing_page_links_to_mcp_servers(): void
    {
        $this->actingAs($this->administrator())
            ->get(route('admin.ai.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('navBlocks', fn ($blocks) => collect($blocks)->contains(
                    fn ($block) => $block['href'] === '/admin/ai/mcp-servers' && $block['label'] === 'MCP Servers',
                ))
            );
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }
}
