<?php

namespace Tests\Feature\Services;

use App\Models\ResumeEducation;
use App\Models\ResumeExperience;
use App\Models\ResumePersonalInfo;
use App\Models\ResumeProject;
use App\Models\ResumeSkillCategory;
use App\Models\ResumeVersion;
use App\Services\DatabaseResumeDataService;
use App\Services\DatabaseResumeVersionService;
use App\Services\Resume\DocumentRenderer;
use App\Services\Resume\MarkdownToOpenXmlConverter;
use App\Services\Resume\ResumeMarkdownComposer;
use DOMDocument;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;
use ZipArchive;

/**
 * End-to-end coverage for the main resume DOCX, which is now composed from
 * structured resume data rather than bound to template loops.
 */
class ResumeDocumentGenerationTest extends TestCase
{
    use DatabaseTransactions;

    protected string $templatePath;

    protected string $outputDir;

    protected DatabaseResumeVersionService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->templatePath = dirname(__DIR__, 3).'/resources/resume/2026 template.docx';

        if (! file_exists($this->templatePath)) {
            $this->markTestSkipped('Shared template not found: '.$this->templatePath);
        }

        $this->outputDir = sys_get_temp_dir().'/resume-docs-test-'.uniqid();
        mkdir($this->outputDir, 0755, true);

        // initDocumentPaths() reads config in the constructor, so configure first.
        config([
            'resume.template' => $this->templatePath,
            'resume.saved_documents' => $this->outputDir,
        ]);

        $this->service = new DatabaseResumeVersionService(
            new DatabaseResumeDataService,
            new DocumentRenderer,
            new ResumeMarkdownComposer,
            new MarkdownToOpenXmlConverter,
        );
    }

    protected function tearDown(): void
    {
        if (is_dir($this->outputDir)) {
            array_map('unlink', glob($this->outputDir.'/*') ?: []);
            rmdir($this->outputDir);
        }

        parent::tearDown();
    }

    public function test_generates_a_docx_for_the_current_version(): void
    {
        $this->seedResumeVersion('2026.1.0');

        $result = $this->service->generateDocx();

        $this->assertTrue($result['success'], $result['error'] ?? '');
        $this->assertFileExists($result['path']);
        $this->assertGreaterThan(0, filesize($result['path']));
        $this->assertSame($this->outputDir.'/2026.1.0 Jason Vertucio.docx', $result['path']);
    }

    public function test_header_placeholders_are_substituted(): void
    {
        $this->seedResumeVersion('2026.1.0');

        $xml = $this->readDocumentXml($this->service->generateDocx()['path']);

        $this->assertStringContainsString('Jason Vertucio', $xml);
        $this->assertStringContainsString('jasonvertucio.com', $xml);
        $this->assertStringNotContainsString('https://', $xml, 'The URL should render without its scheme');

        foreach (['name', 'title', 'email', 'phone', 'url'] as $token) {
            $this->assertStringNotContainsString('{'.$token.'}', $xml);
        }
    }

    public function test_sections_appear_in_resume_order(): void
    {
        $this->seedResumeVersion('2026.1.0');

        $xml = $this->readDocumentXml($this->service->generateDocx()['path']);

        $previous = -1;
        foreach (['Technical Skills', 'Professional Experience', 'Selected Projects', 'Education'] as $section) {
            $position = strpos($xml, '<w:t xml:space="preserve">'.$section.'</w:t>');
            $this->assertNotFalse($position, "Resume should contain the {$section} heading");
            $this->assertGreaterThan($previous, $position, "The {$section} section is out of order");
            $previous = $position;
        }
    }

    public function test_generated_document_carries_the_expected_styles(): void
    {
        $this->seedResumeVersion('2026.1.0');

        $xml = $this->readDocumentXml($this->service->generateDocx()['path']);

        foreach (['Heading1', 'JobTitle', 'CompanyInfo', 'ListParagraph'] as $styleId) {
            $this->assertStringContainsString(
                '<w:pStyle w:val="'.$styleId.'"/>',
                $xml,
                "Resume body should use the {$styleId} style"
            );
        }
    }

    public function test_document_xml_is_well_formed_with_awkward_content(): void
    {
        $this->seedResumeVersion('2026.1.0', awkwardContent: true);

        $result = $this->service->generateDocx();
        $this->assertTrue($result['success'], $result['error'] ?? '');

        $dom = new DOMDocument;
        $this->assertTrue(
            $dom->loadXML($this->readDocumentXml($result['path'])),
            'Ampersands, angle brackets, em-dashes and stray Markdown characters must not break the XML'
        );
    }

    public function test_real_current_resume_version_renders_when_one_exists(): void
    {
        $version = ResumeVersion::current()->first();

        if ($version === null) {
            $this->seedResumeVersion('2026.1.0');
        }

        $result = $this->service->generateDocx();
        $this->assertTrue($result['success'], $result['error'] ?? '');

        $dom = new DOMDocument;
        $this->assertTrue($dom->loadXML($this->readDocumentXml($result['path'])));
    }

    public function test_resume_contains_no_signature_image(): void
    {
        $this->seedResumeVersion('2026.1.0');

        $path = $this->service->generateDocx()['path'];

        $zip = new ZipArchive;
        $zip->open($path);
        $this->assertFalse($zip->getFromName('word/media/signature.png'), 'The resume must carry no signature');
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        $this->assertStringNotContainsString('<w:drawing>', $xml);
    }

    public function test_page_setup_is_preserved(): void
    {
        $this->seedResumeVersion('2026.1.0');

        $templateSectPr = $this->extractFinalSectPr($this->readZipEntry($this->templatePath, 'word/document.xml'));
        $outputSectPr = $this->extractFinalSectPr($this->readDocumentXml($this->service->generateDocx()['path']));

        $this->assertSame($templateSectPr, $outputSectPr, 'Page size and margins must survive body composition');
    }

    public function test_generation_fails_when_the_template_is_missing(): void
    {
        $this->seedResumeVersion('2026.1.0');

        $missingTemplate = $this->outputDir.'/missing-template.docx';
        config(['resume.template' => $missingTemplate]);

        $service = new DatabaseResumeVersionService(
            new DatabaseResumeDataService,
            new DocumentRenderer,
            new ResumeMarkdownComposer,
            new MarkdownToOpenXmlConverter,
        );

        $result = $service->generateDocx();

        $this->assertFalse($result['success']);
        $this->assertStringContainsString($missingTemplate, $result['error']);
    }

    protected function seedResumeVersion(string $version, bool $awkwardContent = false): ResumeVersion
    {
        $resumeVersion = ResumeVersion::factory()->create([
            'version' => $version,
            'is_current' => true,
        ]);

        ResumePersonalInfo::factory()->create([
            'version_id' => $resumeVersion->id,
            'name' => 'Jason Vertucio',
            'title' => 'Senior Software Engineer',
            'email' => 'jason@example.com',
            'phone' => '555-123-4567',
            'url' => 'https://jasonvertucio.com',
            'summary' => $awkwardContent
                ? 'Engineer & architect — ships <fast>, writes #1 code with *emphasis*.'
                : 'Engineer who ships.',
        ]);

        $category = ResumeSkillCategory::factory()->create([
            'version_id' => $resumeVersion->id,
            'group' => 'top',
            'title' => $awkwardContent ? 'Languages & Tools' : 'Languages',
        ]);
        $category->skills()->createMany([
            ['name' => 'PHP', 'sort_order' => 0],
            ['name' => $awkwardContent ? 'C# / .NET' : 'JavaScript', 'sort_order' => 1],
        ]);

        $experience = ResumeExperience::factory()->create([
            'version_id' => $resumeVersion->id,
            'job_title' => 'Lead Developer',
            'company' => $awkwardContent ? 'Acme & Sons <Holdings>' : 'Acme Corp',
            'location' => 'Philadelphia, PA',
            'date_start' => '2020',
            'date_end' => '2024',
            'job_title_label' => null,
        ]);
        $experience->bullets()->createMany([
            ['content' => $awkwardContent ? 'Cut latency 40% — from 500ms to 300ms & held it' : 'Built microservices', 'sort_order' => 0],
            ['content' => 'Key Technologies: PHP, Laravel', 'sort_order' => 1],
        ]);

        $project = ResumeProject::factory()->create([
            'version_id' => $resumeVersion->id,
            'project_name' => 'Portfolio Site',
            'description' => $awkwardContent ? 'A Laravel site — # not a heading' : 'A Laravel personal site.',
        ]);
        $project->bullets()->create(['content' => 'Server-rendered Blade views', 'sort_order' => 0]);

        ResumeEducation::factory()->create([
            'version_id' => $resumeVersion->id,
            'institution' => 'State University',
            'degree' => 'BS Computer Science',
            'date_start' => '2014',
            'date_end' => '2018',
            'description' => 'Graduated with honors.',
        ]);

        return $resumeVersion;
    }

    /**
     * The body-level sectPr holds the page setup. The Markdown converter also
     * emits sectPr elements inside w:pPr to switch column counts, so this must
     * select by position in the tree rather than by text matching.
     */
    protected function extractFinalSectPr(string $xml): string
    {
        $dom = new DOMDocument;
        $this->assertTrue($dom->loadXML($xml));

        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

        $sectPr = $xpath->query('//w:body/w:sectPr')->item(0);
        $this->assertNotNull($sectPr, 'Document should have a body-level sectPr');

        return $dom->saveXML($sectPr);
    }

    protected function readDocumentXml(string $docxPath): string
    {
        return $this->readZipEntry($docxPath, 'word/document.xml');
    }

    protected function readZipEntry(string $docxPath, string $entry): string
    {
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($docxPath) === true, "Could not open {$docxPath}");
        $contents = $zip->getFromName($entry);
        $zip->close();

        $this->assertNotFalse($contents, "Missing zip entry {$entry}");

        return $contents;
    }
}
