<?php

namespace Tests\Unit;

use Jvjvjv\CodeTalker\Models\AiConversation;
use Jvjvjv\CodeTalker\Services\Mcp\ChatBotToolRegistry;
use Tests\TestCase;

class ChatBotToolRegistryTest extends TestCase
{
    public function test_registry_exposes_only_allowed_tools(): void
    {
        $registry = new ChatBotToolRegistry(
            new AiConversation(['user_id' => null, 'context' => []]),
            ['get_recent_blog_posts'],
        );

        $toolNames = array_column($registry->toApiTools(), 'name');

        $this->assertSame(['get_recent_blog_posts'], $toolNames);
    }

    public function test_registry_exposes_no_tools_when_allowlist_is_null(): void
    {
        $registry = new ChatBotToolRegistry(
            new AiConversation(['user_id' => null, 'context' => []]),
            null,
        );

        $this->assertSame([], $registry->toApiTools());
    }
}
