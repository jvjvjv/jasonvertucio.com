<?php

namespace Tests\Feature;

use Jvjvjv\CodeTalker\Enums\AiProvider;
use Jvjvjv\CodeTalker\Models\AiSystem;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AiSystemCapabilityPersistenceTest extends TestCase
{
    use DatabaseTransactions;

    private function authenticatedUser(): User
    {
        Permission::findOrCreate('manage-ai-tools', 'web');
        $user = User::factory()->create();
        $user->givePermissionTo('manage-ai-tools');

        return $user;
    }

    public function test_store_persists_lm_studio_model_capabilities_without_frontend_payload(): void
    {
        Http::fake([
            'http://localhost:1234/api/v1/models' => Http::response([
                'models' => [
                    [
                        'type' => 'llm',
                        'key' => 'google/gemma-4-e4b',
                        'display_name' => 'Gemma 4 E4B',
                        'loaded_instances' => [],
                        'max_context_length' => 131072,
                        'capabilities' => [
                            'vision' => true,
                            'trained_for_tool_use' => true,
                            'reasoning' => ['default' => 'on'],
                        ],
                    ],
                ],
            ]),
        ]);

        $response = $this->actingAs($this->authenticatedUser())
            ->post(route('admin.ai.systems.store'), [
                'name' => 'Local Gemma',
                'provider' => AiProvider::LmStudio->value,
                'api_key' => 'unused-local-key',
                'model' => 'google/gemma-4-e4b',
                'base_url' => 'http://localhost:1234',
                'api_version' => '',
                'max_tokens' => 4096,
                'context_length' => 8192,
                'temperature' => 0.4,
                'system_prompt' => 'You are local.',
                'config' => '',
                'credentials' => '',
                'auth_type' => 'none',
                'endpoint_type' => 'local',
                'stream_protocol' => 'chunked-json',
                'system_prompt_mode' => 'messages',
                'supports_tools' => false,
                'allowed_tools' => [],
                'supports_json_mode' => false,
                'is_local_endpoint' => true,
                'pricing_profile' => '',
                'is_active' => true,
                'feature_defaults' => [],
            ]);

        $response->assertRedirect(route('admin.ai.systems.index'));

        $system = AiSystem::query()->where('name', 'Local Gemma')->firstOrFail();

        $this->assertEquals([
            'reasoning' => true,
            'vision' => true,
            'tools' => true,
            'max_context_length' => 131072,
        ], $system->model_capabilities);
    }

    public function test_update_refreshes_lm_studio_model_capabilities_without_frontend_payload(): void
    {
        Http::fake([
            'http://localhost:1234/api/v1/models' => Http::response([
                'models' => [
                    [
                        'type' => 'llm',
                        'key' => 'google/gemma-4-e4b',
                        'display_name' => 'Gemma 4 E4B',
                        'loaded_instances' => [],
                        'max_context_length' => 262144,
                        'capabilities' => [
                            'vision' => true,
                            'trained_for_tool_use' => false,
                            'reasoning' => ['default' => 'on'],
                        ],
                    ],
                ],
            ]),
        ]);

        $system = AiSystem::factory()->create([
            'provider' => AiProvider::LmStudio->value,
            'model' => 'google/gemma-4-e4b',
            'base_url' => 'http://localhost:1234',
            'model_capabilities' => null,
        ]);

        $response = $this->actingAs($this->authenticatedUser())
            ->put(route('admin.ai.systems.update', $system), [
                'name' => 'Updated Local Gemma',
                'base_url' => 'http://localhost:1234',
                'api_version' => '',
                'max_tokens' => 8192,
                'context_length' => 16384,
                'temperature' => 0.5,
                'system_prompt' => 'Refreshed capabilities.',
                'config' => '',
                'credentials' => '',
                'auth_type' => 'none',
                'endpoint_type' => 'local',
                'stream_protocol' => 'chunked-json',
                'system_prompt_mode' => 'messages',
                'supports_tools' => false,
                'allowed_tools' => [],
                'supports_json_mode' => false,
                'is_local_endpoint' => true,
                'pricing_profile' => '',
                'is_active' => true,
                'feature_defaults' => [],
            ]);

        $response->assertRedirect(route('admin.ai.systems.index'));

        $system->refresh();

        $this->assertSame('Updated Local Gemma', $system->name);
        $this->assertEquals([
            'reasoning' => true,
            'vision' => true,
            'tools' => false,
            'max_context_length' => 262144,
        ], $system->model_capabilities);
    }
}
