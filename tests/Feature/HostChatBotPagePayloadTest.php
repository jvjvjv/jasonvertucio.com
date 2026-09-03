<?php

namespace Tests\Feature;

use App\Models\AiChatBot;
use App\Models\User;
use BSPDX\Keystone\Models\KeystonePermission as Permission;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Inertia\Testing\AssertableInertia as Assert;
use Jvjvjv\CodeTalker\Models\AiConversation;
use Jvjvjv\CodeTalker\Models\AiConversationMessage;
use PHPUnit\Framework\Attributes\DataProvider;
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

    public function test_chat_page_exposes_required_permission_for_a_restricted_bot(): void
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
        $response->assertInertia(fn (Assert $page) => $page
            ->component('ai/ChatBot', false)
            ->where('bot.required_permission', 'manage-ai-tools')
        );
    }

    public function test_chat_page_exposes_a_null_required_permission_for_a_public_bot(): void
    {
        $bot = AiChatBot::factory()->create([
            'required_permission' => null,
            'access_path' => AiChatBot::ACCESS_PATH_CHAT,
        ]);

        $response = $this->get(route('chat-bots.chat.show', $bot));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('ai/ChatBot', false)
            ->where('bot.required_permission', null)
        );
    }

    public function test_previous_href_preserves_a_same_host_referer(): void
    {
        $bot = AiChatBot::factory()->create([
            'required_permission' => null,
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

    #[DataProvider('fallbackRefererProvider')]
    public function test_previous_href_falls_back_to_the_index(?string $refererPath): void
    {
        $bot = AiChatBot::factory()->create([
            'required_permission' => null,
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
            'required_permission' => null,
            'access_path' => AiChatBot::ACCESS_PATH_CHAT,
        ]);

        $conversation = AiConversation::factory()->create([
            'ai_persona_id' => $bot->id,
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

    /**
     * A user holding `manage-ai-tools`, with the payload preference as given.
     */
    private function toolUser(bool $showToolPayloads): User
    {
        Permission::firstOrCreate(['name' => 'manage-ai-tools']);

        $user = User::factory()->create(['show_tool_payloads' => $showToolPayloads]);
        $user->givePermissionTo('manage-ai-tools');

        return $user;
    }

    /**
     * The tool panel as it appears when payloads are visible.
     *
     * @return array<int, array<string, mixed>>
     */
    private function panelsWithPayloads(): array
    {
        return [
            ['pretext' => '', 'tools' => ['web_search'], 'input' => ['query' => 'weather in Boise'], 'output' => 'Sunny, 72F'],
        ];
    }

    /**
     * The same panel with arguments and results stripped.
     *
     * @return array<int, array<string, mixed>>
     */
    private function panelsRedacted(): array
    {
        return [
            ['pretext' => '', 'tools' => ['web_search']],
        ];
    }

    private function assertToolPanels(string $slug, string $hash, array $expected): void
    {
        $response = $this->get(route('chat-bot.by-hash', ['slug' => $slug, 'hash' => $hash]));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('ai/ChatBot', false)
            ->where('messages.0.tool_panels', $expected)
        );
    }

    public function test_chat_page_exposes_tool_activity_for_a_permitted_opted_in_user(): void
    {
        [$bot, $hash] = $this->conversationWithToolActivity();

        $this->actingAs($this->toolUser(true));

        $this->assertToolPanels($bot->slug, $hash, $this->panelsWithPayloads());
    }

    public function test_chat_page_omits_tool_activity_for_a_message_that_used_no_tools(): void
    {
        $bot = AiChatBot::factory()->create([
            'required_permission' => null,
            'access_path' => AiChatBot::ACCESS_PATH_CHAT,
        ]);

        $conversation = AiConversation::factory()->create([
            'ai_persona_id' => $bot->id,
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

    public function test_chat_page_redacts_tool_payloads_for_a_permitted_user_who_opted_out(): void
    {
        [$bot, $hash] = $this->conversationWithToolActivity();

        $this->actingAs($this->toolUser(false));

        $this->assertToolPanels($bot->slug, $hash, $this->panelsRedacted());
    }

    public function test_chat_page_redacts_tool_payloads_for_a_user_without_the_permission(): void
    {
        [$bot, $hash] = $this->conversationWithToolActivity();

        // Preference forced on in the database — the permission is the actual
        // grant, so this user must still see nothing.
        $this->actingAs(User::factory()->create(['show_tool_payloads' => true]));

        $this->assertToolPanels($bot->slug, $hash, $this->panelsRedacted());
    }

    public function test_chat_page_redacts_tool_payloads_for_a_guest(): void
    {
        [$bot, $hash] = $this->conversationWithToolActivity();

        $this->assertToolPanels($bot->slug, $hash, $this->panelsRedacted());
    }

    public function test_revoking_the_permission_hides_tool_payloads_on_the_next_request(): void
    {
        [$bot, $hash] = $this->conversationWithToolActivity();

        $user = $this->toolUser(true);
        $this->actingAs($user);

        $this->assertToolPanels($bot->slug, $hash, $this->panelsWithPayloads());

        $user->revokePermissionTo('manage-ai-tools');

        // The stored preference is untouched; only the permission changed.
        $this->assertTrue($user->fresh()->show_tool_payloads);
        $this->assertToolPanels($bot->slug, $hash, $this->panelsRedacted());
    }
}
