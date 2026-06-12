<?php

namespace Tests\Unit\Services\Mcp\Tools\ChatBot;

use App\Models\AiChatBot;
use App\Services\Mcp\Tools\ChatBot\FetchWebPageTool;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Jvjvjv\CodeTalker\Models\AiConversation;
use Tests\TestCase;

class FetchWebPageToolTest extends TestCase
{
    public function testItFetchesHtmlContentWithTheJayScraperUserAgent(): void
    {
        Http::fake([
            'https://example.com/article' => Http::response(
                '<html><head><title>Example Article</title></head><body><main><h1>Heading</h1><p>First paragraph.</p><script>alert("x")</script><p>Second paragraph.</p></main></body></html>',
                200,
                ['Content-Type' => 'text/html; charset=UTF-8'],
            ),
        ]);

        $conversation = new AiConversation(['context' => []]);
        $conversation->setRelation('aiChatBot', new AiChatBot(['name' => 'Research Bot']));

        $tool = new FetchWebPageTool($conversation);

        $result = $tool->handle(['url' => 'https://example.com/article']);

        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://example.com/article'
                && $request->hasHeader('User-Agent', 'JayScraper/0.1.0 (name: Research Bot; purpose: research; contact: https://jasonvertucio.com)');
        });

        $this->assertSame('https://example.com/article', $result['url']);
        $this->assertSame('Example Article', $result['title']);
        $this->assertStringContainsString('Heading', $result['content']);
        $this->assertStringContainsString('First paragraph.', $result['content']);
        $this->assertStringContainsString('Second paragraph.', $result['content']);
        $this->assertStringNotContainsString('alert("x")', $result['content']);
        $this->assertFalse($result['truncated']);
    }

    public function testItRejectsUnsupportedUrlSchemes(): void
    {
        Http::fake();

        $tool = new FetchWebPageTool(new AiConversation(['context' => []]));

        $result = $tool->handle(['url' => 'file:///etc/passwd']);

        $this->assertSame('The URL must be a valid http or https address.', $result['error']);
        Http::assertNothingSent();
    }

    public function testItReturnsAMeaningfulErrorForHttpErrorResponses(): void
    {
        Http::fake([
            'https://example.com/missing' => Http::response(
                '<!DOCTYPE html><html><body>Not Found</body></html>',
                404,
                ['Content-Type' => 'text/html; charset=UTF-8'],
            ),
        ]);

        $tool = new FetchWebPageTool(new AiConversation(['context' => []]));

        $result = $tool->handle(['url' => 'https://example.com/missing']);

        $this->assertSame(
            'Failed to fetch https://example.com/missing. The server responded with HTTP status 404 (Not Found).',
            $result['error'],
        );
        $this->assertSame('https://example.com/missing', $result['url']);
        $this->assertSame(404, $result['status']);
        $this->assertArrayNotHasKey('content', $result);
    }

    public function testItReturnsAMeaningfulErrorWhenTheConnectionFails(): void
    {
        Http::fake(function (): void {
            throw new ConnectionException('cURL error 6: Could not resolve host: example.invalid');
        });

        $tool = new FetchWebPageTool(new AiConversation(['context' => []]));

        $result = $tool->handle(['url' => 'https://example.invalid/page']);

        $this->assertSame(
            'Could not connect to https://example.invalid/page. The request failed before receiving a response.',
            $result['error'],
        );
        $this->assertSame('https://example.invalid/page', $result['url']);
        $this->assertArrayNotHasKey('status', $result);
    }

    public function testItHandlesPlainTextResponses(): void
    {
        Http::fake([
            'https://example.com/plain' => Http::response(
                "Line one\n\nLine two",
                200,
                ['Content-Type' => 'text/plain; charset=UTF-8'],
            ),
        ]);

        $tool = new FetchWebPageTool(new AiConversation(['context' => []]));

        $result = $tool->handle(['url' => 'https://example.com/plain']);

        $this->assertNull($result['title']);
        $this->assertSame("Line one\n\nLine two", $result['content']);
        $this->assertFalse($result['truncated']);
    }
}
