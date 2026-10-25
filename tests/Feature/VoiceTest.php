<?php

namespace Tests\Feature;

use App\Models\AiChatBot;
use App\Models\User;
use App\Services\Voice\SpeechCache;
use BSPDX\Keystone\Models\KeystonePermission as Permission;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Jvjvjv\CodeTalker\Models\AiSystem;
use Laravel\Ai\Audio;
use Laravel\Ai\Prompts\AudioPrompt;
use Laravel\Ai\Prompts\TranscriptionPrompt;
use Laravel\Ai\Transcription;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class VoiceTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Cache::flush();
        config(['voice.transcription_language' => '']);
    }

    private function voiceUser(string ...$permissions): User
    {
        $user = User::factory()->create();

        foreach (['use-voice', ...$permissions] as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
            $user->givePermissionTo($permission);
        }

        return $user;
    }

    private function speechSystem(array $attributes = []): AiSystem
    {
        $system = AiSystem::factory()->audio()->create($attributes);
        $system->featureDefaults()->create(['feature' => 'voice:speech']);

        return $system;
    }

    private function dictationSystem(array $attributes = []): AiSystem
    {
        $system = AiSystem::factory()->transcription()->create($attributes);
        $system->featureDefaults()->create(['feature' => 'voice:dictation']);

        return $system;
    }

    private function recording(string $mime = 'audio/webm'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('dictation', 'recorded-audio')->mimeType($mime);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function endpointProvider(): array
    {
        return [
            'status' => ['get', '/api/admin/voice/status'],
            'transcriptions' => ['post', '/api/admin/voice/transcriptions'],
            'speech' => ['post', '/api/admin/voice/speech'],
        ];
    }

    #[DataProvider('endpointProvider')]
    public function test_a_user_without_use_voice_is_refused(string $method, string $uri): void
    {
        Permission::firstOrCreate(['name' => 'use-voice']);

        $this->actingAs(User::factory()->create())
            ->json($method, $uri)
            ->assertForbidden();
    }

    #[DataProvider('endpointProvider')]
    public function test_a_guest_is_refused(string $method, string $uri): void
    {
        $this->json($method, $uri)->assertUnauthorized();
    }

    public function test_the_voice_prop_is_null_for_a_guest_on_a_persona_page(): void
    {
        $bot = AiChatBot::factory()->create([
            'required_permission' => null,
            'access_path' => AiChatBot::ACCESS_PATH_CHAT,
        ]);

        $this->get(route('chat-bots.chat.show', $bot))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('voice', null));
    }

    public function test_the_voice_prop_is_null_without_the_permission(): void
    {
        Permission::firstOrCreate(['name' => 'use-voice']);
        Permission::firstOrCreate(['name' => 'manage-ai-tools']);
        $user = User::factory()->create();
        $user->givePermissionTo('manage-ai-tools');

        $bot = AiChatBot::factory()->create([
            'required_permission' => null,
            'access_path' => AiChatBot::ACCESS_PATH_CHAT,
        ]);

        $this->actingAs($user)
            ->get(route('chat-bots.chat.show', $bot))
            ->assertInertia(fn (Assert $page) => $page->where('voice', null));

        $this->actingAs($user)
            ->get(route('admin.ai.systems.index'))
            ->assertInertia(fn (Assert $page) => $page->where('voice', null));
    }

    public function test_a_holder_receives_the_voice_endpoints_on_persona_and_admin_pages(): void
    {
        $user = $this->voiceUser('manage-ai-tools');
        $bot = AiChatBot::factory()->create([
            'required_permission' => null,
            'access_path' => AiChatBot::ACCESS_PATH_CHAT,
        ]);

        $expected = fn (Assert $page) => $page
            ->where('voice.statusUrl', '/api/admin/voice/status')
            ->where('voice.transcribeUrl', '/api/admin/voice/transcriptions')
            ->where('voice.speechUrl', '/api/admin/voice/speech')
            ->where('voice.maxRecordingSeconds', config('voice.max_recording_seconds'))
            ->where('voice.maxSpeechChars', config('voice.max_speech_chars'));

        $this->actingAs($user)->get(route('chat-bots.chat.show', $bot))->assertInertia($expected);
        $this->actingAs($user)->get(route('admin.ai.systems.index'))->assertInertia($expected);
    }

    public function test_status_reports_not_configured_without_defaults(): void
    {
        $this->actingAs($this->voiceUser())
            ->getJson('/api/admin/voice/status')
            ->assertOk()
            ->assertExactJson([
                'dictation' => ['available' => false, 'reason' => 'not_configured'],
                'speech' => ['available' => false, 'reason' => 'not_configured'],
            ]);
    }

    public function test_status_reports_an_inactive_default(): void
    {
        $this->speechSystem(['is_active' => false]);

        $this->actingAs($this->voiceUser())
            ->getJson('/api/admin/voice/status')
            ->assertJsonPath('speech', ['available' => false, 'reason' => 'inactive']);
    }

    public function test_status_reports_an_unreachable_server_and_caches_the_probe(): void
    {
        $this->speechSystem();
        $probes = 0;
        Http::fake(function () use (&$probes): never {
            $probes++;

            throw new ConnectionException('cURL error 28: timed out');
        });

        $user = $this->voiceUser();
        $this->actingAs($user)
            ->getJson('/api/admin/voice/status')
            ->assertJsonPath('speech', ['available' => false, 'reason' => 'unreachable']);
        $this->actingAs($user)->getJson('/api/admin/voice/status');

        $this->assertSame(1, $probes);
    }

    public function test_status_reports_available_when_the_server_answers(): void
    {
        $this->speechSystem();
        $this->dictationSystem();
        Http::fake(['10.10.0.2:8000/v1/models' => Http::response(['data' => []])]);

        $this->actingAs($this->voiceUser())
            ->getJson('/api/admin/voice/status')
            ->assertExactJson([
                'dictation' => ['available' => true, 'reason' => null],
                'speech' => ['available' => true, 'reason' => null],
            ]);
    }

    public function test_status_does_not_probe_a_cloud_system(): void
    {
        $this->speechSystem(['base_url' => null]);
        Http::fake();

        $this->actingAs($this->voiceUser())
            ->getJson('/api/admin/voice/status')
            ->assertJsonPath('speech', ['available' => true, 'reason' => null]);

        Http::assertNothingSent();
    }

    public function test_dictation_returns_the_transcript(): void
    {
        $this->dictationSystem();
        Transcription::fake(['  add a bullet about the migration project ']);

        $this->actingAs($this->voiceUser())
            ->post('/api/admin/voice/transcriptions', ['audio' => $this->recording()], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertExactJson(['text' => 'add a bullet about the migration project']);

        Transcription::assertGenerated(fn (TranscriptionPrompt $prompt): bool => $prompt->language === null);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_a_safari_recording_is_accepted(): void
    {
        $this->dictationSystem();
        Transcription::fake(['hello']);

        $this->actingAs($this->voiceUser())
            ->post('/api/admin/voice/transcriptions', ['audio' => $this->recording('audio/mp4')], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('text', 'hello');
    }

    public function test_the_configured_language_is_sent_when_the_request_names_none(): void
    {
        $this->dictationSystem();
        config(['voice.transcription_language' => 'en']);
        Transcription::fake(['hello']);

        $this->actingAs($this->voiceUser())
            ->post('/api/admin/voice/transcriptions', ['audio' => $this->recording()], ['Accept' => 'application/json'])
            ->assertOk();

        Transcription::assertGenerated(fn (TranscriptionPrompt $prompt): bool => $prompt->language === 'en');
    }

    public function test_an_oversize_recording_is_refused(): void
    {
        $this->dictationSystem();
        config(['voice.max_upload_kb' => 50]);
        Transcription::fake();

        $this->actingAs($this->voiceUser())
            ->post('/api/admin/voice/transcriptions', ['audio' => UploadedFile::fake()->create('dictation.webm', 51, 'audio/webm')], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('audio');

        Transcription::assertNothingGenerated();
    }

    public function test_a_non_audio_upload_is_refused(): void
    {
        $this->dictationSystem();
        Transcription::fake();

        $this->actingAs($this->voiceUser())
            ->post('/api/admin/voice/transcriptions', [
                'audio' => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf'),
            ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('audio');

        Transcription::assertNothingGenerated();
    }

    public function test_speech_is_generated_once_and_then_served_from_the_cache(): void
    {
        $this->speechSystem();
        $calls = 0;
        Audio::fake(function () use (&$calls): string {
            $calls++;

            return base64_encode('mp3-bytes');
        });

        $user = $this->voiceUser();

        foreach ([1, 2] as $attempt) {
            $response = $this->actingAs($user)
                ->postJson('/api/admin/voice/speech', ['text' => '**Strong fit** for the role'])
                ->assertOk()
                ->assertHeader('Content-Type', 'audio/mpeg');

            $this->assertSame('mp3-bytes', $response->streamedContent());
        }

        $this->assertSame(1, $calls);
        Audio::assertGenerated(fn (AudioPrompt $prompt): bool => $prompt->text === 'Strong fit for the role'
            && $prompt->voice === 'af_heart');
    }

    public function test_changing_the_default_voice_misses_the_cache(): void
    {
        $system = $this->speechSystem();
        $voices = [];
        Audio::fake(function (AudioPrompt $prompt) use (&$voices): string {
            $voices[] = $prompt->voice;

            return base64_encode('mp3-bytes');
        });

        $user = $this->voiceUser();
        $this->actingAs($user)->postJson('/api/admin/voice/speech', ['text' => 'Hello there'])->assertOk();

        $system->update(['config' => ['voice' => 'bf_emma']]);
        $this->actingAs($user)->postJson('/api/admin/voice/speech', ['text' => 'Hello there'])->assertOk();

        $this->assertSame(['af_heart', 'bf_emma'], $voices);
    }

    public function test_empty_or_over_length_speech_is_refused(): void
    {
        $this->speechSystem();
        config(['voice.max_speech_chars' => 20]);
        Audio::fake();
        $user = $this->voiceUser();

        $this->actingAs($user)
            ->postJson('/api/admin/voice/speech', ['text' => '***'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('text');

        $this->actingAs($user)
            ->postJson('/api/admin/voice/speech', ['text' => str_repeat('word ', 10)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('text');

        Audio::assertNothingGenerated();
    }

    public function test_an_unreachable_speech_server_is_a_generic_503(): void
    {
        $this->speechSystem();
        Http::fake(fn () => throw new ConnectionException('cURL error 7: Failed to connect to 10.10.0.2 port 8000: Connection refused'));

        $response = $this->actingAs($this->voiceUser())
            ->postJson('/api/admin/voice/speech', ['text' => 'Hello there'])
            ->assertServiceUnavailable()
            ->assertExactJson(['message' => 'Voice is unavailable.']);

        $this->assertStringNotContainsString('10.10.0.2', $response->getContent());
        $this->assertStringNotContainsString('Connection refused', $response->getContent());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_a_failure_is_logged_without_url_credentials_or_query_strings(): void
    {
        $this->speechSystem(['base_url' => 'http://speech-user:hunter2@10.10.0.2:18820/v1']);
        Http::fake(fn () => throw new ConnectionException(
            'cURL error 7: Failed to connect to http://speech-user:hunter2@10.10.0.2:18820/v1/audio/speech?key=sk-secret-123'
        ));
        Log::spy();

        $this->actingAs($this->voiceUser())
            ->postJson('/api/admin/voice/speech', ['text' => 'Hello there'])
            ->assertServiceUnavailable();

        Log::shouldHaveReceived('warning')->withArgs(function (string $message, array $context): bool {
            $logged = json_encode($context);

            return $message === 'Voice request failed.'
                && ! str_contains($logged, 'hunter2')
                && ! str_contains($logged, 'speech-user')
                && ! str_contains($logged, 'sk-secret-123')
                && str_contains($logged, '10.10.0.2');
        })->once();
    }

    public function test_dictation_without_a_default_is_a_generic_503(): void
    {
        $this->actingAs($this->voiceUser())
            ->post('/api/admin/voice/transcriptions', ['audio' => $this->recording()], ['Accept' => 'application/json'])
            ->assertServiceUnavailable()
            ->assertExactJson(['message' => 'Voice is unavailable.']);
    }

    public function test_the_sweep_deletes_old_entries_and_keeps_new_ones(): void
    {
        config(['voice.cache_ttl_days' => 7]);
        $disk = Storage::disk('local');
        $disk->put(SpeechCache::DIRECTORY.'/old.mp3', 'old');
        $disk->put(SpeechCache::DIRECTORY.'/new.mp3', 'new');
        touch($disk->path(SpeechCache::DIRECTORY.'/old.mp3'), now()->subDays(8)->timestamp);
        touch($disk->path(SpeechCache::DIRECTORY.'/new.mp3'), now()->subDays(6)->timestamp);

        $this->artisan('voice:sweep-cache')->assertSuccessful();

        $this->assertFalse($disk->exists(SpeechCache::DIRECTORY.'/old.mp3'));
        $this->assertTrue($disk->exists(SpeechCache::DIRECTORY.'/new.mp3'));
    }
}
