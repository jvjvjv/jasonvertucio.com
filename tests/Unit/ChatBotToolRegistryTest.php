<?php

namespace Tests\Unit;

use App\Contracts\ResumeDataServiceContract;
use App\Models\AiConversation;
use App\Services\AiMemoryService;
use App\Services\Mcp\ChatBotToolRegistry;
use App\Services\TargetedResumeService;
use Tests\TestCase;

class ChatBotToolRegistryTest extends TestCase
{
    public function test_registry_exposes_only_allowed_tools(): void
    {
        $registry = new ChatBotToolRegistry(
            new AiConversation(['user_id' => null, 'context' => []]),
            $this->mockResumeDataService(),
            $this->mockAiMemoryService(),
            $this->mockTargetedResumeService(),
            ['get_recent_blog_posts'],
        );

        $toolNames = array_column($registry->toApiTools(), 'name');

        $this->assertSame(['get_recent_blog_posts'], $toolNames);
    }

    public function test_registry_exposes_no_tools_when_allowlist_is_null(): void
    {
        $registry = new ChatBotToolRegistry(
            new AiConversation(['user_id' => null, 'context' => []]),
            $this->mockResumeDataService(),
            $this->mockAiMemoryService(),
            $this->mockTargetedResumeService(),
            null,
        );

        $this->assertSame([], $registry->toApiTools());
    }

    private function mockResumeDataService(): ResumeDataServiceContract
    {
        return $this->createMock(ResumeDataServiceContract::class);
    }

    private function mockAiMemoryService(): AiMemoryService
    {
        return $this->createMock(AiMemoryService::class);
    }

    private function mockTargetedResumeService(): TargetedResumeService
    {
        return $this->createMock(TargetedResumeService::class);
    }
}
