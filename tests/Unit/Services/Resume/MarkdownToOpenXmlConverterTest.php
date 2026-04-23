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
        $this->converter = new MarkdownToOpenXmlConverter();
    }

    public function testEmptyInputReturnsEmptyString(): void
    {
        $this->assertSame('', $this->converter->convert(''));
        $this->assertSame('', $this->converter->convert('   '));
    }

    public function testHeading1ProducesHeading1Style(): void
    {
        $xml = $this->converter->convert('# Experience');

        $this->assertStringContainsString('<w:pStyle w:val="Heading1"/>', $xml);
        $this->assertStringContainsString('Experience', $xml);
    }

    public function testHeading2DefaultProducesHeading2Style(): void
    {
        $xml = $this->converter->convert("# Skills\n## Programming Languages");

        $this->assertStringContainsString('<w:pStyle w:val="Heading2"/>', $xml);
        $this->assertStringContainsString('Programming Languages', $xml);
    }

    public function testHeading2UnderExperienceProducesJobTitleStyle(): void
    {
        $xml = $this->converter->convert("# Experience\n## Senior Software Engineer");

        $this->assertStringContainsString('<w:pStyle w:val="JobTitle"/>', $xml);
        $this->assertStringContainsString('Senior Software Engineer', $xml);
    }

    public function testHeading3UnderExperienceProducesCompanyInfoStyle(): void
    {
        $xml = $this->converter->convert("# Experience\n## Senior Engineer\n### Acme Corp - NYC - 2020-2024");

        $this->assertStringContainsString('<w:pStyle w:val="CompanyInfo"/>', $xml);
        $this->assertStringContainsString('Acme Corp - NYC - 2020-2024', $xml);
    }

    public function testHeading3UnderEducationProducesCompanyInfoStyle(): void
    {
        $xml = $this->converter->convert("# Education\n## Bachelor of Science\n### MIT - 2016-2020");

        $this->assertStringContainsString('<w:pStyle w:val="CompanyInfo"/>', $xml);
        $this->assertStringContainsString('MIT - 2016-2020', $xml);
    }

    public function testHeading2UnderSkillsDoesNotProduceJobTitle(): void
    {
        $xml = $this->converter->convert("# Skills\n## Frontend");

        $this->assertStringNotContainsString('JobTitle', $xml);
        $this->assertStringContainsString('<w:pStyle w:val="Heading2"/>', $xml);
    }

    public function testBulletProducesListParagraphWithNumPr(): void
    {
        $xml = $this->converter->convert('- Led a team of 5 engineers');

        $this->assertStringContainsString('<w:pStyle w:val="ListParagraph"/>', $xml);
        $this->assertStringContainsString('<w:numPr>', $xml);
        $this->assertStringContainsString('<w:numId w:val="6"/>', $xml);
        $this->assertStringContainsString('<w:ilvl w:val="0"/>', $xml);
        $this->assertStringContainsString('Led a team of 5 engineers', $xml);
    }

    public function testAsteriskBulletAlsoWorks(): void
    {
        $xml = $this->converter->convert('* Built a REST API');

        $this->assertStringContainsString('<w:pStyle w:val="ListParagraph"/>', $xml);
        $this->assertStringContainsString('Built a REST API', $xml);
    }

    public function testKeyTechnologiesLineProducesKeyTechnologiesStyle(): void
    {
        $xml = $this->converter->convert('- Key Technologies: React, Node.js, PostgreSQL');

        $this->assertStringContainsString('<w:pStyle w:val="KeyTechnologies"/>', $xml);
        $this->assertStringNotContainsString('<w:numPr>', $xml);
        $this->assertStringContainsString('Key Technologies: React, Node.js, PostgreSQL', $xml);
    }

    public function testPlainParagraphProducesNormalStyle(): void
    {
        $xml = $this->converter->convert('Experienced software engineer with 10 years of expertise.');

        $this->assertStringContainsString('<w:pStyle w:val="Normal"/>', $xml);
        $this->assertStringContainsString('Experienced software engineer', $xml);
    }

    public function testBoldTextCreatesBoldRun(): void
    {
        $xml = $this->converter->convert('Led **cross-functional** teams');

        $this->assertStringContainsString('<w:b/>', $xml);
        $this->assertStringContainsString('cross-functional', $xml);
        $this->assertStringContainsString('Led ', $xml);
        $this->assertStringContainsString(' teams', $xml);
    }

    public function testCodeFencesAreStripped(): void
    {
        $markdown = "```tailored-resume\n# Summary\nTest content\n```";
        $xml = $this->converter->convert($markdown);

        $this->assertStringNotContainsString('tailored-resume', $xml);
        $this->assertStringNotContainsString('```', $xml);
        $this->assertStringContainsString('<w:pStyle w:val="Heading1"/>', $xml);
        $this->assertStringContainsString('Test content', $xml);
    }

    public function testEmptyLinesAreSkipped(): void
    {
        $xml = $this->converter->convert("# Summary\n\nA paragraph\n\n- A bullet");

        $pCount = substr_count($xml, '<w:p ');
        $this->assertSame(3, $pCount, 'Should produce exactly 3 paragraphs (no empty ones)');
    }

    public function testXmlSpecialCharactersAreEscaped(): void
    {
        $xml = $this->converter->convert('- Used R&D approach with <script> tags & "quotes"');

        $this->assertStringContainsString('R&amp;D', $xml);
        $this->assertStringContainsString('&lt;script&gt;', $xml);
        $this->assertStringContainsString('&quot;quotes&quot;', $xml);
    }

    public function testSectionContextResetsOnNewH1(): void
    {
        $markdown = "# Experience\n## Senior Engineer\n# Projects\n## My Cool Project";
        $xml = $this->converter->convert($markdown);

        $this->assertStringContainsString('<w:pStyle w:val="JobTitle"/>', $xml);
        // "My Cool Project" under Projects should be Heading2, not JobTitle
        // Count occurrences: 1 JobTitle, 1 Heading2
        $this->assertSame(1, substr_count($xml, 'w:val="JobTitle"'));
        $this->assertSame(1, substr_count($xml, 'w:val="Heading2"'));
    }

    public function testFullResumeEndToEnd(): void
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

        // Section headers → Heading1
        $this->assertSame(5, substr_count($xml, 'w:val="Heading1"'));

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

        // Skills section has an explicit single-column boundary after heading,
        // a two-column start, and a return to single-column at section end.
        $this->assertSame(3, substr_count($xml, '<w:type w:val="continuous"/>'));
        $this->assertStringContainsString('<w:cols w:num="2"', $xml);
        $this->assertStringContainsString('<w:cols w:num="1"', $xml);
    }

    public function testSkillsSectionGetsTwoColumnBreaks(): void
    {
        $xml = $this->converter->convert("# Skills\n## Frontend\n- React\n## Backend\n- PHP");

        // Skills heading is isolated in 1-col, then content switches to 2-col,
        // and converter returns to 1-col at section end.
        $this->assertStringContainsString('<w:cols w:num="2"', $xml);
        $this->assertStringContainsString('<w:cols w:num="1"', $xml);
        $this->assertSame(3, substr_count($xml, '<w:type w:val="continuous"/>'));
    }

    public function testSkillsColumnsEndWhenNextSectionStarts(): void
    {
        $xml = $this->converter->convert("# Skills\n## Frontend\n- React\n# Experience\n## Engineer");

        // 2-col break emitted for Skills content, and 1-col break emitted
        // before Experience heading.
        $this->assertStringContainsString('<w:cols w:num="2"', $xml);
        $this->assertStringContainsString('<w:cols w:num="1"', $xml);
        // Experience content still gets correct styles
        $this->assertStringContainsString('w:val="JobTitle"', $xml);
    }

    public function testNonSkillsSectionsDoNotGetColumnBreaks(): void
    {
        $xml = $this->converter->convert("# Experience\n## Engineer\n### Corp - 2020\n- Built stuff");

        $this->assertStringNotContainsString('<w:cols', $xml);
        $this->assertStringNotContainsString('continuous', $xml);
    }
}
