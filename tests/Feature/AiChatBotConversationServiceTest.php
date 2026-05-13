<?php

namespace Tests\Feature;

use App\Contracts\ResumeDataServiceContract;
use App\Models\AiChatBot;
use App\Services\AiChatBotConversationService;
use App\Services\AiClientFactory;
use App\Services\AiMemoryService;
use App\Services\ClaudeService;
use App\Services\ConversationUsageService;
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

        $resumeDataService = Mockery::mock(ResumeDataServiceContract::class);

        $service = new AiChatBotConversationService($clientFactory, $memoryService, new ConversationUsageService(), $resumeDataService);

        $conversation = $service->startConversation($bot);

        iterator_to_array($service->continueConversation($conversation, 'Can you summarize Jason\'s backend leadership experience for a recruiter?'));

        $this->assertSame(
            'Can you summarize Jason\'s backend leadership experience for a recruiter?',
            $conversation->fresh()->title,
        );
    }

    public function test_continue_conversation_syncs_usage_after_successful_response(): void {
        $bot = AiChatBot::factory()->create([
            'ai_system_id' => \App\Models\AiSystem::factory()->create([
                'pricing_profile' => [
                    'models' => [
                        'claude-sonnet-4-6' => [
                            'input_per_million' => 50.00,
                            'output_per_million' => 100.00,
                        ],
                    ],
                ],
            ])->id,
        ]);

        $client = Mockery::mock(ClaudeService::class);
        $client->shouldReceive('withSystem')->once()->andReturnSelf();
        $client->shouldReceive('withMaxTokens')->once()->andReturnSelf();
        $client->shouldReceive('stream')->once()->andReturn($this->usageAwareStream());

        $clientFactory = Mockery::mock(AiClientFactory::class);
        $clientFactory->shouldReceive('forSystem')->once()->andReturn($client);

        $memoryService = Mockery::mock(AiMemoryService::class);
        $memoryService->shouldReceive('getMemoriesForPrompt')->once()->andReturn('');

        $resumeDataService = Mockery::mock(ResumeDataServiceContract::class);

        $service = new AiChatBotConversationService($clientFactory, $memoryService, new ConversationUsageService(), $resumeDataService);

        $conversation = $service->startConversation($bot->fresh());

        iterator_to_array($service->continueConversation($conversation, 'Tell me about Jason.'));

        $conversation->refresh();

        $this->assertSame(1200, $conversation->usage_input_tokens);
        $this->assertSame(300, $conversation->usage_output_tokens);
        $this->assertSame(1500, $conversation->usage_total_tokens);
        $this->assertSame('0.090000', (string) $conversation->usage_cost_usd);
        $this->assertNotNull($conversation->usage_synced_at);

        $this->assertDatabaseHas('ai_interaction_logs', [
            'ai_conversation_id' => $conversation->id,
            'status' => 'success',
            'input_tokens' => 1200,
            'output_tokens' => 300,
            'input_token_price_snapshot' => '0.00005000',
            'output_token_price_snapshot' => '0.00010000',
        ]);
    }

    private function fakeStream(): Generator
    {
        yield [
            'type' => 'content_block_delta',
            'delta' => ['text' => 'Jason has led engineering teams and shipped backend systems.'],
        ];
        yield ['type' => 'message_stop'];
    }

    private function usageAwareStream(): Generator {
        yield [
            'type' => 'message_start',
            'message' => [
                'usage' => ['input_tokens' => 1200],
            ],
        ];
        yield [
            'type' => 'content_block_delta',
            'delta' => ['text' => 'Jason has built backend systems.'],
        ];
        yield [
            'type' => 'message_delta',
            'usage' => ['output_tokens' => 300],
        ];
        yield ['type' => 'message_stop'];
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }
}
