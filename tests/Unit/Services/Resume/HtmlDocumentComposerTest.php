<?php

namespace Tests\Unit\Services\Resume;

use App\Services\Resume\HtmlDocumentComposer;
use Tests\TestCase;

class HtmlDocumentComposerTest extends TestCase
{
    protected HtmlDocumentComposer $composer;

    protected function setUp(): void
    {
        parent::setUp();

        $templatePath = dirname(__DIR__, 4).'/resources/resume/2026 template.docx';

        if (! file_exists($templatePath)) {
            $this->markTestSkipped('Shared template not found: '.$templatePath);
        }

        config(['resume.template' => $templatePath]);
        $this->composer = new HtmlDocumentComposer;
    }

    public function test_letterhead_contains_substituted_values(): void
    {
        $html = $this->composer->compose('', [
            'name' => 'Jason Vertucio',
            'title' => 'Software Engineer',
            'email' => 'jason@example.com',
            'phone' => '555-1234',
            'url' => 'jasonvertucio.com',
        ]);

        $this->assertStringContainsString('Jason Vertucio', $html);
        $this->assertStringContainsString('Software Engineer', $html);
        $this->assertStringContainsString('jason@example.com', $html);
        $this->assertStringContainsString('555-1234', $html);
        $this->assertStringContainsString('jasonvertucio.com', $html);
    }

    public function test_letterhead_leaves_no_literal_placeholder_text(): void
    {
        $html = $this->composer->compose('', [
            'name' => 'Jason Vertucio',
            'title' => 'Software Engineer',
            'email' => 'jason@example.com',
            'phone' => '555-1234',
            // {url} is stored split across runs (" • {" / "url" / "}") in the
            // committed template, so this exercises the split-run path.
            'url' => 'jasonvertucio.com',
        ]);

        foreach (['{name}', '{title}', '{email}', '{phone}', '{url}'] as $placeholder) {
            $this->assertStringNotContainsString($placeholder, $html);
        }
    }

    public function test_body_html_is_appended_after_the_letterhead(): void
    {
        $html = $this->composer->compose('<p class="Normal">Body content</p>', [
            'name' => 'Jason', 'title' => '', 'email' => '', 'phone' => '', 'url' => '',
        ]);

        $letterheadPos = strpos($html, 'Jason');
        $bodyPos = strpos($html, 'Body content');

        $this->assertNotFalse($letterheadPos);
        $this->assertNotFalse($bodyPos);
        $this->assertLessThan($bodyPos, $letterheadPos);
    }

    public function test_composed_document_is_well_formed_html(): void
    {
        $html = $this->composer->compose('<p class="Normal">Test</p>', [
            'name' => 'Jason', 'title' => '', 'email' => '', 'phone' => '', 'url' => '',
        ]);

        $this->assertStringStartsWith('<!DOCTYPE html>', $html);
        $this->assertStringContainsString('<style>', $html);
        $this->assertStringContainsString('</html>', $html);
    }
}
