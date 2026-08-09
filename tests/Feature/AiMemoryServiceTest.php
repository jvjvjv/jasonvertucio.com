<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Jvjvjv\CodeTalker\Models\AiConversation;
use Jvjvjv\CodeTalker\Models\AiConversationMessage;
use Jvjvjv\CodeTalker\Models\AiFeatureMemory;
use Jvjvjv\CodeTalker\Services\LaravelAi\AgentFactory;
use Jvjvjv\CodeTalker\Services\LaravelAi\CodeTalkerAgent;
use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\Usage;
use Jvjvjv\CodeTalker\Services\AiMemoryService;
use Tests\TestCase;

class AiMemoryServiceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_get_memories_for_prompt_returns_formatted_string(): void
    {
        $user = User::factory()->create();

        AiFeatureMemory::factory()->preference()->create([
            'feature' => 'targeted-resume',
            'content' => 'Prefers concise bullet points',
            'confidence' => 90,
            'user_id' => $user->id,
        ]);

        AiFeatureMemory::factory()->domainKnowledge()->create([
            'feature' => 'targeted-resume',
            'content' => 'Has 10 years of PHP experience',
            'confidence' => 85,
            'user_id' => $user->id,
        ]);

        $service = app(AiMemoryService::class);
        $result = $service->getMemoriesForPrompt('targeted-resume', $user->id);

        $this->assertStringContainsString('User Preferences', $result);
        $this->assertStringContainsString('Prefers concise bullet points', $result);
        $this->assertStringContainsString('Domain Knowledge', $result);
        $this->assertStringContainsString('Has 10 years of PHP experience', $result);
    }

    public function test_get_memories_for_prompt_returns_empty_for_no_memories(): void
    {
        $service = app(AiMemoryService::class);
        $result = $service->getMemoriesForPrompt('nonexistent-feature');

        $this->assertSame('', $result);
    }

    public function test_get_memories_for_prompt_excludes_inactive(): void
    {
        $user = User::factory()->create();

        AiFeatureMemory::factory()->create([
            'feature' => 'targeted-resume',
            'content' => 'Active memory',
            'is_active' => true,
            'user_id' => $user->id,
        ]);

        AiFeatureMemory::factory()->inactive()->create([
            'feature' => 'targeted-resume',
            'content' => 'Inactive memory',
            'user_id' => $user->id,
        ]);

        $service = app(AiMemoryService::class);
        $result = $service->getMemoriesForPrompt('targeted-resume', $user->id);

        $this->assertStringContainsString('Active memory', $result);
        $this->assertStringNotContainsString('Inactive memory', $result);
    }

    public function test_apply_memory_operations_creates_new_entries(): void
    {
        $conversation = AiConversation::factory()->create();

        $service = app(AiMemoryService::class);
        $service->applyMemoryOperations('targeted-resume', [
            'add' => [
                [
                    'key' => 'prefers-action-verbs',
                    'category' => 'preference',
                    'content' => 'Prefers action verbs in bullet points',
                    'confidence' => 80,
                ],
            ],
            'update' => [],
            'remove' => [],
        ], $conversation->id);

        $this->assertDatabaseHas('ai_feature_memories', [
            'feature' => 'targeted-resume',
            'key' => 'prefers-action-verbs',
            'category' => 'preference',
            'content' => 'Prefers action verbs in bullet points',
            'confidence' => 80,
            'source_conversation_id' => $conversation->id,
            'is_active' => true,
        ]);
    }

    public function test_apply_memory_operations_updates_existing_entries(): void
    {
        $conversation = AiConversation::factory()->create();

        $memory = AiFeatureMemory::factory()->create([
            'feature' => 'targeted-resume',
            'key' => 'existing-key',
            'content' => 'Old content',
            'confidence' => 50,
            'times_reinforced' => 2,
        ]);

        $service = app(AiMemoryService::class);
        $service->applyMemoryOperations('targeted-resume', [
            'add' => [],
            'update' => [
                [
                    'key' => 'existing-key',
                    'content' => 'Updated content',
                    'confidence' => 75,
                    'reinforced' => true,
                ],
            ],
            'remove' => [],
        ], $conversation->id);

        $memory->refresh();

        $this->assertEquals('Updated content', $memory->content);
        $this->assertEquals(75, $memory->confidence);
        $this->assertEquals(3, $memory->times_reinforced);
        $this->assertNotNull($memory->last_reinforced_at);
        $this->assertEquals($conversation->id, $memory->source_conversation_id);
    }

    public function test_apply_memory_operations_deactivates_removed_entries(): void
    {
        $conversation = AiConversation::factory()->create();

        $memory = AiFeatureMemory::factory()->create([
            'feature' => 'targeted-resume',
            'key' => 'to-remove',
            'is_active' => true,
        ]);

        $service = app(AiMemoryService::class);
        $service->applyMemoryOperations('targeted-resume', [
            'add' => [],
            'update' => [],
            'remove' => [
                ['key' => 'to-remove', 'reason' => 'No longer relevant'],
            ],
        ], $conversation->id);

        $this->assertFalse($memory->fresh()->is_active);
    }

    public function test_analyze_conversation_calls_ai_and_parses_response(): void
    {
        $conversation = AiConversation::factory()->create([
            'feature' => 'targeted-resume',
        ]);

        AiConversationMessage::create([
            'ai_conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => 'I prefer concise bullet points',
        ]);

        AiConversationMessage::create([
            'ai_conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => 'Noted, I will keep bullets concise.',
        ]);

        $responseJson = json_encode([
            'add' => [
                ['key' => 'concise-bullets', 'category' => 'preference', 'content' => 'Prefers concise bullets', 'confidence' => 85],
            ],
            'update' => [],
            'remove' => [],
        ]);

        $mockAgent = $this->createMock(CodeTalkerAgent::class);
        $mockAgent->method('prompt')->willReturn($this->agentResponse($responseJson));

        $mockFactory = $this->createMock(AgentFactory::class);
        $mockFactory->method('forSystem')->willReturn($mockAgent);
        $mockFactory->method('forFeature')->willReturn($mockAgent);

        $this->app->instance(AgentFactory::class, $mockFactory);

        $service = app(AiMemoryService::class);
        $operations = $service->analyzeConversation($conversation);

        $this->assertCount(1, $operations['add']);
        $this->assertEquals('concise-bullets', $operations['add'][0]['key']);
        $this->assertEmpty($operations['update']);
        $this->assertEmpty($operations['remove']);
    }

    public function test_process_completed_conversation_does_not_throw_on_failure(): void
    {
        $conversation = AiConversation::factory()->create([
            'feature' => 'targeted-resume',
        ]);

        $mockAgent = $this->createMock(CodeTalkerAgent::class);
        $mockAgent->method('prompt')->willThrowException(new \RuntimeException('API error'));

        $mockFactory = $this->createMock(AgentFactory::class);
        $mockFactory->method('forSystem')->willReturn($mockAgent);
        $mockFactory->method('forFeature')->willReturn($mockAgent);

        $this->app->instance(AgentFactory::class, $mockFactory);

        $service = app(AiMemoryService::class);
        $service->processCompletedConversation($conversation);

        // Should not throw — just logs the error
        $this->assertTrue(true);
    }

    private function agentResponse(string $text): AgentResponse
    {
        return new AgentResponse(
            'id-1',
            $text,
            new Usage(promptTokens: 10, completionTokens: 10),
            new Meta(provider: 'anthropic', model: 'claude-sonnet-4-6'),
        );
    }
}
