<?php

namespace Tests\Feature\Services;

use App\Models\CoverLetter;
use App\Models\ResumePersonalInfo;
use App\Models\ResumeVersion;
use App\Services\CoverLetterDocumentService;
use App\Services\Resume\SignatureImageService;
use DOMDocument;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;
use ZipArchive;

class CoverLetterDocumentServiceTest extends TestCase
{
    use DatabaseTransactions;

    protected string $templatePath;

    /** @var array<int, string> */
    protected array $generatedFiles = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->templatePath = dirname(__DIR__, 3).'/resources/resume/2026 template.docx';

        if (! file_exists($this->templatePath)) {
            $this->markTestSkipped('Shared template not found: '.$this->templatePath);
        }

        config(['resume.template' => $this->templatePath]);
    }

    protected function tearDown(): void
    {
        foreach ($this->generatedFiles as $path) {
            if (file_exists($path)) {
                unlink($path);
            }
        }

        parent::tearDown();
    }

    public function test_build_docx_data_includes_url_from_personal_info(): void
    {
        $coverLetter = $this->makeCoverLetter();

        $service = app(CoverLetterDocumentService::class);
        $buildDocxData = new \ReflectionMethod($service, 'buildDocxData');
        $data = $buildDocxData->invoke($service, $coverLetter);

        $this->assertSame('jasonvertucio.com', $data['url']);
    }

    public function test_generates_a_docx_from_the_shared_template(): void
    {
        $coverLetter = $this->makeCoverLetter();

        $result = app(CoverLetterDocumentService::class)->generateDocx($coverLetter);

        $this->assertTrue($result['success'], $result['error'] ?? '');
        $this->assertFileExists($result['path']);
        $this->assertGreaterThan(0, filesize($result['path']));
        $this->assertSame($result['path'], $coverLetter->fresh()->docx_path);

        $this->generatedFiles[] = $result['path'];
    }

    public function test_letter_blocks_appear_in_order(): void
    {
        $coverLetter = $this->makeCoverLetter([
            'date' => '2026-03-14',
            'company_address' => "Acme Corp\n123 Main St\nPhiladelphia, PA",
            'greeting' => 'Dear Hiring Manager,',
            'message_body' => 'I am writing about the Engineer role.',
            'closing' => 'Sincerely,',
            'signature' => 'Jason Vertucio',
        ]);

        $result = app(CoverLetterDocumentService::class)->generateDocx($coverLetter);
        $this->generatedFiles[] = $result['path'];

        $xml = $this->readZipEntry($result['path'], 'word/document.xml');

        $positions = [];
        foreach ([
            'date' => 'March 14, 2026',
            'address' => '123 Main St',
            'greeting' => 'Dear Hiring Manager,',
            'body' => 'I am writing about the Engineer role.',
            'closing' => 'Sincerely,',
            // The name appears twice — in the letterhead and as the sign-off —
            // so the sign-off is the last occurrence.
            'signature' => 'Jason Vertucio',
        ] as $block => $needle) {
            $position = $block === 'signature'
                ? strrpos($xml, $needle)
                : strpos($xml, $needle);

            $this->assertNotFalse($position, "Cover letter should contain the {$block} block");
            $positions[$block] = $position;
        }

        $this->assertSame(
            ['date', 'address', 'greeting', 'body', 'closing', 'signature'],
            array_keys($positions),
        );

        $previous = -1;
        foreach ($positions as $block => $position) {
            $this->assertGreaterThan($previous, $position, "The {$block} block is out of order");
            $previous = $position;
        }
    }

    public function test_header_placeholders_are_substituted(): void
    {
        $coverLetter = $this->makeCoverLetter();

        $result = app(CoverLetterDocumentService::class)->generateDocx($coverLetter);
        $this->generatedFiles[] = $result['path'];

        $xml = $this->readZipEntry($result['path'], 'word/document.xml');

        $this->assertStringContainsString('Jason Vertucio', $xml);
        $this->assertStringContainsString('jasonvertucio.com', $xml);

        foreach (['name', 'title', 'email', 'phone', 'url'] as $token) {
            $this->assertStringNotContainsString('{'.$token.'}', $xml);
        }
    }

    public function test_document_xml_is_well_formed(): void
    {
        $coverLetter = $this->makeCoverLetter([
            'company_address' => "Acme & Co.\n<Suite 5>",
            'message_body' => "First paragraph.\n\n- A bullet\n- Another bullet\n\nClosing thought with **bold**.",
        ]);

        $result = app(CoverLetterDocumentService::class)->generateDocx($coverLetter);
        $this->generatedFiles[] = $result['path'];

        $dom = new DOMDocument;
        $this->assertTrue($dom->loadXML($this->readZipEntry($result['path'], 'word/document.xml')));
    }

    public function test_signature_is_embedded_at_two_inches_tall(): void
    {
        $coverLetter = $this->makeCoverLetter();

        $result = app(CoverLetterDocumentService::class)->generateDocx($coverLetter);
        $this->generatedFiles[] = $result['path'];

        $signatureBytes = $this->readZipEntry($result['path'], 'word/media/signature.png');
        $this->assertNotSame('', $signatureBytes, 'Signature media should be embedded in the package');

        $xml = $this->readZipEntry($result['path'], 'word/document.xml');
        $this->assertStringContainsString('<w:drawing>', $xml);
        $this->assertStringContainsString('cy="1828800"', $xml);

        $rels = $this->readZipEntry($result['path'], 'word/_rels/document.xml.rels');
        $this->assertStringContainsString('Target="media/signature.png"', $rels);
    }

    public function test_generates_a_pdf_with_no_docx_present(): void
    {
        $this->requireWeasyprint();

        $coverLetter = $this->makeCoverLetter();
        $this->assertNull($coverLetter->docx_path);

        $result = app(CoverLetterDocumentService::class)->generatePdf($coverLetter);

        $this->assertTrue($result['success'], $result['error'] ?? '');
        $this->assertFileExists($result['path']);
        $this->assertSame($result['path'], $coverLetter->fresh()->pdf_path);

        $this->generatedFiles[] = $result['path'];
    }

    public function test_pdf_generation_succeeds_without_the_signature_when_its_source_is_unavailable(): void
    {
        $this->requireWeasyprint();

        Log::spy();

        $this->app->instance(
            SignatureImageService::class,
            new SignatureImageService(sys_get_temp_dir().'/not-a-real-signature-'.uniqid().'.png')
        );

        $coverLetter = $this->makeCoverLetter();

        $result = app(CoverLetterDocumentService::class)->generatePdf($coverLetter);
        $this->generatedFiles[] = $result['path'] ?? '';

        $this->assertTrue($result['success'], $result['error'] ?? '');
        $this->assertFileExists($result['path']);
    }

    protected function requireWeasyprint(): void
    {
        exec('command -v '.escapeshellarg((string) config('resume.weasyprint')).' 2>/dev/null', $output, $exitCode);

        if ($exitCode !== 0) {
            $this->markTestSkipped('weasyprint binary not available in this environment.');
        }
    }

    public function test_generation_succeeds_without_the_signature_when_its_source_is_unavailable(): void
    {
        Log::spy();

        $this->app->instance(
            SignatureImageService::class,
            new SignatureImageService(sys_get_temp_dir().'/not-a-real-signature-'.uniqid().'.png')
        );

        $coverLetter = $this->makeCoverLetter();

        $result = app(CoverLetterDocumentService::class)->generateDocx($coverLetter);
        $this->generatedFiles[] = $result['path'];

        $this->assertTrue($result['success'], $result['error'] ?? '');

        $zip = new ZipArchive;
        $zip->open($result['path']);
        $this->assertFalse($zip->getFromName('word/media/signature.png'), 'No signature media should be embedded');
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        $this->assertStringNotContainsString('<w:drawing>', $xml);
        $this->assertStringContainsString('Jason Vertucio', $xml, 'The typed signature name is still present');

        Log::shouldHaveReceived('warning')
            ->withArgs(fn (string $message, array $context = []): bool => str_contains($message, 'not found'));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function makeCoverLetter(array $attributes = []): CoverLetter
    {
        $resumeVersion = ResumeVersion::factory()->create();
        ResumePersonalInfo::factory()->create([
            'version_id' => $resumeVersion->id,
            'name' => 'Jason Vertucio',
            'url' => 'https://jasonvertucio.com',
        ]);

        return CoverLetter::create(array_merge([
            'resume_version_id' => $resumeVersion->id,
            'targeted_resume_id' => null,
            'company_name' => 'Acme Corp',
            'position' => 'Engineer',
            'date' => now()->toDateString(),
            'company_address' => '123 Main St',
            'greeting' => 'Dear Hiring Manager,',
            'message_body' => 'Thank you for your consideration.',
            'closing' => 'Sincerely,',
            'signature' => 'Jason Vertucio',
        ], $attributes));
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
