<?php

namespace Tests\Feature;

use Generator;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Jvjvjv\CodeTalker\Models\AiChatBot;
use Jvjvjv\CodeTalker\Models\AiSystem;
use Jvjvjv\CodeTalker\Services\AiChatBotConversationService;
use Jvjvjv\CodeTalker\Services\AiMemoryService;
use Jvjvjv\CodeTalker\Services\LaravelAi\AgentFactory;
use Jvjvjv\CodeTalker\Services\LaravelAi\CodeTalkerAgent;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\Usage;
use Laravel\Ai\Responses\StreamableAgentResponse;
use Laravel\Ai\Streaming\Events\StreamEnd;
use Laravel\Ai\Streaming\Events\StreamStart;
use Laravel\Ai\Streaming\Events\TextDelta;
use Mockery;
use Tests\TestCase;

class AiChatBotConversationServiceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_first_user_message_becomes_conversation_title(): void
    {
        $bot = AiChatBot::factory()->create();

        $this->bindAgentReturning($this->fakeStream());

        $service = app(AiChatBotConversationService::class);

        $conversation = $service->startConversation($bot);

        iterator_to_array($service->continueConversation($conversation, 'Can you summarize Jason\'s backend leadership experience for a recruiter?'));

        $this->assertSame(
            'Can you summarize Jason\'s backend leadership experience for a recruiter?',
            $conversation->fresh()->title,
        );
    }

    public function test_continue_conversation_syncs_usage_after_successful_response(): void
    {
        $bot = AiChatBot::factory()->create([
            'ai_system_id' => AiSystem::factory()->create([
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

        $this->bindAgentReturning($this->usageAwareStream());

        $service = app(AiChatBotConversationService::class);

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

    /**
     * Swap the container's AgentFactory for one returning an agent that streams
     * the given canned response, and stub out memory lookups.
     */
    private function bindAgentReturning(StreamableAgentResponse $response): void
    {
        $agent = Mockery::mock(CodeTalkerAgent::class);
        $agent->shouldReceive('messages')->andReturn([]);
        $agent->shouldReceive('stream')->once()->andReturn($response);
        $agent->shouldReceive('append')->never();

        $agentFactory = Mockery::mock(AgentFactory::class);
        $agentFactory->shouldReceive('forSystem')->once()->andReturn($agent);

        $memoryService = Mockery::mock(AiMemoryService::class);
        $memoryService->shouldReceive('getMemoriesForPrompt')->once()->andReturn('');
        $memoryService->shouldReceive('processCompletedConversation')->zeroOrMoreTimes();

        $this->app->instance(AgentFactory::class, $agentFactory);
        $this->app->instance(AiMemoryService::class, $memoryService);
    }

    private function fakeStream(): StreamableAgentResponse
    {
        return $this->streamOf(
            'Jason has led engineering teams and shipped backend systems.',
            new Usage(promptTokens: 0, completionTokens: 0),
        );
    }

    private function usageAwareStream(): StreamableAgentResponse
    {
        return $this->streamOf(
            'Jason has built backend systems.',
            new Usage(promptTokens: 1200, completionTokens: 300),
        );
    }

    private function streamOf(string $text, Usage $usage): StreamableAgentResponse
    {
        return new StreamableAgentResponse(
            'id-1',
            static function () use ($text, $usage): Generator {
                yield new StreamStart('id-1', 'anthropic', 'claude-sonnet-4-6', time());
                yield new TextDelta('e1', 'm1', $text, time());
                yield new StreamEnd('id-1', 'stop', $usage, time());
            },
            new Meta(provider: 'anthropic', model: 'claude-sonnet-4-6'),
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }
}
