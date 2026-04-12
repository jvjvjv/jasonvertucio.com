<?php

namespace Tests\Feature;

use App\Services\AiChatBotConversationService;
use App\Services\AiClientFactory;
use App\Services\AiMemoryService;
use App\Services\ClaudeService;
use App\Models\AiChatBot;
use Generator;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Mockery;
use Tests\TestCase;

class AiChatBotConversationServiceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_first_user_message_becomes_conversation_title(): void
    {
        $bot = AiChatBot::factory()->create();

        $client = Mockery::mock(ClaudeService::class);
        $client->shouldReceive('withSystem')->once()->andReturnSelf();
        $client->shouldReceive('withMaxTokens')->once()->andReturnSelf();
        $client->shouldReceive('stream')->once()->andReturn($this->fakeStream());

        $clientFactory = Mockery::mock(AiClientFactory::class);
        $clientFactory->shouldReceive('forSystem')->once()->andReturn($client);

        $memoryService = Mockery::mock(AiMemoryService::class);
        $memoryService->shouldReceive('getMemoriesForPrompt')->once()->andReturn('');

        $service = new AiChatBotConversationService($clientFactory, $memoryService);

        $conversation = $service->startConversation($bot);

        iterator_to_array($service->continueConversation($conversation, 'Can you summarize Jason\'s backend leadership experience for a recruiter?'));

        $this->assertSame(
            'Can you summarize Jason\'s backend leadership experience for a recruiter?',
            $conversation->fresh()->title,
        );
    }

    private function fakeStream(): Generator
    {
        yield [
            'type' => 'content_block_delta',
            'delta' => ['text' => 'Jason has led engineering teams and shipped backend systems.'],
        ];
        yield ['type' => 'message_stop'];
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }
}
