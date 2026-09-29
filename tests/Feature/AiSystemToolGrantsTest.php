<?php

namespace Tests\Feature;

use App\Models\User;
use BSPDX\Keystone\Models\KeystonePermission as Permission;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Jvjvjv\CodeTalker\Models\AiMcpServer;
use Jvjvjv\CodeTalker\Models\AiMcpServerTool;
use Jvjvjv\CodeTalker\Models\AiSystem;
use Jvjvjv\CodeTalker\Services\Management\AiPersonaManager;
use Tests\TestCase;

class AiSystemToolGrantsTest extends TestCase
{
    use DatabaseTransactions;

    private function administrator(): User
    {
        Permission::firstOrCreate(['name' => 'manage-ai-tools']);
        $user = User::factory()->create();
        $user->givePermissionTo('manage-ai-tools');

        return $user;
    }

    private function server(string $slug, bool $enabled = true): AiMcpServer
    {
        return AiMcpServer::create([
            'slug' => $slug,
            'name' => ucfirst($slug).' Server',
            'transport' => AiMcpServer::TRANSPORT_HTTP,
            'url' => "https://{$slug}.example.test/mcp",
            'enabled' => $enabled,
        ]);
    }

    private function tool(AiMcpServer $server, string $name, bool $available = true, ?string $reason = null): AiMcpServerTool
    {
        return AiMcpServerTool::create([
            'ai_mcp_server_id' => $server->id,
            'remote_name' => $name,
            'exposed_name' => $server->slug.'__'.$name,
            'description' => "The {$name} tool.",
            'input_schema' => ['type' => 'object', 'properties' => ['q' => ['type' => 'string']]],
            'representable' => $available,
            'unrepresentable_reason' => $reason,
            'definition_hash' => sha1($name),
            'synced_at' => now(),
        ]);
    }

    public function test_create_and_edit_pages_carry_the_server_catalog(): void
    {
        $docs = $this->server('docs');
        $this->tool($docs, 'search');
        $this->tool($docs, 'broken', available: false, reason: 'Unsupported schema keyword');
        $this->tool($this->server('off', enabled: false), 'lookup');
        $system = AiSystem::factory()->create();
        $this->actingAs($this->administrator());

        $assertCatalog = fn (Assert $page) => $page
            ->where('mcpServers', function ($servers) {
                $servers = collect($servers)->keyBy('slug');

                return $servers['docs']['name'] === 'Docs Server'
                    && $servers['docs']['enabled'] === true
                    && collect($servers['docs']['tools'])->contains(fn ($t) => $t['exposed_name'] === 'docs__search' && $t['available'] === true)
                    && collect($servers['docs']['tools'])->contains(fn ($t) => $t['exposed_name'] === 'docs__broken'
                        && $t['available'] === false
                        && $t['unavailable_reason'] === 'Unsupported schema keyword')
                    && $servers['off']['enabled'] === false
                    && ! array_key_exists('auth', $servers['docs'])
                    && ! array_key_exists('auth_type', $servers['docs']);
            });

        $this->get(route('admin.ai.systems.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $assertCatalog($page->component('ai/systems/Create', false)));

        $this->get(route('admin.ai.systems.edit', $system))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $assertCatalog($page->component('ai/systems/Edit', false)));
    }

    public function test_granting_a_remote_tool_offers_it_to_the_system_and_keeps_stale_grants(): void
    {
        Http::fake();
        $this->tool($this->server('docs'), 'search');
        $system = AiSystem::factory()->create([
            'provider' => 'openai-compatible',
            'allowed_tools' => ['fetch-web-page'],
        ]);

        $this->actingAs($this->administrator())
            ->put(route('admin.ai.systems.update', $system), [
                'name' => $system->name,
                'base_url' => 'http://localhost:1234/v1',
                'api_version' => '',
                'max_tokens' => 4096,
                'context_length' => null,
                'temperature' => 0.5,
                'system_prompt' => 'Test.',
                'config' => '',
                'credentials' => '',
                'auth_type' => 'none',
                'endpoint_type' => 'openai-compatible',
                'stream_protocol' => 'chunked-json',
                'system_prompt_mode' => 'messages',
                'supports_tools' => true,
                'allowed_tools' => ['fetch-web-page', 'docs__search', 'gone__lookup'],
                'supports_json_mode' => false,
                'is_local_endpoint' => true,
                'pricing_profile' => '',
                'is_active' => true,
                'feature_defaults' => [],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.ai.systems.index'));

        $this->assertSame(['fetch-web-page', 'docs__search', 'gone__lookup'], $system->fresh()->allowed_tools);

        $offered = array_column(app(AiPersonaManager::class)->availableTools(aiSystemId: $system->id), 'name');

        $this->assertContains('docs__search', $offered);
        $this->assertContains('fetch-web-page', $offered);
        $this->assertNotContains('gone__lookup', $offered);
    }
}
