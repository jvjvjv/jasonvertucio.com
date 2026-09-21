<?php

namespace Tests\Unit\Services\Resume;

use App\Services\Resume\MarkdownToOpenXmlConverter;
use PHPUnit\Framework\TestCase;

class MarkdownToOpenXmlConverterTest extends TestCase
{
    protected MarkdownToOpenXmlConverter $converter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->converter = new MarkdownToOpenXmlConverter;
    }

    public function test_empty_input_returns_empty_string(): void
    {
        $this->assertSame('', $this->converter->convert(''));
        $this->assertSame('', $this->converter->convert('   '));
    }

    public function test_summary_heading_is_suppressed_but_its_text_is_kept(): void
    {
        $xml = $this->converter->convert("# Summary\nExperienced engineer.");

        $this->assertStringNotContainsString('Summary', $xml, 'The SUMMARY heading is dropped by design');
        $this->assertStringContainsString('Experienced engineer.', $xml);
        $this->assertStringContainsString('<w:pStyle w:val="Normal"/>', $xml);
    }

    public function test_suppression_is_case_insensitive(): void
    {
        $this->assertStringNotContainsString('SUMMARY', $this->converter->convert('# SUMMARY'));
    }

    public function test_other_headings_are_not_suppressed(): void
    {
        $xml = $this->converter->convert("# Skills\n## Front-End\nPHP");

        $this->assertStringContainsString('Skills', $xml);
    }

    public function test_heading1_produces_heading1_style(): void
    {
        $xml = $this->converter->convert('# Experience');

        $this->assertStringContainsString('<w:pStyle w:val="Heading1"/>', $xml);
        $this->assertStringContainsString('Experience', $xml);
    }

    public function test_heading2_default_produces_heading2_style(): void
    {
        $xml = $this->converter->convert("# Skills\n## Programming Languages");

        $this->assertStringContainsString('<w:pStyle w:val="Heading2"/>', $xml);
        $this->assertStringContainsString('Programming Languages', $xml);
    }

    public function test_heading2_under_experience_produces_job_title_style(): void
    {
        $xml = $this->converter->convert("# Experience\n## Senior Software Engineer");

        $this->assertStringContainsString('<w:pStyle w:val="JobTitle"/>', $xml);
        $this->assertStringContainsString('Senior Software Engineer', $xml);
    }

    public function test_heading3_under_experience_produces_company_info_style(): void
    {
        $xml = $this->converter->convert("# Experience\n## Senior Engineer\n### Acme Corp - NYC - 2020-2024");

        $this->assertStringContainsString('<w:pStyle w:val="CompanyInfo"/>', $xml);
        $this->assertStringContainsString('Acme Corp - NYC - 2020-2024', $xml);
    }

    public function test_heading3_under_education_produces_company_info_style(): void
    {
        $xml = $this->converter->convert("# Education\n## Bachelor of Science\n### MIT - 2016-2020");

        $this->assertStringContainsString('<w:pStyle w:val="CompanyInfo"/>', $xml);
        $this->assertStringContainsString('MIT - 2016-2020', $xml);
    }

    public function test_heading2_under_skills_does_not_produce_job_title(): void
    {
        $xml = $this->converter->convert("# Skills\n## Frontend");

        $this->assertStringNotContainsString('JobTitle', $xml);
        $this->assertStringContainsString('<w:pStyle w:val="Heading2"/>', $xml);
    }

    public function test_bullet_produces_list_paragraph_with_num_pr(): void
    {
        $xml = $this->converter->convert('- Led a team of 5 engineers');

        $this->assertStringContainsString('<w:pStyle w:val="ListParagraph"/>', $xml);
        $this->assertStringContainsString('<w:numPr>', $xml);
        $this->assertStringContainsString('<w:numId w:val="6"/>', $xml);
        $this->assertStringContainsString('<w:ilvl w:val="0"/>', $xml);
        $this->assertStringContainsString('Led a team of 5 engineers', $xml);
    }

    public function test_asterisk_bullet_also_works(): void
    {
        $xml = $this->converter->convert('* Built a REST API');

        $this->assertStringContainsString('<w:pStyle w:val="ListParagraph"/>', $xml);
        $this->assertStringContainsString('Built a REST API', $xml);
    }

    public function test_key_technologies_line_produces_key_technologies_style(): void
    {
        $xml = $this->converter->convert('- Key Technologies: React, Node.js, PostgreSQL');

        $this->assertStringContainsString('<w:pStyle w:val="KeyTechnologies"/>', $xml);
        $this->assertStringNotContainsString('<w:numPr>', $xml);
        $this->assertStringContainsString('Key Technologies: React, Node.js, PostgreSQL', $xml);
    }

    public function test_plain_paragraph_produces_normal_style(): void
    {
        $xml = $this->converter->convert('Experienced software engineer with 10 years of expertise.');

        $this->assertStringContainsString('<w:pStyle w:val="Normal"/>', $xml);
        $this->assertStringContainsString('Experienced software engineer', $xml);
    }

    public function test_bold_text_creates_bold_run(): void
    {
        $xml = $this->converter->convert('Led **cross-functional** teams');

        $this->assertStringContainsString('<w:b/>', $xml);
        $this->assertStringContainsString('cross-functional', $xml);
        $this->assertStringContainsString('Led ', $xml);
        $this->assertStringContainsString(' teams', $xml);
    }

    public function test_code_fences_are_stripped(): void
    {
        $markdown = "```tailored-resume\n# Skills\nTest content\n```";
        $xml = $this->converter->convert($markdown);

        $this->assertStringNotContainsString('tailored-resume', $xml);
        $this->assertStringNotContainsString('```', $xml);
        $this->assertStringContainsString('<w:pStyle w:val="Heading1"/>', $xml);
        $this->assertStringContainsString('Test content', $xml);
    }

    public function test_empty_lines_are_skipped(): void
    {
        $xml = $this->converter->convert("# Experience\n\nA paragraph\n\n- A bullet");

        $pCount = substr_count($xml, '<w:p ');
        $this->assertSame(3, $pCount, 'Should produce exactly 3 paragraphs (no empty ones)');
    }

    public function test_xml_special_characters_are_escaped(): void
    {
        $xml = $this->converter->convert('- Used R&D approach with <script> tags & "quotes"');

        $this->assertStringContainsString('R&amp;D', $xml);
        $this->assertStringContainsString('&lt;script&gt;', $xml);
        $this->assertStringContainsString('&quot;quotes&quot;', $xml);
    }

    public function test_section_context_resets_on_new_h1(): void
    {
        $markdown = "# Experience\n## Senior Engineer\n# Projects\n## My Cool Project";
        $xml = $this->converter->convert($markdown);

        $this->assertStringContainsString('<w:pStyle w:val="JobTitle"/>', $xml);
        // "My Cool Project" under Projects should be Heading2, not JobTitle
        // Count occurrences: 1 JobTitle, 1 Heading2
        $this->assertSame(1, substr_count($xml, 'w:val="JobTitle"'));
        $this->assertSame(1, substr_count($xml, 'w:val="Heading2"'));
    }

    public function test_full_resume_end_to_end(): void
    {
        $markdown = <<<'MD'
```tailored-resume
# Summary
Experienced full-stack engineer with expertise in **Laravel** and **React**.

# Skills
## Frontend
- React, Vue.js, TypeScript
## Backend
- PHP, Laravel, Node.js

# Experience
## Senior Software Engineer
### Acme Corp - New York, NY - Jan 2020 - Present
- Led development of microservices architecture
- **Reduced** deployment time by 60%
- Key Technologies: Laravel, React, Docker, AWS

## Software Engineer
### StartupCo - Remote - Mar 2017 - Dec 2019
- Built REST APIs serving 1M+ requests/day

# Education
## B.S. Computer Science
### State University - 2013 - 2017

# Projects
## Open Source CLI Tool
- Published npm package with 5K+ weekly downloads
```
MD;

        $xml = $this->converter->convert($markdown);

        // Section headers → Heading1. Summary is suppressed by design, so the
        // five `#` headings in the fixture yield four rendered headings.
        $this->assertSame(4, substr_count($xml, 'w:val="Heading1"'));

        // Job titles under Experience → JobTitle
        $this->assertSame(2, substr_count($xml, 'w:val="JobTitle"'));

        // Company info under Experience/Education → CompanyInfo
        $this->assertSame(3, substr_count($xml, 'w:val="CompanyInfo"'));

        // Skill categories, education degree, and project names → Heading2
        $this->assertSame(4, substr_count($xml, 'w:val="Heading2"'));

        // Key Technologies line → KeyTechnologies
        $this->assertSame(1, substr_count($xml, 'w:val="KeyTechnologies"'));

        // Bullets (excluding KeyTechnologies) → ListParagraph
        $this->assertGreaterThan(0, substr_count($xml, 'w:val="ListParagraph"'));

        // Normal paragraphs
        $this->assertGreaterThan(0, substr_count($xml, 'w:val="Normal"'));

        // Bold formatting
        $this->assertStringContainsString('<w:b/>', $xml);

        // This fixture's skills section marks no division, so the whole section
        // flows in two columns: one break closes the heading's run, a second
        // closes the two-column run at the next section.
        $this->assertSame(2, substr_count($xml, '<w:type w:val="continuous"/>'));
        $this->assertSame(
            ['Frontend', 'React, Vue.js, TypeScript', 'Backend', 'PHP, Laravel, Node.js'],
            $this->textsInColumns($xml, 2)
        );
    }

    public function test_skills_section_without_a_marker_flows_entirely_in_two_columns(): void
    {
        $xml = $this->converter->convert("# Skills\n## Frontend\n- React\n## Backend\n- PHP");

        $this->assertSame(
            ['Frontend', 'React', 'Backend', 'PHP'],
            $this->textsInColumns($xml, 2)
        );
        $this->assertSame(['Technical Skills'], $this->textsInColumns($xml, 1));
        $this->assertSame(2, substr_count($xml, '<w:type w:val="continuous"/>'));
    }

    public function test_skills_columns_end_when_next_section_starts(): void
    {
        $xml = $this->converter->convert("# Skills\n## Frontend\n- React\n# Experience\n## Engineer");

        $this->assertSame(['Frontend', 'React'], $this->textsInColumns($xml, 2));

        // Everything from the next heading on is back to full width.
        $this->assertSame(
            ['Technical Skills', 'Professional Experience', 'Engineer'],
            $this->textsInColumns($xml, 1)
        );
        $this->assertStringContainsString('w:val="JobTitle"', $xml);
    }

    public function test_top_skills_run_full_width_and_the_rest_flow_in_two_columns(): void
    {
        $xml = $this->converter->convert(
            "# Skills\n## Core\nPHP, Laravel\n---\n## Other\nDocker\n# Experience\n## Engineer"
        );

        $this->assertSame(['Other', 'Docker'], $this->textsInColumns($xml, 2));
        $this->assertSame(
            ['Technical Skills', 'Core', 'PHP, Laravel', 'Professional Experience', 'Engineer'],
            $this->textsInColumns($xml, 1)
        );
    }

    public function test_a_marker_with_no_content_after_it_leaves_no_two_column_region(): void
    {
        $xml = $this->converter->convert("# Skills\n## Core\nPHP, Laravel\n---\n# Experience\n## Engineer");

        $this->assertSame([], $this->textsInColumns($xml, 2));
        $this->assertStringNotContainsString('<w:cols', $xml);
    }

    public function test_a_trailing_two_column_region_is_closed_at_the_end_of_the_document(): void
    {
        $xml = $this->converter->convert("# Skills\n## Frontend\n- React");

        $this->assertSame(['Frontend', 'React'], $this->textsInColumns($xml, 2));

        $paragraphs = $this->parseParagraphs($xml);
        $this->assertSame(2, end($paragraphs)['columns'], 'The document must close its two-column run');
    }

    public function test_a_rule_emits_no_paragraph_outside_a_skills_section(): void
    {
        $xml = $this->converter->convert("# Experience\n## Engineer\n---\n## Manager");

        $this->assertStringNotContainsString('---', $xml);
        $this->assertStringNotContainsString('<w:cols', $xml);
        $this->assertSame(
            ['Professional Experience', 'Engineer', 'Manager'],
            $this->textsInColumns($xml, 1)
        );
    }

    public function test_section_headings_carry_their_display_labels(): void
    {
        $xml = $this->converter->convert("# Skills\n# Experience\n# Projects\n# Education");

        $this->assertSame(
            ['Technical Skills', 'Professional Experience', 'Selected Projects', 'Education'],
            array_values(array_filter(array_column($this->parseParagraphs($xml), 'text')))
        );
    }

    public function test_a_heading_already_using_its_display_label_keeps_its_styling(): void
    {
        $xml = $this->converter->convert("# Professional Experience\n## Engineer\n### Acme - 2020");

        $this->assertStringContainsString('w:val="JobTitle"', $xml);
        $this->assertStringContainsString('w:val="CompanyInfo"', $xml);

        // The label is not doubled up by re-resolving it through the alias map.
        $this->assertSame(1, substr_count($xml, 'Professional Experience'));
    }

    public function test_an_unrecognized_heading_renders_its_own_text(): void
    {
        $xml = $this->converter->convert("# Certifications\n## AWS Solutions Architect");

        $this->assertStringContainsString('Certifications', $xml);
        $this->assertStringContainsString('<w:pStyle w:val="Heading1"/>', $xml);
        $this->assertStringContainsString('<w:pStyle w:val="Heading2"/>', $xml);
        $this->assertStringNotContainsString('<w:cols', $xml);
    }

    public function test_non_skills_sections_do_not_get_column_breaks(): void
    {
        $xml = $this->converter->convert("# Experience\n## Engineer\n### Corp - 2020\n- Built stuff");

        $this->assertStringNotContainsString('<w:cols', $xml);
        $this->assertStringNotContainsString('continuous', $xml);
    }

    public function test_skills_content_falls_inside_the_two_column_region(): void
    {
        $xml = $this->converter->convert("# Skills\n## Languages\nPHP, JavaScript\n# Experience\n## Engineer");

        $this->assertSame(
            ['Languages', 'PHP, JavaScript'],
            $this->textsInColumns($xml, 2),
            'The skill category and its list must sit inside the two-column region, not merely alongside a two-column break'
        );
    }

    /**
     * Split the emitted fragment into the column regions that actually govern it.
     *
     * A `sectPr` describes the section that *ends* with its paragraph, so a
     * content paragraph belongs to the next break paragraph following it.
     * Content after the final break is governed by the template's own body
     * `sectPr`, which is a single column.
     *
     * @return array<int, array{columns: int, texts: array<int, string>}>
     */
    protected function columnRegions(string $xml): array
    {
        $regions = [];
        $pending = [];

        foreach ($this->parseParagraphs($xml) as $paragraph) {
            if ($paragraph['columns'] === null) {
                $pending[] = $paragraph['text'];

                continue;
            }

            $regions[] = ['columns' => $paragraph['columns'], 'texts' => $pending];
            $pending = [];
        }

        if ($pending !== []) {
            $regions[] = ['columns' => 1, 'texts' => $pending];
        }

        return $regions;
    }

    /**
     * Every paragraph's text, in order, that falls inside a region of the given
     * column count.
     *
     * @return array<int, string>
     */
    protected function textsInColumns(string $xml, int $columns): array
    {
        $texts = [];

        foreach ($this->columnRegions($xml) as $region) {
            if ($region['columns'] === $columns) {
                $texts = array_merge($texts, $region['texts']);
            }
        }

        return $texts;
    }

    /**
     * Flatten the fragment into its paragraphs.
     *
     * A paragraph carrying a `sectPr` is a column break and reports its column
     * count; every other paragraph reports null and carries its text.
     *
     * @return array<int, array{text: string, style: string, columns: int|null}>
     */
    protected function parseParagraphs(string $xml): array
    {
        preg_match_all('/<w:p[ >].*?<\/w:p>/s', $xml, $matches);

        $paragraphs = [];

        foreach ($matches[0] as $paragraph) {
            preg_match_all('/<w:t[^>]*>(.*?)<\/w:t>/s', $paragraph, $runs);
            preg_match('/<w:pStyle w:val="([^"]+)"/', $paragraph, $style);
            preg_match('/<w:cols w:num="(\d+)"/', $paragraph, $cols);

            $paragraphs[] = [
                'text' => html_entity_decode(implode('', $runs[1]), ENT_XML1 | ENT_QUOTES, 'UTF-8'),
                'style' => $style[1] ?? '',
                'columns' => str_contains($paragraph, '<w:sectPr>') ? (int) ($cols[1] ?? 1) : null,
            ];
        }

        return $paragraphs;
    }
}
