<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Jvjvjv\CodeTalker\Enums\AiConversationStatus;
use Jvjvjv\CodeTalker\Enums\AiInteractionStatus;
use Jvjvjv\CodeTalker\Models\AiConversation;
use Jvjvjv\CodeTalker\Models\AiConversationMessage;
use Jvjvjv\CodeTalker\Models\AiInteractionLog;
use Jvjvjv\CodeTalker\Models\AiSystem;
use Tests\TestCase;

class ConversationUsageSyncTest extends TestCase
{
    use DatabaseTransactions;

    public function test_sync_command_updates_usage_for_recently_active_conversations(): void
    {
        $system = AiSystem::factory()->create(['model' => 'claude-sonnet-4-6']);
        $originalUpdatedAt = now()->subMinute()->setMicrosecond(0);
        $conversation = AiConversation::factory()->active()->create([
            'ai_system_id' => $system->id,
            'updated_at' => $originalUpdatedAt,
        ]);

        $messageTimestamp = now()->subMinute()->setMicrosecond(0);
        AiConversationMessage::create([
            'ai_conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => 'Recent assistant response',
            'created_at' => $messageTimestamp,
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
        $this->assertSame(
            $originalUpdatedAt->toDateTimeString(),
            $conversation->updated_at?->toDateTimeString(),
        );
    }

    public function test_backfill_command_populates_historical_conversation_usage(): void
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

    public function test_backfill_command_uses_system_pricing_profile_override(): void
    {
        $system = AiSystem::factory()->create([
            'model' => 'claude-sonnet-4-6',
            'pricing_profile' => [
                'models' => [
                    'claude-sonnet-4-6' => [
                        'input_per_million' => 500.00,
                        'output_per_million' => 1000.00,
                    ],
                ],
            ],
        ]);
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
        $this->assertSame('2.000000', (string) $conversation->usage_cost_usd);
    }

    public function test_backfill_command_uses_interaction_price_snapshots_when_available(): void
    {
        $system = AiSystem::factory()->create([
            'model' => 'claude-sonnet-4-6',
            'pricing_profile' => [
                'models' => [
                    'claude-sonnet-4-6' => [
                        'input_per_million' => 1.00,
                        'output_per_million' => 1.00,
                    ],
                ],
            ],
        ]);
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
            'input_token_price_snapshot' => 0.001,
            'output_token_price_snapshot' => 0.002,
            'duration_ms' => 400,
            'status' => AiInteractionStatus::Success,
        ]);

        $this->artisan('ai:backfill-conversation-usage')->assertExitCode(0);

        $conversation->refresh();

        $this->assertSame('4.000000', (string) $conversation->usage_cost_usd);
    }

    public function test_sync_command_skips_older_active_conversations_outside_window(): void
    {
        $system = AiSystem::factory()->create(['model' => 'claude-sonnet-4-6']);
        $conversation = AiConversation::factory()->active()->create([
            'ai_system_id' => $system->id,
            'updated_at' => now()->subMinutes(30),
        ]);

        $oldMessageTimestamp = now()->subMinutes(30)->setMicrosecond(0);
        AiConversationMessage::create([
            'ai_conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => 'Old assistant response',
            'created_at' => $oldMessageTimestamp,
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

    public function test_conversation_last_message_at_tracks_latest_user_and_assistant_messages(): void
    {
        $conversation = AiConversation::factory()->create([
            'updated_at' => now()->subDay(),
        ]);

        $systemMessageTimestamp = now()->subHours(2)->setMicrosecond(0);
        AiConversationMessage::create([
            'ai_conversation_id' => $conversation->id,
            'role' => 'system',
            'content' => 'System prompt',
            'created_at' => $systemMessageTimestamp,
        ]);

        $conversation->refresh();
        $this->assertNull($conversation->last_message_at);
        $this->assertNotSame(
            $systemMessageTimestamp->toDateTimeString(),
            $conversation->updated_at?->toDateTimeString(),
        );

        $userMessageTimestamp = now()->subHour()->setMicrosecond(0);
        AiConversationMessage::create([
            'ai_conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => 'Hello there',
            'created_at' => $userMessageTimestamp,
        ]);

        $conversation->refresh();
        $this->assertSame(
            $userMessageTimestamp->toDateTimeString(),
            $conversation->last_message_at?->toDateTimeString(),
        );

        $assistantMessageTimestamp = now()->subMinutes(30)->setMicrosecond(0);
        AiConversationMessage::create([
            'ai_conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => 'Hi back!',
            'created_at' => $assistantMessageTimestamp,
        ]);

        $conversation->refresh();
        $this->assertSame(
            $assistantMessageTimestamp->toDateTimeString(),
            $conversation->last_message_at?->toDateTimeString(),
        );
    }
}
