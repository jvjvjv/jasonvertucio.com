<?php

namespace Tests\Feature;

use App\Models\AiChatBot;
use App\Models\User;
use BSPDX\Keystone\Models\KeystoneRole as Role;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Inertia\Testing\AssertableInertia as Assert;
use Jvjvjv\CodeTalker\Models\AiConversation;
use Jvjvjv\CodeTalker\Models\AiConversationMessage;
use Tests\TestCase;

/**
 * The two `ai/ChatBot` props the host adds on top of the package payload.
 *
 * These come from the container binding for ChatBotPagePayload rather than from
 * the controller, so an unbound or mis-merged payload would leave the rest of
 * the chat-bot suite green while the UI silently lost its access label and back
 * link. That is what these tests exist to catch.
 */
class HostChatBotPagePayloadTest extends TestCase
{
    use DatabaseTransactions;

    public function test_chat_page_exposes_allowed_roles_for_a_restricted_bot(): void
    {
        Role::firstOrCreate(['name' => 'editor']);
        $user = User::factory()->create();
        $user->assignRole('editor');

        $bot = AiChatBot::factory()->create([
            'allowed_roles' => ['editor'],
            'access_path' => AiChatBot::ACCESS_PATH_CHAT,
        ]);

        $response = $this->actingAs($user)->get(route('chat-bots.chat.show', $bot));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('ai/ChatBot', false)
            ->where('bot.allowed_roles', ['editor'])
        );
    }

    public function test_chat_page_exposes_an_empty_allowed_roles_array_for_a_public_bot(): void
    {
        $bot = AiChatBot::factory()->create([
            'allowed_roles' => [],
            'access_path' => AiChatBot::ACCESS_PATH_CHAT,
        ]);

        $response = $this->get(route('chat-bots.chat.show', $bot));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('ai/ChatBot', false)
            ->where('bot.allowed_roles', [])
        );
    }

    public function test_previous_href_preserves_a_same_host_referer(): void
    {
        $bot = AiChatBot::factory()->create([
            'allowed_roles' => [],
            'access_path' => AiChatBot::ACCESS_PATH_CHAT,
        ]);

        $referer = url('/blog');

        $response = $this->withHeader('referer', $referer)
            ->get(route('chat-bots.chat.show', $bot));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('ai/ChatBot', false)
            ->where('previousHref', $referer)
        );
    }

    /**
     * @dataProvider fallbackRefererProvider
     */
    public function test_previous_href_falls_back_to_the_index(?string $refererPath): void
    {
        $bot = AiChatBot::factory()->create([
            'allowed_roles' => [],
            'access_path' => AiChatBot::ACCESS_PATH_CHAT,
        ]);

        $url = route('chat-bots.chat.show', $bot);
        $request = $this;

        if ($refererPath !== null) {
            $referer = $refererPath === '@self' ? $url : $refererPath;
            $request = $this->withHeader('referer', $referer);
        }

        $response = $request->get($url);

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('ai/ChatBot', false)
            ->where('previousHref', route('chat-bots.index'))
        );
    }

    /**
     * @return array<string, array{0: ?string}>
     */
    public static function fallbackRefererProvider(): array
    {
        return [
            'no referer' => [null],
            'referer is this page' => ['@self'],
            'referer is another host' => ['https://elsewhere.example.com/somewhere'],
        ];
    }

    /**
     * @return array{0: AiChatBot, 1: string}
     */
    private function conversationWithToolActivity(): array
    {
        $bot = AiChatBot::factory()->create([
            'allowed_roles' => [],
            'access_path' => AiChatBot::ACCESS_PATH_CHAT,
        ]);

        $conversation = AiConversation::factory()->create([
            'ai_chat_bot_id' => $bot->id,
        ]);

        AiConversationMessage::create([
            'ai_conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => 'Here is what I found.',
            'tool_calls' => [
                ['id' => 'call_1', 'name' => 'web_search', 'arguments' => ['query' => 'weather in Boise']],
            ],
            'tool_results' => [
                ['id' => 'call_1', 'name' => 'web_search', 'arguments' => ['query' => 'weather in Boise'], 'result' => 'Sunny, 72F'],
            ],
        ]);

        return [$bot, $conversation->generateChatHash()];
    }

    public function test_chat_page_exposes_tool_activity_for_a_message_that_used_tools(): void
    {
        [$bot, $hash] = $this->conversationWithToolActivity();

        $response = $this->get(route('chat-bot.by-hash', ['slug' => $bot->slug, 'hash' => $hash]));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('ai/ChatBot', false)
            ->where('messages.0.tool_panels', [
                ['pretext' => '', 'tools' => ['web_search'], 'input' => ['query' => 'weather in Boise'], 'output' => 'Sunny, 72F'],
            ])
        );
    }

    public function test_chat_page_omits_tool_activity_for_a_message_that_used_no_tools(): void
    {
        $bot = AiChatBot::factory()->create([
            'allowed_roles' => [],
            'access_path' => AiChatBot::ACCESS_PATH_CHAT,
        ]);

        $conversation = AiConversation::factory()->create([
            'ai_chat_bot_id' => $bot->id,
        ]);

        AiConversationMessage::create([
            'ai_conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => 'Just an answer, no tools.',
        ]);

        $response = $this->get(route('chat-bot.by-hash', [
            'slug' => $bot->slug,
            'hash' => $conversation->generateChatHash(),
        ]));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('ai/ChatBot', false)
            ->where('messages.0.tool_panels', null)
        );
    }

    public function test_chat_page_redacts_tool_arguments_and_output_in_production(): void
    {
        [$bot, $hash] = $this->conversationWithToolActivity();

        $this->app->detectEnvironment(fn () => 'production');

        $response = $this->get(route('chat-bot.by-hash', ['slug' => $bot->slug, 'hash' => $hash]));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('ai/ChatBot', false)
            ->where('messages.0.tool_panels', [
                ['pretext' => '', 'tools' => ['web_search']],
            ])
        );
    }
}
