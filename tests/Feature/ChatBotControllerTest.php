<?php

namespace Tests\Feature;

use App\Models\AiChatBot;
use App\Models\AiConversation;
use App\Models\User;
use App\Services\AiChatBotConversationService;
use Generator;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Mockery;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ChatBotControllerTest extends TestCase
{
    use DatabaseTransactions;

    public function test_guest_can_view_public_bot(): void
    {
        $bot = AiChatBot::factory()->create([
            'name' => 'Guest Bot',
            'is_public' => true,
        ]);

        $response = $this->get(route('chat-bots.show', $bot));

        $response->assertOk();
        $response->assertSeeText('Guest Bot');
    }

    public function test_guest_cannot_view_private_bot(): void
    {
        $bot = AiChatBot::factory()->create([
            'is_public' => false,
        ]);

        $response = $this->get(route('chat-bots.show', $bot));

        $response->assertForbidden();
    }

    public function test_authenticated_user_with_allowed_role_can_view_private_bot(): void
    {
        Role::findOrCreate('editor', 'web');
        $user = User::factory()->create();
        $user->assignRole('editor');

        $bot = AiChatBot::factory()->create([
            'is_public' => false,
            'allowed_roles' => ['editor'],
        ]);

        $response = $this->actingAs($user)->get(route('chat-bots.show', $bot));

        $response->assertOk();
    }

    public function test_first_guest_message_requires_identity_when_configured(): void
    {
        $bot = AiChatBot::factory()->create([
            'is_public' => true,
            'require_visitor_identity' => true,
        ]);

        $response = $this->postJson(route('chat-bots.message', $bot), [
            'message' => 'Hello there',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name', 'email']);
    }

    public function test_message_endpoint_creates_session_conversation_and_streams(): void
    {
        $bot = AiChatBot::factory()->create([
            'is_public' => true,
            'require_visitor_identity' => false,
        ]);

        $conversation = AiConversation::factory()->create([
            'user_id' => null,
            'ai_system_id' => $bot->ai_system_id,
            'ai_chat_bot_id' => $bot->id,
            'feature' => $bot->featureKey(),
        ]);

        $service = Mockery::mock(AiChatBotConversationService::class);
        $service->shouldReceive('startConversation')
            ->once()
            ->andReturn($conversation);
        $service->shouldReceive('continueConversation')
            ->zeroOrMoreTimes()
            ->andReturn($this->fakeStream());

        $this->app->instance(AiChatBotConversationService::class, $service);

        $response = $this->post(route('chat-bots.message', $bot), [
            'message' => 'Start talking',
        ]);

        $response->assertOk();
        $this->assertEquals($conversation->id, session('ai_chat_bot_conversation.' . $bot->id));
    }

    private function fakeStream(): Generator
    {
        yield "data: [DONE]\n\n";
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }
}
