<?php

namespace Tests\Feature;

use App\Models\AiChatBot;
use App\Models\AiConversation;
use App\Models\AiConversationMessage;
use App\Models\AiFeatureMemory;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AiConversationControllerTest extends TestCase
{
    use DatabaseTransactions;

    private function authenticatedUser(): User
    {
        Permission::findOrCreate('manage-ai-tools', 'web');
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
}
