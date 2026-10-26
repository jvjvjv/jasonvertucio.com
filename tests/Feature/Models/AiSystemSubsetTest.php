<?php

namespace Tests\Feature\Models;

use App\Models\ChatSystem;
use App\Models\VoiceSystem;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Jvjvjv\CodeTalker\Enums\AiSystemRole;
use Jvjvjv\CodeTalker\Models\AiSystem;
use LogicException;
use Tests\TestCase;

class AiSystemSubsetTest extends TestCase
{
    use DatabaseTransactions;

    public function test_each_subset_reads_only_its_own_roles(): void
    {
        $chat = AiSystem::factory()->create();
        $audio = AiSystem::factory()->audio()->create();
        $transcription = AiSystem::factory()->transcription()->create();

        $chatIds = ChatSystem::query()->pluck('id');
        $voiceIds = VoiceSystem::query()->pluck('id');

        $this->assertTrue($chatIds->contains($chat->id));
        $this->assertFalse($chatIds->contains($audio->id));
        $this->assertFalse($chatIds->contains($transcription->id));

        $this->assertTrue($voiceIds->contains($audio->id));
        $this->assertTrue($voiceIds->contains($transcription->id));
        $this->assertFalse($voiceIds->contains($chat->id));

        $this->assertNull(VoiceSystem::find($chat->id));
        $this->assertNull(ChatSystem::find($audio->id));
    }

    /**
     * The package model names neither its table nor its foreign key, and
     * Eloquent derives both from the runtime class. Without the pins in
     * IsAiSystemSubset these would query `chat_systems` and `chat_system_id`.
     */
    public function test_relations_and_counts_use_the_ai_systems_keys(): void
    {
        $chat = AiSystem::factory()->create();
        $chat->featureDefaults()->create(['feature' => 'cover-letter']);
        $voice = AiSystem::factory()->transcription()->create();
        $voice->featureDefaults()->create(['feature' => 'voice:dictation']);

        $loadedChat = ChatSystem::query()
            ->withCount(['interactionLogs', 'personas as chat_bots_count'])
            ->with('featureDefaults')
            ->findOrFail($chat->id);
        $loadedVoice = VoiceSystem::query()->with('featureDefaults')->findOrFail($voice->id);

        $this->assertSame(['cover-letter'], $loadedChat->featureDefaults->pluck('feature')->all());
        $this->assertSame(0, $loadedChat->interaction_logs_count);
        $this->assertSame(['voice:dictation'], $loadedVoice->featureDefaults->pluck('feature')->all());
        $this->assertSame('ai_systems', (new VoiceSystem)->getTable());
    }

    public function test_creating_through_a_subset_sets_or_requires_the_role(): void
    {
        $chat = ChatSystem::create(AiSystem::factory()->raw(['role' => AiSystemRole::Audio]));
        $this->assertSame(AiSystemRole::Chat, AiSystem::findOrFail($chat->id)->role);

        $voice = VoiceSystem::create(AiSystem::factory()->transcription()->raw());
        $this->assertSame(AiSystemRole::Transcription, AiSystem::findOrFail($voice->id)->role);

        $this->expectException(LogicException::class);
        VoiceSystem::create(AiSystem::factory()->raw());
    }
}
