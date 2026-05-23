<?php

namespace Tests\Feature;

use App\Contracts\AiClientContract;
use App\Contracts\CanLoadModels;
use App\Contracts\ResumeDataServiceContract;
use App\Models\AiChatBot;
use App\Models\AiSystem;
use App\Services\AiChatBotConversationService;
use App\Services\AiClientFactory;
use App\Services\AiMemoryService;
use App\Services\AiModelReadinessService;
use App\Services\ConversationUsageService;
use App\Services\TargetedResumeService;
use Generator;
use Illuminate\Foundation\Testing\DatabaseTransactions;
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

        $client = Mockery::mock(AiClientContract::class);
        $client->shouldReceive('withSystem')->once()->andReturnSelf();
        $client->shouldReceive('withMaxTokens')->once()->andReturnSelf();
        $client->shouldReceive('withTemperature')->once()->with(0.35)->andReturnSelf();
        $client->shouldReceive('withTools')->never();
        $client->shouldReceive('stream')->once()->andReturn($this->fakeStream());

        $clientFactory = Mockery::mock(AiClientFactory::class);
        $clientFactory->shouldReceive('forSystem')->once()->andReturn($client);

        $memoryService = Mockery::mock(AiMemoryService::class);
        $memoryService->shouldReceive('getMemoriesForPrompt')->once()->andReturn('');

        $resumeDataService = Mockery::mock(ResumeDataServiceContract::class);
        $targetedResumeService = Mockery::mock(TargetedResumeService::class);

        $service = new AiChatBotConversationService(
            $clientFactory,
            $memoryService,
            new ConversationUsageService(),
            $resumeDataService,
            $targetedResumeService,
        );

        $conversation = $service->startConversation($bot->fresh());

        $chunks = iterator_to_array($service->continueConversation($conversation, 'Tell me about Jason.'));

        $this->assertNotEmpty($chunks);
    }

    public function test_warm_up_chat_bot_uses_context_length_override(): void
    {
        $system = new AiSystem([
            'provider' => 'lm-studio',
            'model' => 'openai/gpt-oss-20b',
            'context_length' => 4096,
        ]);

        $bot = new AiChatBot([
            'context_length' => 8192,
        ]);
        $bot->setRelation('aiSystem', $system);

        $client = new class implements AiClientContract, CanLoadModels {
            public bool $loaded = false;
            public ?int $loadedContextLength = null;

            public function withSystem(string $system): self { return $this; }
            public function withModel(string $model): self { return $this; }
            public function withMaxTokens(int $maxTokens): self { return $this; }
            public function withTemperature(float $temperature): self { return $this; }
            public function withTools(array $tools): self { return $this; }
            public function message(array $messages): array { return []; }
            public function stream(array $messages): Generator { if (false) { yield []; } }
            public function listModels(): array { return []; }
            public function formatAssistantToolCallTurn(string $textContent, array $toolCalls): array { return []; }
            public function formatToolResultTurn(array $toolResults): array { return []; }
            public function isModelLoaded(string $model): bool { return $this->loaded; }
            public function loadModel(string $model, ?int $contextLength = null): array {
                $this->loaded = true;
                $this->loadedContextLength = $contextLength;

                return [
                    'status' => 'loaded',
                    'instance_id' => $model,
                    'load_time_seconds' => 0.1,
                ];
            }
        };

        $clientFactory = Mockery::mock(AiClientFactory::class);
        $clientFactory->shouldReceive('forSystem')->atLeast()->once()->andReturn($client);

        $service = new AiModelReadinessService($clientFactory);

        $status = $service->warmUpChatBot($bot);

        $this->assertTrue($status['warmup_attempted']);
        $this->assertSame('loaded', $status['state']);
        $this->assertSame(8192, $client->loadedContextLength);
    }

    private function fakeStream(): Generator
    {
        yield [
            'type' => 'message_start',
            'message' => [
                'usage' => ['input_tokens' => 10],
            ],
        ];
        yield [
            'type' => 'content_block_delta',
            'delta' => ['text' => 'Jason builds backend systems.'],
        ];
        yield [
            'type' => 'message_delta',
            'usage' => ['output_tokens' => 20],
        ];
        yield ['type' => 'message_stop'];
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }
}
