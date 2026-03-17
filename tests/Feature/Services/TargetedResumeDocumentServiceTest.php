<?php

namespace Tests\Feature\Services;

use App\Models\ResumePersonalInfo;
use App\Models\ResumeVersion;
use App\Models\TargetedResume;
use App\Services\Resume\MarkdownToOpenXmlConverter;
use App\Services\TargetedResumeDocumentService;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;
use ZipArchive;

/**
 * Tests the DOCX generation logic by directly exercising the protected methods
 * via reflection, with minimal database setup when template data needs model-backed relations.
 */
class TargetedResumeDocumentServiceTest extends TestCase
{
    use DatabaseTransactions;

    protected TargetedResumeDocumentService $service;

    protected string $templatePath;

    protected string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->templatePath = dirname(__DIR__, 3) . '/resources/resume/2026 targeted resume template.docx';

        if (!file_exists($this->templatePath)) {
            $this->markTestSkipped('Template file not found: ' . $this->templatePath);
        }

        $this->tempDir = sys_get_temp_dir() . '/targeted-resume-test-' . uniqid();
        mkdir($this->tempDir, 0755, true);

        $this->service = new TargetedResumeDocumentService(new MarkdownToOpenXmlConverter());
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempDir)) {
            array_map('unlink', glob($this->tempDir . '/*'));
            rmdir($this->tempDir);
        }

        parent::tearDown();
    }

    public function testSimplePlaceholdersAreReplaced(): void
    {
        $outputPath = $this->generateDocx([
            'name' => 'Jason Vertucio',
            'title' => 'Senior Software Engineer',
            'email' => 'jason@example.com',
            'phone' => '555-123-4567',
            'resume' => '# Summary',
        ]);

        $xml = $this->readDocumentXml($outputPath);

        $this->assertStringContainsString('Jason Vertucio', $xml);
        $this->assertStringContainsString('Senior Software Engineer', $xml);
        $this->assertStringContainsString('jason@example.com', $xml);
        $this->assertStringContainsString('555-123-4567', $xml);
        $this->assertStringNotContainsString('{name}', $xml);
        $this->assertStringNotContainsString('{title}', $xml);
        $this->assertStringNotContainsString('{email}', $xml);
        $this->assertStringNotContainsString('{phone}', $xml);
    }

    public function testResumePlaceholderIsReplacedWithStyledParagraphs(): void
    {
        $outputPath = $this->generateDocx([
            'name' => 'Test',
            'title' => 'Test',
            'email' => 'test@test.com',
            'phone' => '555-0000',
            'resume' => "# Experience\n## Lead Developer\n### Acme Corp - NYC - 2020-2024\n- Built microservices",
        ]);

        $xml = $this->readDocumentXml($outputPath);

        $this->assertStringNotContainsString('{resume}', $xml);
        $this->assertStringContainsString('w:val="Heading1"', $xml);
        $this->assertStringContainsString('w:val="JobTitle"', $xml);
        $this->assertStringContainsString('w:val="CompanyInfo"', $xml);
        $this->assertStringContainsString('w:val="ListParagraph"', $xml);
        $this->assertStringContainsString('Experience', $xml);
        $this->assertStringContainsString('Lead Developer', $xml);
        $this->assertStringContainsString('Acme Corp - NYC - 2020-2024', $xml);
        $this->assertStringContainsString('Built microservices', $xml);
    }

    public function testGeneratedDocxIsValidZip(): void
    {
        $outputPath = $this->generateDocx([
            'name' => 'Test',
            'title' => 'Test',
            'email' => 'test@test.com',
            'phone' => '555-0000',
            'resume' => '# Summary',
        ]);

        $zip = new ZipArchive();
        $this->assertTrue($zip->open($outputPath) === true, 'Output should be a valid ZIP');
        $this->assertNotFalse($zip->getFromName('word/document.xml'), 'Should contain word/document.xml');
        $this->assertNotFalse($zip->getFromName('word/styles.xml'), 'Should contain word/styles.xml');
        $zip->close();
    }

    public function testStylesXmlIsPreservedUnchanged(): void
    {
        $zip = new ZipArchive();
        $zip->open($this->templatePath);
        $originalStyles = $zip->getFromName('word/styles.xml');
        $zip->close();

        $outputPath = $this->generateDocx([
            'name' => 'Test',
            'title' => 'Test',
            'email' => 'test@test.com',
            'phone' => '555-0000',
            'resume' => "# Experience\n## Engineer\n### Corp - NYC - 2020",
        ]);

        $zip = new ZipArchive();
        $zip->open($outputPath);
        $outputStyles = $zip->getFromName('word/styles.xml');
        $zip->close();

        $this->assertSame($originalStyles, $outputStyles, 'styles.xml should be preserved unchanged');
    }

    public function testXmlSpecialCharsInPlaceholdersAreEscaped(): void
    {
        $outputPath = $this->generateDocx([
            'name' => 'O\'Brien & Associates',
            'title' => 'Dev <Lead>',
            'email' => 'test@test.com',
            'phone' => '555-0000',
            'resume' => '# Summary',
        ]);

        $xml = $this->readDocumentXml($outputPath);

        $dom = new DOMDocument();
        $result = $dom->loadXML($xml);
        $this->assertTrue($result, 'Output XML should be well-formed even with special characters');
    }

    public function testKeyTechnologiesStyleApplied(): void
    {
        $outputPath = $this->generateDocx([
            'name' => 'Test',
            'title' => 'Test',
            'email' => 'test@test.com',
            'phone' => '555-0000',
            'resume' => "# Experience\n## Engineer\n### Corp - 2020\n- Key Technologies: PHP, Laravel, React",
        ]);

        $xml = $this->readDocumentXml($outputPath);

        $this->assertStringContainsString('w:val="KeyTechnologies"', $xml);
        $this->assertStringContainsString('Key Technologies: PHP, Laravel, React', $xml);
    }

    public function testDocumentXmlRemainsWellFormed(): void
    {
        $outputPath = $this->generateDocx([
            'name' => 'Jason Vertucio',
            'title' => 'Senior Engineer',
            'email' => 'jason@example.com',
            'phone' => '555-1234',
            'resume' => "# Summary\nExperienced engineer.\n\n# Skills\n## Frontend\n- React\n- Vue\n\n# Experience\n## Lead Dev\n### Corp - NYC - 2020-2024\n- Built things\n- Key Technologies: PHP, JS",
        ]);

        $xml = $this->readDocumentXml($outputPath);

        $dom = new DOMDocument();
        $result = $dom->loadXML($xml);
        $this->assertTrue($result, 'Generated document.xml must be well-formed XML');

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

        $sectPr = $xpath->query('//w:body/w:sectPr');
        $this->assertSame(1, $sectPr->length, 'Document should still have exactly one sectPr');
    }

    public function testBuildTemplateDataPrefersTailoredResumeTitleOverBaseResumeTitle(): void {
        $resumeVersion = ResumeVersion::factory()->create();
        ResumePersonalInfo::factory()->create([
            'version_id' => $resumeVersion->id,
            'title' => 'Full Stack Engineer',
        ]);
        $targetedResume = TargetedResume::factory()->create([
            'resume_version_id' => $resumeVersion->id,
            'title' => 'Senior Frontend Engineer',
            'tailored_data' => [
                'content' => '# Summary\nTailored summary',
            ],
        ]);

        $buildTemplateData = new \ReflectionMethod($this->service, 'buildTemplateData');
        $data = $buildTemplateData->invoke($this->service, $targetedResume);

        $this->assertSame('Senior Frontend Engineer', $data['title']);
    }

    /**
     * Generate a DOCX by directly manipulating the template (bypassing model/database).
     *
     * @param array{name: string, title: string, email: string, phone: string, resume: string} $data
     */
    protected function generateDocx(array $data): string
    {
        $outputPath = $this->tempDir . '/test-output.docx';

        copy($this->templatePath, $outputPath);

        $zip = new ZipArchive();
        $this->assertTrue($zip->open($outputPath) === true);

        $xml = $zip->getFromName('word/document.xml');
        $this->assertNotFalse($xml);

        $replaceSimple = new \ReflectionMethod($this->service, 'replaceSimplePlaceholders');
        $replaceResume = new \ReflectionMethod($this->service, 'replaceResumeContent');

        $xml = $replaceSimple->invoke($this->service, $xml, $data);
        $xml = $replaceResume->invoke($this->service, $xml, $data['resume']);

        $zip->addFromString('word/document.xml', $xml);
        $zip->close();

        return $outputPath;
    }

    protected function readDocumentXml(string $docxPath): string
    {
        $zip = new ZipArchive();
        $zip->open($docxPath);
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        return $xml;
    }
}
