<?php

namespace Tests\Unit;

use App\Services\JobUrlParseService;
use App\Services\AiClientFactory;
use PHPUnit\Framework\TestCase;

class JobUrlParseServiceTest extends TestCase
{
    private JobUrlParseService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $factory = $this->createMock(AiClientFactory::class);
        $this->service = new JobUrlParseService($factory);
    }

    public function testCleanHtmlStripsScriptsAndStyles(): void
    {
        $html = '<html><head><style>body{color:red}</style></head><body><script>alert("x")</script><p>Hello</p></body></html>';

        $cleaned = $this->service->cleanHtml($html);

        $this->assertStringNotContainsString('<script', $cleaned);
        $this->assertStringNotContainsString('<style', $cleaned);
        $this->assertStringNotContainsString('alert', $cleaned);
        $this->assertStringContainsString('Hello', $cleaned);
    }

    public function testCleanHtmlStripsSvgAndNoscript(): void
    {
        $html = '<html><body><svg><circle r="5"/></svg><noscript>Enable JS</noscript><p>Content</p></body></html>';

        $cleaned = $this->service->cleanHtml($html);

        $this->assertStringNotContainsString('<svg', $cleaned);
        $this->assertStringNotContainsString('<noscript', $cleaned);
        $this->assertStringContainsString('Content', $cleaned);
    }

    public function testCleanHtmlTruncatesLargeContent(): void
    {
        $html = str_repeat('a', 200000);

        $cleaned = $this->service->cleanHtml($html);

        $this->assertLessThanOrEqual(100000, strlen($cleaned));
    }

    public function testCleanHtmlStripsComments(): void
    {
        $html = '<html><body><!-- secret comment --><p>Visible</p></body></html>';

        $cleaned = $this->service->cleanHtml($html);

        $this->assertStringNotContainsString('secret comment', $cleaned);
        $this->assertStringContainsString('Visible', $cleaned);
    }

    public function testExtractDomainStripsWww(): void
    {
        $this->assertEquals('linkedin.com', $this->service->extractDomain('https://www.linkedin.com/jobs/123'));
    }

    public function testExtractDomainHandlesSubdomains(): void
    {
        $this->assertEquals('jobs.lever.co', $this->service->extractDomain('https://jobs.lever.co/company/abc'));
    }

    public function testExtractDomainLowercases(): void
    {
        $this->assertEquals('example.com', $this->service->extractDomain('https://EXAMPLE.COM/job'));
    }
}
