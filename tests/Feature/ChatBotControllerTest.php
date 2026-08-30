<?php

namespace Tests\Feature;

use App\Models\AiChatBot;
use App\Models\User;
use BSPDX\Keystone\Models\KeystonePermission as Permission;
use Generator;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Jvjvjv\CodeTalker\Models\AiConversation;
use Jvjvjv\CodeTalker\Models\AiConversationMessage;
use Jvjvjv\CodeTalker\Services\AiPersonaConversationService;
use Jvjvjv\CodeTalker\Services\AiModelReadinessService;
use Mockery;
use Tests\TestCase;

class ChatBotControllerTest extends TestCase
{
    use DatabaseTransactions;

    public function test_chats_index_for_guests_only_includes_public_chatbots(): void
    {
        AiChatBot::factory()->create([
            'name' => 'Public Bot',
            'is_active' => true,
            'required_permission' => null,
        ]);

        AiChatBot::factory()->create([
            'name' => 'Private Bot',
            'is_active' => true,
            'required_permission' => 'manage-ai-tools',
        ]);

        AiChatBot::factory()->create([
            'name' => 'Inactive Public Bot',
            'is_active' => false,
            'required_permission' => null,
        ]);

        $response = $this->get(route('chat-bots.index'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('ai/ChatBotsIndex', false)
            ->has('bots', 1)
            ->where('bots.0.name', 'Public Bot')
            ->where('bots.0.conversations', [])
        );
    }

    public function test_chats_index_for_authenticated_users_includes_accessible_private_bots_and_sorts_conversations_descending(): void
    {
        Permission::firstOrCreate(['name' => 'manage-ai-tools']);
        $user = User::factory()->create();
        $user->givePermissionTo('manage-ai-tools');

        $publicBot = AiChatBot::factory()->create([
            'name' => 'Public Bot',
            'is_active' => true,
            'required_permission' => null,
        ]);

        $privateAllowedBot = AiChatBot::factory()->create([
            'name' => 'Private Allowed Bot',
            'is_active' => true,
            'required_permission' => 'manage-ai-tools',
        ]);

        AiChatBot::factory()->create([
            'name' => 'Private Denied Bot',
            'is_active' => true,
            'required_permission' => 'edit-resume',
        ]);

        $olderConversationId = DB::table('ai_conversations')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'public_id' => (string) Str::ulid(),
            'user_id' => $user->id,
            'ai_system_id' => $privateAllowedBot->ai_system_id,
            'ai_persona_id' => $privateAllowedBot->id,
            'feature' => $privateAllowedBot->featureKey(),
            'title' => 'Older Conversation',
            'status' => 'active',
            'context' => json_encode([]),
            'created_at' => now()->subDays(4),
            'updated_at' => now()->subDays(4),
        ]);

        $newerConversationId = DB::table('ai_conversations')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'public_id' => (string) Str::ulid(),
            'user_id' => $user->id,
            'ai_system_id' => $privateAllowedBot->ai_system_id,
            'ai_persona_id' => $privateAllowedBot->id,
            'feature' => $privateAllowedBot->featureKey(),
            'title' => 'Newer Conversation',
            'status' => 'active',
            'context' => json_encode([]),
            'created_at' => now()->subHours(2),
            'updated_at' => now()->subHours(2),
        ]);

        AiConversationMessage::query()->create([
            'ai_conversation_id' => $olderConversationId,
            'role' => 'assistant',
            'content' => 'An older message',
            'created_at' => now()->subDays(3),
            'updated_at' => now()->subDays(3),
        ]);

        AiConversationMessage::query()->create([
            'ai_conversation_id' => $newerConversationId,
            'role' => 'assistant',
            'content' => 'A newer message',
            'created_at' => now()->subHour(),
            'updated_at' => now()->subHour(),
        ]);

        $response = $this->actingAs($user)->get(route('chat-bots.index'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('ai/ChatBotsIndex', false)
            ->has('bots', 2)
            ->where('bots.0.name', 'Private Allowed Bot')
            ->where('bots.0.conversations.0.title', 'Newer Conversation')
            ->where('bots.0.conversations.1.title', 'Older Conversation')
            ->where('bots.1.name', 'Public Bot')
        );
    }

    public function test_guest_can_view_public_bot(): void
    {
        $bot = AiChatBot::factory()->create([
            'name' => 'Guest Bot',
            'required_permission' => null,
            'access_path' => AiChatBot::ACCESS_PATH_CHAT,
        ]);

        $response = $this->get(route('chat-bots.chat.show', $bot));

        $response->assertOk();
        $response->assertSee('Guest Bot');
    }

    public function test_chats_statuses_endpoint_returns_statuses_for_accessible_bots(): void
    {
        $publicBot = AiChatBot::factory()->create([
            'name' => 'Public Bot',
            'required_permission' => null,
        ]);

        AiChatBot::factory()->create([
            'name' => 'Private Bot',
            'required_permission' => 'manage-ai-tools',
        ]);

        $readiness = Mockery::mock(AiModelReadinessService::class);
        $readiness->shouldReceive('statusForSystem')
            ->once()
            ->andReturnUsing(fn (): array => $this->loadedStatus('anthropic', 'claude-sonnet-4-6'));

        $this->app->instance(AiModelReadinessService::class, $readiness);

        $response = $this->get(route('chat-bots.statuses'));

        $response->assertOk();
        $response->assertJsonPath('statuses.'.$publicBot->slug.'.state', 'loaded');
    }

    public function test_guest_can_request_public_bot_status_endpoint(): void
    {
        $bot = AiChatBot::factory()->create([
            'required_permission' => null,
            'access_path' => AiChatBot::ACCESS_PATH_CHAT,
        ]);

        $readiness = Mockery::mock(AiModelReadinessService::class);
        $readiness->shouldReceive('statusForPersona')
            ->once()
            ->andReturnUsing(fn (): array => $this->loadedStatus('openai-compatible', 'deepseek-r1-distill'));

        $this->app->instance(AiModelReadinessService::class, $readiness);

        $response = $this->get(route('chat-bots.chat.status', $bot));

        $response->assertOk();
        $response->assertJsonPath('status.state', 'loaded');
    }

    public function test_guest_can_request_public_bot_warmup_endpoint(): void
    {
        $bot = AiChatBot::factory()->create([
            'required_permission' => null,
            'access_path' => AiChatBot::ACCESS_PATH_CHAT,
        ]);

        $readiness = Mockery::mock(AiModelReadinessService::class);
        $readiness->shouldReceive('warmUpPersona')
            ->once()
            ->andReturnUsing(fn (): array => $this->loadedStatus('openai-compatible', 'deepseek-r1-distill') + [
                'warmup_attempted' => true,
            ]);

        $this->app->instance(AiModelReadinessService::class, $readiness);

        $response = $this->post(route('chat-bots.chat.warmup', $bot));

        $response->assertOk();
        $response->assertJsonPath('status.state', 'loaded');
        $response->assertJsonPath('status.warmup_attempted', true);
    }

    public function test_guest_can_view_public_root_bot(): void
    {
        $bot = AiChatBot::factory()->create([
            'name' => 'Root Guest Bot',
            'required_permission' => null,
            'access_path' => AiChatBot::ACCESS_PATH_ROOT,
        ]);

        $response = $this->get(route('chat-bots.root.show', $bot));

        $response->assertOk();
        $response->assertSee('Root Guest Bot');
    }

    public function test_guest_cannot_view_private_bot(): void
    {
        $bot = AiChatBot::factory()->create([
            'required_permission' => 'manage-ai-tools',
            'access_path' => AiChatBot::ACCESS_PATH_CHAT,
        ]);

        $response = $this->get(route('chat-bots.chat.show', $bot));

        $response->assertForbidden();
    }

    public function test_authenticated_user_with_required_permission_can_view_private_bot(): void
    {
        Permission::firstOrCreate(['name' => 'manage-ai-tools']);
        $user = User::factory()->create();
        $user->givePermissionTo('manage-ai-tools');

        $bot = AiChatBot::factory()->create([
            'required_permission' => 'manage-ai-tools',
            'access_path' => AiChatBot::ACCESS_PATH_CHAT,
        ]);

        $response = $this->actingAs($user)->get(route('chat-bots.chat.show', $bot));

        $response->assertOk();
    }

    public function test_guest_cannot_view_authenticated_only_bot(): void
    {
        $bot = AiChatBot::factory()->create([
            'required_permission' => AiChatBot::PERMISSION_AUTHENTICATED,
            'access_path' => AiChatBot::ACCESS_PATH_CHAT,
        ]);

        $response = $this->get(route('chat-bots.chat.show', $bot));

        $response->assertForbidden();
    }

    public function test_any_authenticated_user_can_view_authenticated_only_bot(): void
    {
        $user = User::factory()->create();

        $bot = AiChatBot::factory()->create([
            'required_permission' => AiChatBot::PERMISSION_AUTHENTICATED,
            'access_path' => AiChatBot::ACCESS_PATH_CHAT,
        ]);

        $response = $this->actingAs($user)->get(route('chat-bots.chat.show', $bot));

        $response->assertOk();
    }

    public function test_wrong_entry_point_returns_not_found(): void
    {
        $bot = AiChatBot::factory()->create([
            'required_permission' => null,
            'access_path' => AiChatBot::ACCESS_PATH_ROOT,
        ]);

        $response = $this->get(route('chat-bots.chat.show', $bot));

        $response->assertNotFound();
    }

    public function test_first_guest_message_requires_identity_when_configured(): void
    {
        $bot = AiChatBot::factory()->create([
            'required_permission' => null,
            'require_visitor_identity' => true,
            'access_path' => AiChatBot::ACCESS_PATH_CHAT,
        ]);

        $response = $this->postJson(route('chat-bots.chat.message', $bot), [
            'message' => 'Hello there',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name', 'email']);
    }

    public function test_message_endpoint_creates_session_conversation_and_streams(): void
    {
        $bot = AiChatBot::factory()->create([
            'required_permission' => null,
            'require_visitor_identity' => false,
            'access_path' => AiChatBot::ACCESS_PATH_ROOT,
        ]);

        $conversation = AiConversation::factory()->create([
            'user_id' => null,
            'ai_system_id' => $bot->ai_system_id,
            'ai_persona_id' => $bot->id,
            'feature' => $bot->featureKey(),
        ]);

        $service = Mockery::mock(AiPersonaConversationService::class);
        $service->shouldReceive('startConversation')
            ->once()
            ->andReturn($conversation);
        $service->shouldReceive('continueConversation')
            ->zeroOrMoreTimes()
            ->andReturn($this->fakeStream());
        // ChatBotController::message() calls this only for a user holding
        // manage-ai-tools with show_tool_payloads on — a guest never triggers
        // it, but permitted-user tests do, so the mock must allow either.
        $service->shouldReceive('usingToolPayloads')
            ->zeroOrMoreTimes()
            ->andReturnSelf();

        $this->app->instance(AiPersonaConversationService::class, $service);

        $response = $this->post(route('chat-bots.root.message', $bot), [
            'message' => 'Start talking',
        ]);

        $response->assertOk();

        // Per-bot cookies were consolidated into one `ai_chat_bot_current`
        // cookie to keep the request header from growing with each bot. The
        // per-bot state itself still lives in the server-side session.
        $response->assertCookie('ai_chat_bot_current', $conversation->public_id);
        $this->assertEquals($conversation->public_id, session('ai_chat_bot_conversations_'.$bot->id.'.current'));
        $this->assertCount(1, session('ai_chat_bot_conversations_'.$bot->id.'.history', []));
    }

    public function test_switch_endpoint_sets_current_conversation_from_session_history(): void
    {
        $bot = AiChatBot::factory()->create([
            'required_permission' => null,
            'access_path' => AiChatBot::ACCESS_PATH_ROOT,
        ]);

        $firstConversation = AiConversation::factory()->create([
            'user_id' => null,
            'ai_system_id' => $bot->ai_system_id,
            'ai_persona_id' => $bot->id,
            'feature' => $bot->featureKey(),
        ]);

        $secondConversation = AiConversation::factory()->create([
            'user_id' => null,
            'ai_system_id' => $bot->ai_system_id,
            'ai_persona_id' => $bot->id,
            'feature' => $bot->featureKey(),
        ]);

        $response = $this
            ->withSession([
                'ai_chat_bot_conversations_'.$bot->id => [
                    'current' => $firstConversation->public_id,
                    'history' => [
                        ['handle' => 'first-chat', 'public_id' => $firstConversation->public_id],
                        ['handle' => 'second-chat', 'public_id' => $secondConversation->public_id],
                    ],
                ],
            ])
            ->post(route('chat-bots.root.switch', $bot), [
                'conversation' => 'second-chat',
            ]);

        $response->assertRedirect(route('chat-bots.root.show', $bot));
        $this->assertEquals($secondConversation->public_id, session('ai_chat_bot_conversations_'.$bot->id.'.current'));
    }

    public function test_new_chat_preserves_history_but_clears_current_conversation(): void
    {
        $bot = AiChatBot::factory()->create([
            'required_permission' => null,
            'access_path' => AiChatBot::ACCESS_PATH_ROOT,
        ]);

        $conversation = AiConversation::factory()->create([
            'user_id' => null,
            'ai_system_id' => $bot->ai_system_id,
            'ai_persona_id' => $bot->id,
            'feature' => $bot->featureKey(),
        ]);

        $response = $this
            ->withSession([
                'ai_chat_bot_conversations_'.$bot->id => [
                    'current' => $conversation->public_id,
                    'history' => [
                        ['handle' => 'chat-one', 'public_id' => $conversation->public_id],
                    ],
                ],
            ])
            ->post(route('chat-bots.root.reset', $bot));

        $response->assertRedirect(route('chat-bots.root.show', $bot));
        $this->assertNull(session('ai_chat_bot_conversations_'.$bot->id.'.current'));
        $this->assertCount(1, session('ai_chat_bot_conversations_'.$bot->id.'.history', []));
    }

    private function fakeStream(): Generator
    {
        yield "data: [DONE]\n\n";
    }

    /**
     * @return array{state: string, provider: string, model: string, message: string, checked_at: string}
     */
    private function loadedStatus(string $provider, string $model): array
    {
        return [
            'state' => 'loaded',
            'provider' => $provider,
            'model' => $model,
            'message' => 'Model is available.',
            'checked_at' => now()->toIso8601String(),
        ];
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }
}
