<?php

namespace Tests\Feature;

use App\Models\AiChatBot;
use App\Models\User;
use BSPDX\Keystone\Models\KeystoneRole as Role;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Inertia\Testing\AssertableInertia as Assert;
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
}
