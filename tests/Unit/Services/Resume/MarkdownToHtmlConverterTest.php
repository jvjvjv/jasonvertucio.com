<?php

namespace Tests\Unit\Services\Resume;

use App\Services\Resume\MarkdownToHtmlConverter;
use PHPUnit\Framework\TestCase;

class MarkdownToHtmlConverterTest extends TestCase
{
    protected MarkdownToHtmlConverter $converter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->converter = new MarkdownToHtmlConverter;
    }

    public function test_empty_input_returns_empty_string(): void
    {
        $this->assertSame('', $this->converter->convert(''));
    }

    public function test_headings_print_their_display_labels(): void
    {
        $html = $this->converter->convert("# Skills\n# Experience\n# Projects\n# Education");

        $this->assertStringContainsString('>Technical Skills<', $html);
        $this->assertStringContainsString('>Professional Experience<', $html);
        $this->assertStringContainsString('>Selected Projects<', $html);
        $this->assertStringContainsString('>Education<', $html);

        foreach (['Skills<', 'Experience<', 'Projects<'] as $bare) {
            $this->assertStringNotContainsString('>'.$bare, $html, "No heading should print the bare identifier '{$bare}'");
        }
    }

    public function test_summary_is_rendered_with_no_heading(): void
    {
        $html = $this->converter->convert("# Summary\nExperienced engineer.");

        $this->assertStringNotContainsString('Summary', $html);
        $this->assertStringContainsString('Experienced engineer.', $html);
        $this->assertStringContainsString('class="Normal"', $html);
    }

    public function test_experience_h2_and_h3_use_job_title_and_company_info_classes(): void
    {
        $html = $this->converter->convert("# Experience\n## Senior Engineer\n### Acme - NYC - 2020");

        $this->assertStringContainsString('class="JobTitle"', $html);
        $this->assertStringContainsString('class="CompanyInfo"', $html);
        $this->assertStringNotContainsString('class="Heading2"', $html);
    }

    public function test_education_h3_uses_company_info_class_but_h2_stays_heading2(): void
    {
        $html = $this->converter->convert("# Education\n## B.S. Computer Science\n### MIT - 2020");

        $this->assertStringContainsString('class="Heading2"', $html);
        $this->assertStringContainsString('class="CompanyInfo"', $html);
    }

    public function test_bullets_are_grouped_into_one_list(): void
    {
        $html = $this->converter->convert("# Experience\n## Engineer\n- First\n- Second\n- Third");

        $this->assertSame(1, substr_count($html, '<ul'));
        $this->assertSame(3, substr_count($html, '<li>'));
    }

    public function test_key_technologies_bullet_is_not_part_of_the_list(): void
    {
        $html = $this->converter->convert("- A bullet\n- Key Technologies: PHP, Laravel");

        $this->assertStringContainsString('class="KeyTechnologies"', $html);
        $this->assertStringNotContainsString('Key Technologies', $this->between($html, '<ul', '</ul>'));
    }

    public function test_bold_text_renders_as_strong(): void
    {
        $html = $this->converter->convert('Led **cross-functional** teams');

        $this->assertStringContainsString('<strong>cross-functional</strong>', $html);
    }

    public function test_xml_special_characters_are_escaped(): void
    {
        $html = $this->converter->convert('Used <script> & "quotes"');

        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringContainsString('&amp;', $html);
        $this->assertStringContainsString('&quot;quotes&quot;', $html);
    }

    public function test_skills_section_with_both_top_and_other_groups(): void
    {
        $html = $this->converter->convert(
            "# Skills\n## Core\nPHP, Laravel\n---\n## Other\nDocker"
        );

        $this->assertStringContainsString('Core', $this->before($html, 'skills-columns'));
        $this->assertStringContainsString('class="skills-columns"', $html);
        $this->assertStringContainsString('Other', $this->between($html, 'skills-columns', '/div'));
        $this->assertStringContainsString('Docker', $this->between($html, 'skills-columns', '/div'));
    }

    public function test_skills_section_with_no_marker_flows_entirely_in_two_columns(): void
    {
        $html = $this->converter->convert("# Skills\n## Frontend\n- React\n## Backend\n- PHP");

        $this->assertStringContainsString('class="skills-columns"', $html);
        $this->assertSame(2, substr_count($html, 'class="skill-category"'));
    }

    public function test_marker_with_no_content_after_it_leaves_no_empty_column_region(): void
    {
        $html = $this->converter->convert("# Skills\n## Core\nPHP, Laravel\n---\n# Experience\n## Engineer");

        $this->assertStringNotContainsString('skills-columns', $html);
    }

    public function test_non_skills_sections_are_not_wrapped_in_columns(): void
    {
        $html = $this->converter->convert("# Experience\n## Engineer\n### Corp - 2020\n- Built stuff");

        $this->assertStringNotContainsString('skills-columns', $html);
    }

    protected function between(string $haystack, string $start, string $end): string
    {
        $startPos = strpos($haystack, $start);
        $this->assertNotFalse($startPos);
        $endPos = strpos($haystack, $end, $startPos);
        $this->assertNotFalse($endPos);

        return substr($haystack, $startPos, $endPos - $startPos);
    }

    protected function before(string $haystack, string $marker): string
    {
        $pos = strpos($haystack, $marker);
        $this->assertNotFalse($pos);

        return substr($haystack, 0, $pos);
    }
}
