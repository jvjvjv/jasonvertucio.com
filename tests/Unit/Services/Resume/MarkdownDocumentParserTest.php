<?php

namespace Tests\Unit\Services\Resume;

use App\Services\Resume\MarkdownDocumentParser;
use PHPUnit\Framework\TestCase;

class MarkdownDocumentParserTest extends TestCase
{
    protected MarkdownDocumentParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new MarkdownDocumentParser;
    }

    public function test_bare_identifier_and_display_label_resolve_to_the_same_section_key(): void
    {
        $bare = $this->parser->parse('# Experience');
        $label = $this->parser->parse('# Professional Experience');

        $this->assertSame('Experience', $bare[0]['text']);
        $this->assertSame('Experience', $label[0]['text']);
    }

    public function test_summary_heading_is_suppressed(): void
    {
        $lines = $this->parser->parse("# Summary\nExperienced engineer.");

        $this->assertCount(1, $lines);
        $this->assertSame('paragraph', $lines[0]['type']);
        $this->assertSame('Experienced engineer.', $lines[0]['text']);
    }

    public function test_summary_suppression_is_case_insensitive(): void
    {
        $lines = $this->parser->parse('# SUMMARY');

        $this->assertSame([], $lines);
    }

    public function test_marker_splits_the_skills_section_into_two_column_regions(): void
    {
        $lines = $this->parser->parse("# Skills\n## Core\nPHP, Laravel\n---\n## Other\nDocker");

        $byText = [];
        foreach ($lines as $line) {
            $byText[$line['text']] = $line['columns'];
        }

        $this->assertSame(1, $byText['Skills']);
        $this->assertSame(1, $byText['Core']);
        $this->assertSame(1, $byText['PHP, Laravel']);
        $this->assertSame(2, $byText['Other']);
        $this->assertSame(2, $byText['Docker']);
    }

    public function test_marker_outside_the_skills_section_is_discarded(): void
    {
        $lines = $this->parser->parse("# Experience\n## Engineer\n---\n## Manager");

        // The rule line is parsed but carries no column effect outside the
        // skills section — the emitters are the ones that skip rendering it.
        foreach ($lines as $line) {
            $this->assertSame(1, $line['columns']);
        }
    }

    public function test_skills_section_with_no_marker_flows_entirely_in_two_columns(): void
    {
        $lines = $this->parser->parse("# Skills\n## Frontend\n- React");

        $byText = [];
        foreach ($lines as $line) {
            $byText[$line['text']] = $line['columns'];
        }

        $this->assertSame(1, $byText['Skills']);
        $this->assertSame(2, $byText['Frontend']);
        $this->assertSame(2, $byText['React']);
    }

    public function test_code_fences_are_stripped(): void
    {
        $lines = $this->parser->parse("```tailored-resume\n# Skills\nTest content\n```");

        $texts = array_column($lines, 'text');
        $this->assertNotContains('```', $texts);
        $this->assertContains('Skills', $texts);
        $this->assertContains('Test content', $texts);
    }

    public function test_empty_input_returns_no_lines(): void
    {
        $this->assertSame([], $this->parser->parse(''));
        $this->assertSame([], $this->parser->parse('   '));
    }
}
