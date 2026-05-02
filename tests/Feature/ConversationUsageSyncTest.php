<?php

namespace Tests\Feature;

use App\Enums\AiConversationStatus;
use App\Enums\AiInteractionStatus;
use App\Models\AiConversation;
use App\Models\AiInteractionLog;
use App\Models\AiSystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConversationUsageSyncTest extends TestCase
{
    use RefreshDatabase;

    public function testSyncCommandUpdatesUsageForRecentlyActiveConversations(): void
    {
        $system = AiSystem::factory()->create(['model' => 'claude-sonnet-4-6']);
        $conversation = AiConversation::factory()->active()->create([
            'ai_system_id' => $system->id,
            'updated_at' => now()->subMinute(),
        ]);

        AiInteractionLog::create([
            'ai_system_id' => $system->id,
            'ai_conversation_id' => $conversation->id,
            'ai_chat_bot_id' => null,
            'user_id' => null,
            'feature' => $conversation->feature,
            'input_tokens' => 1000,
            'output_tokens' => 500,
            'model' => 'claude-sonnet-4-6',
            'duration_ms' => 250,
            'status' => AiInteractionStatus::Success,
        ]);

        $this->artisan('ai:sync-conversation-usage', [
            '--minutes' => 10,
        ])->assertExitCode(0);

        $conversation->refresh();

        $this->assertSame(1000, $conversation->usage_input_tokens);
        $this->assertSame(500, $conversation->usage_output_tokens);
        $this->assertSame(1500, $conversation->usage_total_tokens);
        $this->assertSame('0.010500', (string) $conversation->usage_cost_usd);
        $this->assertNotNull($conversation->usage_synced_at);
    }

    public function testBackfillCommandPopulatesHistoricalConversationUsage(): void
    {
        $system = AiSystem::factory()->create(['model' => 'claude-sonnet-4-6']);
        $conversation = AiConversation::factory()->completed()->create([
            'ai_system_id' => $system->id,
            'status' => AiConversationStatus::Completed,
            'usage_total_tokens' => null,
        ]);

        AiInteractionLog::create([
            'ai_system_id' => $system->id,
            'ai_conversation_id' => $conversation->id,
            'ai_chat_bot_id' => null,
            'user_id' => null,
            'feature' => $conversation->feature,
            'input_tokens' => 2000,
            'output_tokens' => 1000,
            'model' => 'claude-sonnet-4-6',
            'duration_ms' => 400,
            'status' => AiInteractionStatus::Success,
        ]);

        $this->artisan('ai:backfill-conversation-usage')->assertExitCode(0);

        $conversation->refresh();

        $this->assertSame(2000, $conversation->usage_input_tokens);
        $this->assertSame(1000, $conversation->usage_output_tokens);
        $this->assertSame(3000, $conversation->usage_total_tokens);
        $this->assertSame('0.021000', (string) $conversation->usage_cost_usd);
        $this->assertNotNull($conversation->usage_synced_at);
    }

    public function testSyncCommandSkipsOlderActiveConversationsOutsideWindow(): void
    {
        $system = AiSystem::factory()->create(['model' => 'claude-sonnet-4-6']);
        $conversation = AiConversation::factory()->active()->create([
            'ai_system_id' => $system->id,
            'updated_at' => now()->subMinutes(30),
        ]);

        AiInteractionLog::create([
            'ai_system_id' => $system->id,
            'ai_conversation_id' => $conversation->id,
            'ai_chat_bot_id' => null,
            'user_id' => null,
            'feature' => $conversation->feature,
            'input_tokens' => 100,
            'output_tokens' => 100,
            'model' => 'claude-sonnet-4-6',
            'duration_ms' => 200,
            'status' => AiInteractionStatus::Success,
        ]);

        $this->artisan('ai:sync-conversation-usage', [
            '--minutes' => 5,
        ])->assertExitCode(0);

        $conversation->refresh();

        $this->assertNull($conversation->usage_total_tokens);
        $this->assertNull($conversation->usage_cost_usd);
        $this->assertNull($conversation->usage_synced_at);
    }
}
