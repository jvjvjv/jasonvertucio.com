<?php

namespace Tests\Feature;

use App\Models\User;
use BSPDX\Keystone\Models\KeystonePermission as Permission;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Inertia\Testing\AssertableInertia as Assert;
use Jvjvjv\CodeTalker\Enums\AiProvider;
use Jvjvjv\CodeTalker\Enums\AiSystemRole;
use Jvjvjv\CodeTalker\Models\AiSystem;
use Jvjvjv\CodeTalker\Models\AiSystemFeatureDefault;
use Tests\TestCase;

class AiSystemVoiceRoleTest extends TestCase
{
    use DatabaseTransactions;

    private function admin(string ...$permissions): User
    {
        $user = User::factory()->create();

        foreach (['manage-ai-tools', ...$permissions] as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
            $user->givePermissionTo($permission);
        }

        return $user;
    }

    /**
     * What the Create form submits for a voice system: the chat-only fields
     * are still present (the form keeps them in state), which the request
     * must drop rather than validate.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function voicePayload(array $overrides = []): array
    {
        return [
            'name' => 'Speaches TTS',
            'role' => AiSystemRole::Audio->value,
            'provider' => AiProvider::OpenAI->value,
            'api_key' => 'placeholder',
            'model' => 'kokoro',
            'base_url' => 'http://10.10.0.2:8000/v1',
            'api_version' => '',
            'max_tokens' => '',
            'context_length' => null,
            'temperature' => '',
            'config' => '',
            'credentials' => '',
            'auth_type' => '',
            'endpoint_type' => '',
            'stream_protocol' => '',
            'system_prompt_mode' => '',
            'supports_tools' => false,
            'allowed_tools' => [],
            'supports_json_mode' => false,
            'is_local_endpoint' => true,
            'is_active' => true,
            'default_voice' => '',
            'feature_defaults' => [],
            ...$overrides,
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function chatPayload(array $overrides = []): array
    {
        return [
            ...$this->voicePayload(),
            'name' => 'Claude',
            'role' => AiSystemRole::Chat->value,
            'provider' => AiProvider::Anthropic->value,
            'api_key' => 'sk-ant-test',
            'model' => 'claude-sonnet-4-6',
            'base_url' => '',
            'max_tokens' => 4096,
            'is_local_endpoint' => false,
            ...$overrides,
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function updatePayload(AiSystem $system, array $overrides = []): array
    {
        return $this->voicePayload([
            'name' => $system->name,
            'provider' => $system->provider,
            'api_key' => '',
            'model' => $system->model,
            'base_url' => '',
            ...$overrides,
        ]);
    }

    public function test_a_voice_system_is_created_with_its_role(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.ai.systems.store'), $this->voicePayload([
                'role' => AiSystemRole::Transcription->value,
                'name' => 'Speaches STT',
                'model' => 'Systran/faster-whisper-medium',
            ]))
            ->assertRedirect(route('admin.ai.voice-systems.index'))
            ->assertSessionHasNoErrors();

        $system = AiSystem::query()->where('name', 'Speaches STT')->firstOrFail();

        $this->assertSame(AiSystemRole::Transcription, $system->role);
        $this->assertSame(4096, $system->max_tokens);
    }

    public function test_a_system_created_without_a_role_is_a_chat_system(): void
    {
        $payload = $this->chatPayload();
        unset($payload['role']);

        $this->actingAs($this->admin())
            ->post(route('admin.ai.systems.store'), $payload)
            ->assertSessionHasNoErrors();

        $this->assertSame(AiSystemRole::Chat, AiSystem::query()->where('name', 'Claude')->firstOrFail()->role);
    }

    public function test_a_provider_that_cannot_serve_the_role_is_rejected_naming_both(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.ai.systems.store'), $this->voicePayload([
                'provider' => AiProvider::LmStudio->value,
            ]))
            ->assertSessionHasErrors(['provider' => 'The lm-studio provider cannot serve the audio role.']);

        $this->assertDatabaseMissing('ai_systems', ['name' => 'Speaches TTS']);
    }

    public function test_elevenlabs_cannot_serve_chat(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.ai.systems.store'), $this->chatPayload([
                'provider' => AiProvider::ElevenLabs->value,
            ]))
            ->assertSessionHasErrors('provider');
    }

    public function test_a_voice_system_may_leave_model_and_max_tokens_blank(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.ai.systems.store'), $this->voicePayload(['model' => '']))
            ->assertSessionHasNoErrors();

        $system = AiSystem::query()->where('name', 'Speaches TTS')->firstOrFail();

        $this->assertSame('', $system->model);
        $this->assertSame(4096, $system->max_tokens);
    }

    public function test_a_chat_system_still_requires_model_and_max_tokens(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.ai.systems.store'), $this->chatPayload(['model' => '', 'max_tokens' => '']))
            ->assertSessionHasErrors(['model', 'max_tokens']);
    }

    public function test_chat_only_fields_are_not_stored_on_a_voice_system(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.ai.systems.store'), $this->voicePayload([
                'temperature' => 'not a number',
                'supports_tools' => true,
                'allowed_tools' => ['fetch-web-page'],
            ]))
            ->assertSessionHasNoErrors();

        $system = AiSystem::query()->where('name', 'Speaches TTS')->firstOrFail();

        $this->assertNull($system->temperature);
        $this->assertFalse((bool) $system->supports_tools);
        $this->assertEmpty($system->allowed_tools);
    }

    public function test_an_established_system_ignores_a_submitted_role(): void
    {
        $system = AiSystem::factory()->create(['duplicated_at' => null]);

        $this->actingAs($this->admin())
            ->put(route('admin.ai.systems.update', $system), $this->updatePayload($system, [
                'role' => AiSystemRole::Audio->value,
                'max_tokens' => 2048,
            ]))
            ->assertSessionHasNoErrors();

        $system->refresh();

        $this->assertSame(AiSystemRole::Chat, $system->role);
        $this->assertSame(2048, $system->max_tokens);
    }

    public function test_a_pending_duplicate_accepts_a_role(): void
    {
        $system = AiSystem::factory()->create(['duplicated_at' => now()]);

        $this->actingAs($this->admin())
            ->put(route('admin.ai.systems.update', $system), $this->updatePayload($system, [
                'role' => AiSystemRole::Transcription->value,
                'provider' => AiProvider::OpenAI->value,
                'api_key' => 'placeholder',
                'model' => '',
            ]))
            ->assertSessionHasNoErrors();

        $system->refresh();

        $this->assertSame(AiSystemRole::Transcription, $system->role);
        $this->assertSame(AiProvider::OpenAI->value, $system->provider);
        $this->assertNull($system->duplicated_at);
    }

    public function test_a_pending_duplicate_cannot_take_a_role_its_provider_cannot_serve(): void
    {
        $system = AiSystem::factory()->create(['duplicated_at' => now()]);

        $this->actingAs($this->admin())
            ->put(route('admin.ai.systems.update', $system), $this->updatePayload($system, [
                'role' => AiSystemRole::Audio->value,
            ]))
            ->assertSessionHasErrors(['provider' => 'The anthropic provider cannot serve the audio role.']);
    }

    public function test_a_voice_system_can_hold_its_own_voice_default(): void
    {
        AiSystem::factory()->transcription()->create()
            ->featureDefaults()->create(['feature' => 'voice:dictation']);

        $this->actingAs($this->admin())
            ->post(route('admin.ai.systems.store'), $this->voicePayload([
                'role' => AiSystemRole::Transcription->value,
                'name' => 'Speaches STT',
                'feature_defaults' => ['voice:dictation'],
            ]))
            ->assertSessionHasNoErrors();

        $system = AiSystem::query()->where('name', 'Speaches STT')->firstOrFail();

        $this->assertSame($system->id, AiSystem::defaultForFeature('voice:dictation')?->id);
        $this->assertSame(1, AiSystemFeatureDefault::query()->where('feature', 'voice:dictation')->count());
    }

    public function test_an_audio_system_cannot_hold_a_chat_default(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.ai.systems.store'), $this->voicePayload([
                'feature_defaults' => ['targeted-resume'],
            ]))
            ->assertSessionHasErrors('feature_defaults.0');

        $this->assertDatabaseMissing('ai_systems', ['name' => 'Speaches TTS']);
    }

    public function test_a_chat_system_cannot_hold_a_voice_default(): void
    {
        $system = AiSystem::factory()->create(['duplicated_at' => null]);

        $this->actingAs($this->admin())
            ->put(route('admin.ai.systems.update', $system), $this->updatePayload($system, [
                'max_tokens' => 4096,
                'feature_defaults' => ['voice:speech'],
            ]))
            ->assertSessionHasErrors('feature_defaults.0');

        $this->assertNull(AiSystem::defaultForFeature('voice:speech'));
    }

    public function test_the_default_voice_round_trips_without_disturbing_other_config(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.ai.systems.store'), $this->voicePayload([
                'config' => '{"speed": 1.1}',
                'default_voice' => 'af_heart',
            ]))
            ->assertSessionHasNoErrors();

        $system = AiSystem::query()->where('name', 'Speaches TTS')->firstOrFail();
        $this->assertSame(['speed' => 1.1, 'voice' => 'af_heart'], $system->config);

        $this->actingAs($this->admin())
            ->put(route('admin.ai.systems.update', $system), $this->updatePayload($system, [
                'config' => '{"speed": 1.1}',
                'default_voice' => 'bf_emma',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame(['speed' => 1.1, 'voice' => 'bf_emma'], $system->refresh()->config);

        $this->actingAs($this->admin())
            ->put(route('admin.ai.systems.update', $system), $this->updatePayload($system, [
                'config' => '{"speed": 1.1}',
                'default_voice' => '',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame(['speed' => 1.1], $system->refresh()->config);
    }

    public function test_a_default_voice_is_refused_for_other_roles(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.ai.systems.store'), $this->voicePayload([
                'role' => AiSystemRole::Transcription->value,
                'default_voice' => 'af_heart',
            ]))
            ->assertSessionHasErrors('default_voice');
    }

    public function test_voice_systems_are_not_offered_as_a_persona_backing_system(): void
    {
        $chat = AiSystem::factory()->create(['name' => 'Chat system']);
        AiSystem::factory()->audio()->create(['name' => 'Voice system']);

        $this->actingAs($this->admin())
            ->get(route('admin.ai.bots.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('systems', fn ($systems): bool => collect($systems)->pluck('id')->contains($chat->id)
                    && ! collect($systems)->pluck('name')->contains('Voice system'))
            );
    }

    public function test_voice_systems_are_not_listed_on_new_session(): void
    {
        $chat = AiSystem::factory()->create(['name' => 'Chat system']);
        $chat->featureDefaults()->create(['feature' => 'targeted-resume']);
        AiSystem::factory()->transcription()->create(['name' => 'Voice system'])
            ->featureDefaults()->create(['feature' => 'voice:dictation']);

        $this->actingAs($this->admin('edit-resume'))
            ->get(route('admin.resume.applications.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('systems', fn ($systems): bool => collect($systems)->pluck('id')->contains($chat->id)
                    && ! collect($systems)->pluck('name')->contains('Voice system'))
            );
    }

    public function test_the_ai_systems_index_lists_chat_systems_only(): void
    {
        $audio = AiSystem::factory()->audio()->create();
        $chat = AiSystem::factory()->create();

        $this->actingAs($this->admin())
            ->get(route('admin.ai.systems.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('systems', fn ($systems): bool => collect($systems)->pluck('id')->contains($chat->id)
                    && ! collect($systems)->pluck('id')->contains($audio->id))
            );
    }

    public function test_the_voice_systems_index_lists_voice_systems_with_their_role(): void
    {
        $audio = AiSystem::factory()->audio()->create();
        $transcription = AiSystem::factory()->transcription()->create();
        $chat = AiSystem::factory()->create();

        $this->actingAs($this->admin())
            ->get(route('admin.ai.voice-systems.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ai/voice-systems/Index', false)
                ->where('systems', function ($systems) use ($audio, $transcription, $chat): bool {
                    $roles = collect($systems)->pluck('role', 'id');

                    return $roles->count() === 2
                        && $roles[$audio->id] === 'audio'
                        && $roles[$transcription->id] === 'transcription'
                        && ! $roles->has($chat->id);
                })
            );
    }

    public function test_the_voice_pages_require_manage_ai_tools(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.ai.voice-systems.index'))
            ->assertForbidden();
    }

    public function test_a_voice_system_opened_from_ai_systems_redirects_and_a_chat_system_is_not_a_voice_system(): void
    {
        $audio = AiSystem::factory()->audio()->create();
        $chat = AiSystem::factory()->create();
        $admin = $this->admin();

        $this->actingAs($admin)
            ->get(route('admin.ai.systems.edit', $audio))
            ->assertRedirect(route('admin.ai.voice-systems.edit', $audio->id));

        $this->actingAs($admin)
            ->get(route('admin.ai.voice-systems.edit', $chat->id))
            ->assertNotFound();

        $this->actingAs($admin)
            ->get(route('admin.ai.voice-systems.edit', $audio->id))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ai/voice-systems/Edit', false)
                ->where('aiSystem.id', $audio->id));
    }

    public function test_saving_a_voice_system_returns_to_the_voice_systems_list(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.ai.systems.store'), $this->voicePayload())
            ->assertRedirect(route('admin.ai.voice-systems.index'));

        $system = AiSystem::query()->where('name', 'Speaches TTS')->firstOrFail();

        $this->actingAs($this->admin())
            ->put(route('admin.ai.systems.update', $system), $this->updatePayload($system))
            ->assertRedirect(route('admin.ai.voice-systems.index'));
    }

    public function test_the_voice_form_can_omit_config_and_keeps_other_stored_keys(): void
    {
        $system = AiSystem::factory()->audio()->create(['config' => ['speed' => 1.1, 'voice' => 'af_heart']]);

        // The voice form sends only its own fields, never `config`.
        $this->actingAs($this->admin())
            ->put(route('admin.ai.systems.update', $system), [
                'name' => $system->name,
                'provider' => $system->provider,
                'api_key' => '',
                'clear_api_key' => false,
                'model' => $system->model,
                'base_url' => $system->base_url,
                'default_voice' => 'bf_emma',
                'is_active' => true,
                'feature_defaults' => ['voice:speech'],
            ])
            ->assertSessionHasNoErrors();

        $system->refresh();

        $this->assertSame(['speed' => 1.1, 'voice' => 'bf_emma'], $system->config);
        $this->assertSame($system->id, AiSystem::defaultForFeature('voice:speech')?->id);
    }
}
