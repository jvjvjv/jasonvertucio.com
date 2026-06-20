<?php

namespace Tests\Feature;

use App\Models\User;
use BSPDX\Keystone\Models\KeystonePermission as Permission;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Jvjvjv\CodeTalker\Jobs\BackfillConversationUsageJob;
use Jvjvjv\CodeTalker\Models\AiChatBot;
use Jvjvjv\CodeTalker\Models\AiConversation;
use Jvjvjv\CodeTalker\Models\AiConversationMessage;
use Jvjvjv\CodeTalker\Models\AiFeatureMemory;
use Tests\TestCase;

class AiConversationControllerTest extends TestCase
{
    use DatabaseTransactions;

    private function authenticatedUser(): User
    {
        Permission::firstOrCreate(['name' => 'manage-ai-tools']);
        $user = User::factory()->create();
        $user->givePermissionTo('manage-ai-tools');

        return $user;
    }

    public function test_index_lists_conversations_across_features(): void
    {
        $user = $this->authenticatedUser();
        $bot = AiChatBot::factory()->create(['name' => 'Public Intake']);
        $conversation = AiConversation::factory()->create([
            'ai_chat_bot_id' => $bot->id,
            'feature' => $bot->featureKey(),
            'title' => 'Conversation about contact form',
            'visitor_name' => 'Taylor',
        ]);

        AiConversationMessage::query()->create([
            'ai_conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => 'Hello from the bot.',
        ]);

        $response = $this->actingAs($user)->get(route('admin.ai.conversations.index'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('ai/conversations/Index', false)
            ->has('conversations.data', 1)
            ->where('conversations.data.0.title', 'Conversation about contact form')
            ->where('conversations.data.0.visitor_name', 'Taylor')
            ->where('conversations.data.0.ai_chat_bot_name', 'Public Intake')
        );
    }

    public function test_show_displays_messages_and_memories(): void
    {
        $user = $this->authenticatedUser();
        $conversation = AiConversation::factory()->create([
            'title' => 'Memory review',
            'feature' => 'chat-bot:memory-review',
        ]);

        AiConversationMessage::query()->create([
            'ai_conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => 'A saved response.',
        ]);

        AiFeatureMemory::factory()->create([
            'feature' => 'chat-bot:memory-review',
            'key' => 'prefers-concise-tone',
            'content' => 'Prefers concise replies.',
            'source_conversation_id' => $conversation->id,
        ]);

        $response = $this->actingAs($user)->get(route('admin.ai.conversations.show', $conversation));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('ai/conversations/Show', false)
            ->where('conversation.title', 'Memory review')
            ->where('messages.0.content', 'A saved response.')
            ->where('memories.0.key', 'prefers-concise-tone')
        );
    }

    public function test_queue_usage_backfill_dispatches_job(): void
    {
        Queue::fake();
        $user = $this->authenticatedUser();

        $response = $this->actingAs($user)
            ->post(route('admin.ai.conversations.backfill-usage'));

        $response->assertRedirect(route('admin.ai.conversations.index'));
        Queue::assertPushed(BackfillConversationUsageJob::class, function (BackfillConversationUsageJob $job): bool {
            return $job->all === false && $job->chunk === 200;
        });
    }

    public function test_queue_usage_backfill_can_recompute_all(): void
    {
        Queue::fake();
        $user = $this->authenticatedUser();

        $response = $this->actingAs($user)
            ->post(route('admin.ai.conversations.backfill-usage'), [
                'all' => true,
                'chunk' => 500,
            ]);

        $response->assertRedirect(route('admin.ai.conversations.index'));
        Queue::assertPushed(BackfillConversationUsageJob::class, function (BackfillConversationUsageJob $job): bool {
            return $job->all === true && $job->chunk === 500;
        });
    }
}
