<?php

namespace Tests\Unit;

use App\Support\SpeakableText;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SpeakableTextTest extends TestCase
{
    /**
     * @return array<string, array{string, string}>
     */
    public static function markdownProvider(): array
    {
        return [
            'bold' => ['**Strong fit** for the role', 'Strong fit for the role'],
            'italic and inline code' => ['Use _the_ `artisan` command', 'Use the artisan command'],
            'link' => ['See [the posting](https://example.com/job) today', 'See the posting today'],
            'list' => ["- First point\n- Second point", 'First point Second point'],
            'heading' => ["## Summary\n\nA short paragraph.", 'Summary A short paragraph.'],
            'fenced code' => ["Run this:\n\n```bash\nnpm run build\n```", 'Run this: npm run build'],
            'entities' => ['Tom &amp; Jerry & "friends"', 'Tom & Jerry & "friends"'],
            'raw html tags are not spoken' => ['Hello <b>there</b> world', 'Hello there world'],
            'markup only' => ['***', ''],
        ];
    }

    #[DataProvider('markdownProvider')]
    public function test_markdown_is_reduced_to_spoken_words(string $markdown, string $expected): void
    {
        $this->assertSame($expected, SpeakableText::fromMarkdown($markdown));
    }
}
