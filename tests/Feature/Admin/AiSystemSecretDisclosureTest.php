<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use BSPDX\Keystone\Models\KeystonePermission as Permission;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Jvjvjv\CodeTalker\Enums\AiProvider;
use Jvjvjv\CodeTalker\Models\AiSystem;
use Tests\TestCase;

class AiSystemSecretDisclosureTest extends TestCase
{
    use DatabaseTransactions;

    private const API_KEY = 'sk-ant-super-secret-key';

    private const HEADER = 'Bearer header-secret-value';

    private function admin(): User
    {
        Permission::firstOrCreate(['name' => 'manage-ai-tools']);
        $user = User::factory()->create();
        $user->givePermissionTo('manage-ai-tools');

        return $user;
    }

    private function makeSystem(array $overrides = []): AiSystem
    {
        return AiSystem::factory()->create(array_merge([
            'provider' => AiProvider::Anthropic->value,
            'model' => 'claude-sonnet-4-6',
            'api_key' => self::API_KEY,
            'web_tool_policy' => [
                'allowed_domains' => ['a.example.com', 'b.example.com'],
                'credentials' => [
                    'a.example.com' => ['Authorization' => self::HEADER],
                    'b.example.com' => ['X-Api-Key' => 'b-secret'],
                ],
            ],
            'duplicated_at' => null,
        ], $overrides));
    }

    private function payload(AiSystem $system, array $overrides = []): array
    {
        return array_merge([
            'name' => $system->name,
            'base_url' => '',
            'api_version' => '',
            'max_tokens' => 4096,
            'context_length' => null,
            'temperature' => 0.7,
            'config' => '',
            'credentials' => '',
            'api_key' => '',
            'auth_type' => '',
            'endpoint_type' => '',
            'stream_protocol' => '',
            'system_prompt_mode' => '',
            'supports_tools' => false,
            'allowed_tools' => [],
            'supports_json_mode' => false,
            'is_local_endpoint' => false,
            'is_active' => true,
            'feature_defaults' => [],
            'web_tool_policy' => '',
        ], $overrides);
    }

    private function assertNoSecrets(string $json): void
    {
        foreach ([self::API_KEY, self::HEADER, 'b-secret'] as $secret) {
            $this->assertStringNotContainsString($secret, $json);
        }
    }

    public function test_the_systems_list_contains_no_secrets(): void
    {
        $this->makeSystem();

        $response = $this->actingAs($this->admin())->get(route('admin.ai.systems.index'));

        $response->assertOk();
        $this->assertNoSecrets(json_encode($response->viewData('page')['props']));
        $response->assertInertia(fn (Assert $page) => $page
            ->where('systems.0.has_api_key', true)
            ->missing('systems.0.api_key')
            ->missing('systems.0.credentials'));
    }

    public function test_the_edit_page_contains_no_secrets_but_says_they_are_set(): void
    {
        $system = $this->makeSystem();

        $response = $this->actingAs($this->admin())->get(route('admin.ai.systems.edit', $system));

        $response->assertOk();
        $this->assertNoSecrets(json_encode($response->viewData('page')['props']));
        $response->assertInertia(fn (Assert $page) => $page
            ->where('aiSystem.has_api_key', true)
            ->where('aiSystem.has_credentials', false)
            ->where('aiSystem.web_tool_policy.allowed_domains', ['a.example.com', 'b.example.com'])
            ->where('aiSystem.web_tool_policy.has_headers', ['a.example.com' => true, 'b.example.com' => true]));
    }

    public function test_the_logs_page_contains_no_secrets(): void
    {
        $system = $this->makeSystem();

        $response = $this->actingAs($this->admin())->get(route('admin.ai.systems.logs', $system));

        $response->assertOk();
        $this->assertNoSecrets(json_encode($response->viewData('page')['props']));
    }

    public function test_duplicating_exposes_no_secrets_and_the_copy_keeps_the_key(): void
    {
        $system = $this->makeSystem();
        $admin = $this->admin();

        $redirect = $this->actingAs($admin)->post(route('admin.ai.systems.duplicate', $system));
        $clone = AiSystem::query()->where('name', $system->name.' (copy)')->firstOrFail();

        $this->assertNoSecrets($redirect->getContent());
        $this->assertSame(self::API_KEY, $clone->api_key);

        $page = $this->actingAs($admin)->get(route('admin.ai.systems.edit', $clone));
        $this->assertNoSecrets(json_encode($page->viewData('page')['props']));
    }

    public function test_saving_without_a_key_keeps_the_stored_key_and_credentials(): void
    {
        $system = $this->makeSystem();

        $this->actingAs($this->admin())
            ->put(route('admin.ai.systems.update', $system), $this->payload($system, ['name' => 'Renamed']))
            ->assertRedirect(route('admin.ai.systems.index'));

        $system->refresh();
        $this->assertSame('Renamed', $system->name);
        $this->assertSame(self::API_KEY, $system->api_key);
    }

    public function test_entering_a_key_replaces_it(): void
    {
        $system = $this->makeSystem();

        $this->actingAs($this->admin())
            ->put(route('admin.ai.systems.update', $system), $this->payload($system, [
                'api_key' => 'sk-new-key',
            ]));

        $this->assertSame('sk-new-key', $system->refresh()->api_key);
    }

    public function test_clear_flag_removes_the_key(): void
    {
        $system = $this->makeSystem();

        $this->actingAs($this->admin())
            ->put(route('admin.ai.systems.update', $system), $this->payload($system, [
                'clear_api_key' => true,
            ]));

        $this->assertSame('', $system->refresh()->api_key);
    }

    public function test_a_policy_saved_with_a_new_domain_keeps_other_domains_stored_headers(): void
    {
        $system = $this->makeSystem();

        $this->actingAs($this->admin())
            ->put(route('admin.ai.systems.update', $system), $this->payload($system, [
                'web_tool_policy' => json_encode([
                    'allowed_domains' => ['a.example.com', 'b.example.com', 'c.example.com'],
                    'credentials' => ['c.example.com' => ['Authorization' => 'c-secret']],
                ]),
            ]));

        $policy = $system->refresh()->web_tool_policy;
        $this->assertSame(['a.example.com', 'b.example.com', 'c.example.com'], $policy['allowed_domains']);
        $this->assertSame(['Authorization' => self::HEADER], $policy['credentials']['a.example.com']);
        $this->assertSame(['X-Api-Key' => 'b-secret'], $policy['credentials']['b.example.com']);
        $this->assertSame(['Authorization' => 'c-secret'], $policy['credentials']['c.example.com']);
    }

    public function test_a_policy_can_replace_or_clear_one_domains_headers_and_drops_removed_domains(): void
    {
        $system = $this->makeSystem();

        $this->actingAs($this->admin())
            ->put(route('admin.ai.systems.update', $system), $this->payload($system, [
                'web_tool_policy' => json_encode([
                    'allowed_domains' => ['a.example.com', 'b.example.com'],
                    'credentials' => ['a.example.com' => ['Authorization' => 'replaced']],
                    'cleared_hosts' => ['b.example.com'],
                ]),
            ]));

        $policy = $system->refresh()->web_tool_policy;
        $this->assertSame(['Authorization' => 'replaced'], $policy['credentials']['a.example.com']);
        $this->assertArrayNotHasKey('b.example.com', $policy['credentials'] ?? []);
        $this->assertArrayNotHasKey('cleared_hosts', $policy);

        $this->actingAs($this->admin())
            ->put(route('admin.ai.systems.update', $system), $this->payload($system, [
                'web_tool_policy' => json_encode(['allowed_domains' => ['b.example.com']]),
            ]));

        $this->assertArrayNotHasKey('a.example.com', $system->refresh()->web_tool_policy['credentials'] ?? []);
    }

    public function test_a_pending_duplicate_with_a_kept_key_can_be_saved_without_retyping_it(): void
    {
        $clone = $this->makeSystem(['duplicated_at' => now()]);

        $this->actingAs($this->admin())
            ->put(route('admin.ai.systems.update', $clone), $this->payload($clone, [
                'provider' => $clone->provider,
                'model' => $clone->model,
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame(self::API_KEY, $clone->refresh()->api_key);
        $this->assertNull($clone->duplicated_at);
    }

    public function test_a_pending_duplicate_that_changes_provider_must_supply_a_key(): void
    {
        $clone = $this->makeSystem(['duplicated_at' => now()]);

        $this->actingAs($this->admin())
            ->put(route('admin.ai.systems.update', $clone), $this->payload($clone, [
                'provider' => AiProvider::OpenAI->value,
                'model' => 'gpt-4o',
            ]))
            ->assertSessionHasErrors('api_key');
    }

    public function test_model_lookup_uses_the_stored_key_when_none_is_entered(): void
    {
        Http::fake(['*' => Http::response(['data' => [['id' => 'claude-x', 'display_name' => 'Claude X']]])]);
        $system = $this->makeSystem();

        $response = $this->actingAs($this->admin())->postJson('/api/admin/ai/systems/fetch-models', [
            'provider' => AiProvider::Anthropic->value,
            'ai_system_id' => $system->id,
        ]);

        $response->assertOk()->assertJsonPath('models.0.id', 'claude-x');
        Http::assertSent(fn ($request) => $request->hasHeader('x-api-key', self::API_KEY));
        $this->assertStringNotContainsString(self::API_KEY, $response->getContent());
    }

    public function test_model_lookup_for_an_unknown_system_is_refused(): void
    {
        Http::fake();

        $this->actingAs($this->admin())->postJson('/api/admin/ai/systems/fetch-models', [
            'provider' => AiProvider::Anthropic->value,
            'ai_system_id' => 999999999,
        ])->assertStatus(422);

        Http::assertNothingSent();
    }

    public function test_model_lookup_for_a_system_the_caller_may_not_manage_is_refused(): void
    {
        Http::fake();
        $system = $this->makeSystem();

        $this->actingAs(User::factory()->create())
            ->postJson('/api/admin/ai/systems/fetch-models', [
                'provider' => AiProvider::Anthropic->value,
                'ai_system_id' => $system->id,
            ])->assertForbidden();

        Http::assertNothingSent();
    }
}
