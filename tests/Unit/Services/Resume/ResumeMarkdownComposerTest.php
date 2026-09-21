<?php

namespace Tests\Unit\Services\Resume;

use App\Services\Resume\ResumeMarkdownComposer;
use PHPUnit\Framework\TestCase;

class ResumeMarkdownComposerTest extends TestCase
{
    protected ResumeMarkdownComposer $composer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->composer = new ResumeMarkdownComposer;
    }

    public function test_composes_the_full_resume_in_section_order(): void
    {
        $markdown = $this->composer->compose($this->fixture());

        $expected = <<<'MD'
        Engineer who ships.

        # Skills
        ## Languages
        PHP, JavaScript
        ## Tools
        Docker, Git

        # Experience
        ## Lead Developer
        ### Acme Corp - Philadelphia, PA - 2020 - 2024
        - Built microservices
        - Key Technologies: PHP, Laravel

        ## Engineer
        ### Globex - Remote - 2018 - 2020
        - Maintained the monolith

        # Projects
        ## Portfolio Site
        A Laravel personal site.
        - Server-rendered Blade views

        # Education
        ## BS Computer Science
        ### State University - Philadelphia, PA - 2014 - 2018
        Graduated with honors.
        MD;

        $this->assertSame($expected, $markdown);
    }

    public function test_current_role_with_no_end_date_reads_present(): void
    {
        $markdown = $this->composer->compose([
            'experience' => [[
                'jobTitle' => 'Staff Engineer',
                'company' => 'Acme Corp',
                'location' => '',
                'dateStart' => '2024',
                'dateEnd' => '',
                'bullets' => [],
            ]],
        ]);

        $this->assertSame("# Experience\n## Staff Engineer\n### Acme Corp - 2024 - Present", $markdown);
    }

    public function test_education_without_dates_omits_the_range(): void
    {
        $markdown = $this->composer->compose([
            'education' => [[
                'institution' => 'State University',
                'degree' => 'BS Computer Science',
                'location' => '',
                'level' => '',
                'dateStart' => '',
                'dateEnd' => '',
                'description' => '',
            ]],
        ]);

        $this->assertSame("# Education\n## BS Computer Science\n### State University", $markdown);
    }

    public function test_education_with_only_a_start_date_does_not_read_present(): void
    {
        $markdown = $this->composer->compose([
            'education' => [[
                'institution' => 'State University',
                'degree' => 'BS Computer Science',
                'dateStart' => '2014',
                'dateEnd' => '',
            ]],
        ]);

        $this->assertStringNotContainsString('Present', $markdown);
        $this->assertStringContainsString('### State University - 2014', $markdown);
    }

    public function test_top_skills_precede_other_skills(): void
    {
        $markdown = $this->composer->compose([
            'skills' => [
                'top' => [['title' => 'Primary', 'listJoined' => 'PHP']],
                'other' => [['title' => 'Secondary', 'listJoined' => 'Bash']],
            ],
        ]);

        $this->assertSame("# Skills\n## Primary\nPHP\n## Secondary\nBash", $markdown);
    }

    public function test_skill_list_falls_back_to_joining_the_raw_list(): void
    {
        $markdown = $this->composer->compose([
            'skills' => ['top' => [['title' => 'Languages', 'list' => ['PHP', 'Go']]]],
        ]);

        $this->assertStringContainsString("## Languages\nPHP, Go", $markdown);
    }

    public function test_empty_sections_are_omitted_entirely(): void
    {
        $markdown = $this->composer->compose([
            'summary' => 'Just a summary.',
            'skills' => ['top' => [], 'other' => []],
            'experience' => [],
            'projects' => [],
            'education' => [],
        ]);

        $this->assertSame("Just a summary.", $markdown);
    }

    public function test_summary_is_emitted_without_a_heading(): void
    {
        $markdown = $this->composer->compose(['summary' => 'Engineer who ships.']);

        $this->assertSame('Engineer who ships.', $markdown);
        $this->assertStringNotContainsString('# Summary', $markdown);
    }

    public function test_empty_data_produces_empty_markdown(): void
    {
        $this->assertSame('', $this->composer->compose([]));
    }

    public function test_blank_bullets_are_dropped(): void
    {
        $markdown = $this->composer->compose([
            'projects' => [[
                'projectName' => 'Thing',
                'bullets' => ['Real bullet', '', '   '],
            ]],
        ]);

        $this->assertSame("# Projects\n## Thing\n- Real bullet", $markdown);
    }

    /**
     * @return array<string, mixed>
     */
    protected function fixture(): array
    {
        return [
            'name' => 'Jason Vertucio',
            'title' => 'Senior Software Engineer',
            'summary' => 'Engineer who ships.',
            'skills' => [
                'top' => [['title' => 'Languages', 'listJoined' => 'PHP, JavaScript']],
                'other' => [['title' => 'Tools', 'listJoined' => 'Docker, Git']],
            ],
            'experience' => [
                [
                    'jobTitle' => 'Lead Developer',
                    'company' => 'Acme Corp',
                    'location' => 'Philadelphia, PA',
                    'dateStart' => '2020',
                    'dateEnd' => '2024',
                    'bullets' => ['Built microservices', 'Key Technologies: PHP, Laravel'],
                ],
                [
                    'jobTitle' => 'Engineer',
                    'company' => 'Globex',
                    'location' => 'Remote',
                    'dateStart' => '2018',
                    'dateEnd' => '2020',
                    'bullets' => ['Maintained the monolith'],
                ],
            ],
            'projects' => [
                [
                    'projectName' => 'Portfolio Site',
                    'description' => 'A Laravel personal site.',
                    'bullets' => ['Server-rendered Blade views'],
                ],
            ],
            'education' => [
                [
                    'institution' => 'State University',
                    'degree' => 'BS Computer Science',
                    'location' => 'Philadelphia, PA',
                    'level' => '',
                    'dateStart' => '2014',
                    'dateEnd' => '2018',
                    'description' => 'Graduated with honors.',
                ],
            ],
        ];
    }
}
