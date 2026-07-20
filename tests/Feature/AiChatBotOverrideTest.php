<?php

namespace Tests\Feature;

use Generator;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Jvjvjv\CodeTalker\Models\AiChatBot;
use Jvjvjv\CodeTalker\Models\AiSystem;
use Jvjvjv\CodeTalker\Services\AiChatBotConversationService;
use Jvjvjv\CodeTalker\Services\AiMemoryService;
use Jvjvjv\CodeTalker\Services\AiModelReadinessService;
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

class AiChatBotOverrideTest extends TestCase
{
    use DatabaseTransactions;

    public function test_chat_bot_resolves_system_settings_when_overrides_are_blank(): void
    {
        $system = new AiSystem([
            'provider' => 'lm-studio',
            'model' => 'openai/gpt-oss-20b',
            'temperature' => 0.55,
            'context_length' => 16384,
        ]);

        $bot = new AiChatBot([
            'temperature' => null,
            'context_length' => null,
        ]);
        $bot->setRelation('aiSystem', $system);

        $this->assertSame(0.55, $bot->resolvedTemperature());
        $this->assertSame(16384, $bot->resolvedContextLength());
    }

    public function test_continue_conversation_uses_chat_bot_temperature_override(): void
    {
        $bot = AiChatBot::factory()->create([
            'temperature' => 0.35,
            'ai_system_id' => AiSystem::factory()->create([
                'temperature' => 0.70,
            ])->id,
        ]);

        $agent = Mockery::mock(CodeTalkerAgent::class);
        $agent->shouldReceive('messages')->andReturn([]);
        $agent->shouldReceive('stream')->once()->andReturn($this->fakeStream());
        $agent->shouldReceive('append')->never();

        // The bot's 0.35 must win over the system's 0.70 when the agent is built.
        $agentFactory = Mockery::mock(AgentFactory::class);
        $agentFactory->shouldReceive('forSystem')
            ->once()
            ->with(Mockery::any(), Mockery::any(), Mockery::any(), Mockery::any(), Mockery::any(), 0.35)
            ->andReturn($agent);

        $memoryService = Mockery::mock(AiMemoryService::class);
        $memoryService->shouldReceive('getMemoriesForPrompt')->once()->andReturn('');
        $memoryService->shouldReceive('processCompletedConversation')->zeroOrMoreTimes();

        $this->app->instance(AgentFactory::class, $agentFactory);
        $this->app->instance(AiMemoryService::class, $memoryService);

        $service = app(AiChatBotConversationService::class);

        $conversation = $service->startConversation($bot->fresh());

        $chunks = iterator_to_array($service->continueConversation($conversation, 'Tell me about Jason.'));

        $this->assertNotEmpty($chunks);
    }

    public function test_warm_up_chat_bot_uses_context_length_override(): void
    {
        // The model reports as unloaded until /models/load is called, so the
        // warm-up path actually runs instead of short-circuiting.
        $loaded = false;

        Http::fake(function ($request) use (&$loaded) {
            if (str_contains($request->url(), '/api/v1/models/load')) {
                $loaded = true;

                return Http::response([
                    'status' => 'loaded',
                    'instance_id' => 'openai/gpt-oss-20b',
                    'load_time_seconds' => 0.1,
                ]);
            }

            return Http::response([
                'models' => [
                    [
                        'type' => 'llm',
                        'key' => 'openai/gpt-oss-20b',
                        'display_name' => 'GPT OSS 20B',
                        'loaded_instances' => $loaded ? [['id' => 'instance-1']] : [],
                    ],
                ],
            ]);
        });

        $system = AiSystem::factory()->create([
            'provider' => 'lm-studio',
            'model' => 'openai/gpt-oss-20b',
            'base_url' => 'http://localhost:1234',
            'context_length' => 4096,
        ]);

        $bot = AiChatBot::factory()->create([
            'ai_system_id' => $system->id,
            'context_length' => 8192,
        ]);

        $service = app(AiModelReadinessService::class);

        $status = $service->warmUpChatBot($bot->fresh());

        $this->assertTrue($status['warmup_attempted']);
        $this->assertSame('loaded', $status['state']);

        // The bot's 8192 override must reach LM Studio, not the system's 4096.
        Http::assertSent(function ($request): bool {
            return str_contains($request->url(), '/api/v1/models/load')
                && $request['context_length'] === 8192;
        });
    }

    private function fakeStream(): StreamableAgentResponse
    {
        return new StreamableAgentResponse(
            'id-1',
            static function (): Generator {
                yield new StreamStart('id-1', 'lm-studio', 'openai/gpt-oss-20b', time());
                yield new TextDelta('e1', 'm1', 'Jason builds backend systems.', time());
                yield new StreamEnd(
                    'id-1',
                    'stop',
                    new Usage(promptTokens: 10, completionTokens: 20),
                    time(),
                );
            },
            new Meta(provider: 'lm-studio', model: 'openai/gpt-oss-20b'),
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }
}
