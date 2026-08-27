<?php

namespace Tests\Feature;

use App\Models\User;
use BSPDX\Keystone\Models\KeystonePermission as Permission;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Jvjvjv\CodeTalker\Enums\AiProvider;
use Jvjvjv\CodeTalker\Models\AiSystem;
use Tests\TestCase;

class AiSystemDuplicationTest extends TestCase
{
    use DatabaseTransactions;

    private function authenticatedUser(): User
    {
        Permission::firstOrCreate(['name' => 'manage-ai-tools']);
        $user = User::factory()->create();
        $user->givePermissionTo('manage-ai-tools');

        return $user;
    }

    public function test_duplicating_a_system_marks_it_as_pending_its_first_edit(): void
    {
        $system = AiSystem::factory()->create(['duplicated_at' => null]);

        $response = $this->actingAs($this->authenticatedUser())
            ->post(route('admin.ai.systems.duplicate', $system));

        $clone = AiSystem::query()->where('name', $system->name.' (copy)')->firstOrFail();

        $response->assertRedirect(route('admin.ai.systems.edit', $clone));
        $this->assertNotNull($clone->duplicated_at);
    }

    public function test_updating_a_pending_duplicate_accepts_new_provider_model_and_api_key(): void
    {
        $original = AiSystem::factory()->create([
            'provider' => AiProvider::Anthropic->value,
            'model' => 'claude-sonnet-4-6',
            'api_key' => 'sk-ant-original',
            'duplicated_at' => null,
        ]);
        $clone = AiSystem::factory()->create([
            'provider' => $original->provider,
            'model' => $original->model,
            'api_key' => $original->api_key,
            'duplicated_at' => now(),
        ]);

        $response = $this->actingAs($this->authenticatedUser())
            ->put(route('admin.ai.systems.update', $clone), [
                'name' => $clone->name,
                'provider' => AiProvider::OpenAI->value,
                'api_key' => 'sk-openai-new',
                'model' => 'gpt-4o',
                'base_url' => '',
                'api_version' => '',
                'max_tokens' => 4096,
                'context_length' => null,
                'temperature' => 0.7,
                'config' => '',
                'credentials' => '',
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
            ]);

        $response->assertRedirect(route('admin.ai.systems.index'));

        $clone->refresh();

        $this->assertSame(AiProvider::OpenAI->value, $clone->provider);
        $this->assertSame('gpt-4o', $clone->model);
        $this->assertSame('sk-openai-new', $clone->api_key);
        $this->assertNull($clone->duplicated_at);
    }

    public function test_resubmitting_a_pending_duplicates_unchanged_copied_api_key_keeps_it_intact(): void
    {
        // Edit's initial form data is pre-filled with the clone's actual
        // (copied) api_key — there is no blank/"leave untouched" convention
        // in this form — so submitting without touching the field resends
        // the same real value, and it must round-trip unchanged.
        $clone = AiSystem::factory()->create([
            'provider' => AiProvider::Anthropic->value,
            'model' => 'claude-sonnet-4-6',
            'api_key' => 'sk-ant-copied-from-original',
            'duplicated_at' => now(),
        ]);

        $this->actingAs($this->authenticatedUser())
            ->put(route('admin.ai.systems.update', $clone), [
                'name' => $clone->name,
                'provider' => $clone->provider,
                'api_key' => $clone->api_key,
                'model' => $clone->model,
                'base_url' => '',
                'api_version' => '',
                'max_tokens' => 4096,
                'context_length' => null,
                'temperature' => 0.7,
                'config' => '',
                'credentials' => '',
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
            ]);

        $clone->refresh();

        $this->assertSame('sk-ant-copied-from-original', $clone->api_key);
    }

    public function test_a_second_update_after_the_first_locks_provider_and_model_again(): void
    {
        $clone = AiSystem::factory()->create([
            'provider' => AiProvider::Anthropic->value,
            'model' => 'claude-sonnet-4-6',
            'duplicated_at' => now(),
        ]);

        $user = $this->authenticatedUser();

        $this->actingAs($user)->put(route('admin.ai.systems.update', $clone), [
            'name' => $clone->name,
            'provider' => AiProvider::Anthropic->value,
            'api_key' => 'sk-ant-kept',
            'model' => 'claude-sonnet-4-6',
            'base_url' => '',
            'api_version' => '',
            'max_tokens' => 4096,
            'context_length' => null,
            'temperature' => 0.7,
            'config' => '',
            'credentials' => '',
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
        ]);

        $clone->refresh();
        $this->assertNull($clone->duplicated_at);

        $this->actingAs($user)->put(route('admin.ai.systems.update', $clone), [
            'name' => $clone->name,
            'provider' => AiProvider::OpenAI->value,
            'model' => 'gpt-4o',
            'base_url' => '',
            'api_version' => '',
            'max_tokens' => 4096,
            'context_length' => null,
            'temperature' => 0.7,
            'config' => '',
            'credentials' => '',
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
        ]);

        $clone->refresh();

        // A non-pending update omits provider/model rules entirely, so a
        // submitted change to either is silently discarded, exactly as it
        // was for every established AiSystem before this change.
        $this->assertSame(AiProvider::Anthropic->value, $clone->provider);
        $this->assertSame('claude-sonnet-4-6', $clone->model);
    }

    public function test_updating_an_established_system_still_discards_provider_and_model_changes(): void
    {
        $system = AiSystem::factory()->create([
            'provider' => AiProvider::Anthropic->value,
            'model' => 'claude-sonnet-4-6',
            'duplicated_at' => null,
        ]);

        $response = $this->actingAs($this->authenticatedUser())
            ->put(route('admin.ai.systems.update', $system), [
                'name' => $system->name,
                'provider' => AiProvider::OpenAI->value,
                'model' => 'gpt-4o',
                'base_url' => '',
                'api_version' => '',
                'max_tokens' => 4096,
                'context_length' => null,
                'temperature' => 0.7,
                'config' => '',
                'credentials' => '',
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
            ]);

        $response->assertRedirect(route('admin.ai.systems.index'));

        $system->refresh();

        $this->assertSame(AiProvider::Anthropic->value, $system->provider);
        $this->assertSame('claude-sonnet-4-6', $system->model);
        $this->assertNull($system->duplicated_at);
    }
}
