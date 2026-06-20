<?php

namespace Tests\Unit\Services\Mcp;

use App\Contracts\ResumeDataServiceContract;
use App\Services\Mcp\TargetedResumeToolRegistry;
use App\Services\TargetedResumeService;
use Jvjvjv\CodeTalker\Contracts\Mcp\AiToolHandlerContract;
use Jvjvjv\CodeTalker\Models\AiConversation;
use Jvjvjv\CodeTalker\Services\AiMemoryService;
use Jvjvjv\CodeTalker\Services\Mcp\ChatBotToolRegistry;
use Tests\TestCase;

class ChatBotToolRegistryTest extends TestCase
{
    public function testItRegistersEveryToolInTheMcpToolsDirectory(): void
    {
        $conversation = new AiConversation([
            'user_id' => 123,
            'context' => [],
        ]);

        $registry = new ChatBotToolRegistry($conversation, exposeAllDiscoveredTools: true);

        $registeredToolNames = array_column($registry->toApiTools(), 'name');

        // Every registered tool must implement AiToolHandlerContract
        foreach ($registry->toApiTools() as $tool) {
            $this->assertArrayHasKey('name', $tool);
            $this->assertArrayHasKey('description', $tool);
            $this->assertArrayHasKey('input_schema', $tool);
        }

        // Known package tool must always be present
        $this->assertContains('scan-memories', $registeredToolNames);
        $this->assertContains('fetch-web-page', $registeredToolNames);
    }

    public function testRegistryFiltersToAllowedToolsOnly(): void
    {
        $conversation = new AiConversation(['user_id' => null, 'context' => []]);

        $registry = new ChatBotToolRegistry($conversation, ['scan-memories']);

        $toolNames = array_column($registry->toApiTools(), 'name');

        $this->assertSame(['scan-memories'], $toolNames);
    }

    public function testRegistryExposesNoToolsWhenAllowlistIsNull(): void
    {
        $registry = new ChatBotToolRegistry(
            new AiConversation(['user_id' => null, 'context' => []]),
            null,
        );

        $this->assertSame([], $registry->toApiTools());
    }

    public function testItRegistersEveryTargetedResumeToolInItsDirectory(): void
    {
        $resumeDataService = $this->createMock(ResumeDataServiceContract::class);
        $memoryService = $this->createMock(AiMemoryService::class);
        $targetedResumeService = $this->createMock(TargetedResumeService::class);
        $conversation = new AiConversation([
            'user_id' => 456,
            'context' => [],
        ]);

        $this->app->instance(ResumeDataServiceContract::class, $resumeDataService);
        $this->app->instance(AiMemoryService::class, $memoryService);
        $this->app->instance(TargetedResumeService::class, $targetedResumeService);

        $registry = new TargetedResumeToolRegistry(
            $conversation,
            $resumeDataService,
            $memoryService,
            $targetedResumeService,
        );

        $tools = $registry->toApiTools();

        $this->assertNotEmpty($tools);

        foreach ($tools as $tool) {
            $this->assertArrayHasKey('name', $tool);
            $this->assertArrayHasKey('description', $tool);
            $this->assertArrayHasKey('input_schema', $tool);
        }
    }
}
