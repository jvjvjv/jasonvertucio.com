<?php

namespace Tests\Feature;

use App\Enums\AiConversationStatus;
use App\Enums\AiInteractionStatus;
use App\Models\AiConversation;
use App\Models\AiConversationMessage;
use App\Models\AiInteractionLog;
use App\Models\AiSystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ConversationUsageSyncTest extends TestCase
{
    use RefreshDatabase;

    public function testSyncCommandUpdatesUsageForRecentlyActiveConversations(): void
    {
        $system = AiSystem::factory()->create(['model' => 'claude-sonnet-4-6']);
        $originalUpdatedAt = now()->subMinute()->setMicrosecond(0);
        $conversation = AiConversation::factory()->active()->create([
            'ai_system_id' => $system->id,
            'updated_at' => $originalUpdatedAt,
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

    public function testConversationUpdatedAtTracksLatestUserAndAssistantMessages(): void
    {
        $conversation = AiConversation::factory()->create([
            'updated_at' => now()->subDay(),
        ]);

        $systemMessageTimestamp = now()->subHours(2)->setMicrosecond(0);
        Carbon::setTestNow($systemMessageTimestamp);
        AiConversationMessage::create([
            'ai_conversation_id' => $conversation->id,
            'role' => 'system',
            'content' => 'System prompt',
        ]);
        Carbon::setTestNow();

        $conversation->refresh();
        $this->assertNotSame(
            $systemMessageTimestamp->toDateTimeString(),
            $conversation->updated_at?->toDateTimeString(),
        );

        $userMessageTimestamp = now()->subHour()->setMicrosecond(0);
        Carbon::setTestNow($userMessageTimestamp);
        AiConversationMessage::create([
            'ai_conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => 'Hello there',
        ]);
        Carbon::setTestNow();

        $conversation->refresh();
        $this->assertSame(
            $userMessageTimestamp->toDateTimeString(),
            $conversation->updated_at?->toDateTimeString(),
        );

        $assistantMessageTimestamp = now()->subMinutes(30)->setMicrosecond(0);
        Carbon::setTestNow($assistantMessageTimestamp);
        AiConversationMessage::create([
            'ai_conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => 'Hi back!',
        ]);
        Carbon::setTestNow();

        $conversation->refresh();
        $this->assertSame(
            $assistantMessageTimestamp->toDateTimeString(),
            $conversation->updated_at?->toDateTimeString(),
        );
    }

    public function testSyncConversationUpdatedAtCommandRepairsConversationTimestamp(): void
    {
        $conversation = AiConversation::factory()->create([
            'updated_at' => now()->subDay(),
        ]);

        $latestMessageTimestamp = now()->subMinutes(15)->setMicrosecond(0);
        Carbon::setTestNow($latestMessageTimestamp);
        AiConversationMessage::create([
            'ai_conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => 'Latest message',
        ]);
        Carbon::setTestNow();
        $message = AiConversationMessage::query()
            ->where('ai_conversation_id', $conversation->id)
            ->latest('id')
            ->firstOrFail();

        AiConversation::withoutTimestamps(function () use ($conversation): void {
            $conversation->forceFill(['updated_at' => now()->subDays(2)])->save();
        });

        $this->artisan('ai:sync-conversation-updated-at', [
            '--conversation-id' => $conversation->id,
        ])->assertExitCode(0);

        $conversation->refresh();

        $this->assertSame(
            $message->created_at?->toDateTimeString(),
            $conversation->updated_at?->toDateTimeString(),
        );
    }

    public function testSyncConversationUpdatedAtCommandSupportsDryRun(): void
    {
        $conversation = AiConversation::factory()->create([
            'updated_at' => now()->subDay(),
        ]);

        $latestMessageTimestamp = now()->subMinutes(10)->setMicrosecond(0);
        Carbon::setTestNow($latestMessageTimestamp);
        AiConversationMessage::create([
            'ai_conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => 'Dry run message',
        ]);
        Carbon::setTestNow();
        $message = AiConversationMessage::query()
            ->where('ai_conversation_id', $conversation->id)
            ->latest('id')
            ->firstOrFail();

        AiConversation::withoutTimestamps(function () use ($conversation): void {
            $conversation->forceFill(['updated_at' => now()->subDays(3)])->save();
        });

        $before = $conversation->fresh()?->updated_at?->toDateTimeString();

        $this->artisan('ai:sync-conversation-updated-at', [
            '--conversation-id' => $conversation->id,
            '--dry-run' => true,
        ])->assertExitCode(0);

        $after = $conversation->fresh()?->updated_at?->toDateTimeString();

        $this->assertSame($before, $after);
        $this->assertNotSame($message->created_at?->toDateTimeString(), $after);
    }
}
