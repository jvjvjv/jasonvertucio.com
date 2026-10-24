<?php

namespace Tests\Feature;

use App\Models\ResumeEducation;
use App\Models\ResumeExperience;
use App\Models\ResumePersonalInfo;
use App\Models\ResumeProject;
use App\Models\ResumeSkillCategory;
use App\Models\ResumeVersion;
use App\Services\DatabaseResumeDataService;
use App\Services\DatabaseResumeVersionService;
use App\Services\Resume\DocumentRenderer;
use App\Services\Resume\HtmlDocumentComposer;
use App\Services\Resume\MarkdownToHtmlConverter;
use App\Services\Resume\MarkdownToOpenXmlConverter;
use App\Services\Resume\PdfRenderer;
use App\Services\Resume\ResumeMarkdownComposer;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Verifies the WeasyPrint-rendered PDF's fidelity against the DOCX it is
 * rendered alongside: embedded fonts, no synthesized bold, and section-level
 * page parity. See document-pdf-rendering spec and CLAUDE.md's font-embedding
 * notes for why each of these has bitten this pipeline before.
 */
class PdfFidelityTest extends TestCase
{
    use DatabaseTransactions;

    protected string $templatePath;

    protected string $outputDir;

    protected DatabaseResumeVersionService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->templatePath = dirname(__DIR__, 2).'/resources/resume/2026 template.docx';

        if (! file_exists($this->templatePath)) {
            $this->markTestSkipped('Shared template not found: '.$this->templatePath);
        }

        $this->requireBinary('resume.weasyprint');
        $this->requireTool('pdffonts');
        $this->requireTool('pdfinfo');
        $this->requireTool('pdftotext');
        $this->requireTool('qpdf');

        $this->outputDir = sys_get_temp_dir().'/pdf-fidelity-test-'.uniqid();
        mkdir($this->outputDir, 0755, true);

        config([
            'resume.template' => $this->templatePath,
            'resume.saved_documents' => $this->outputDir,
        ]);

        $this->service = new DatabaseResumeVersionService(
            new DatabaseResumeDataService,
            new DocumentRenderer,
            new ResumeMarkdownComposer,
            new MarkdownToOpenXmlConverter,
            new MarkdownToHtmlConverter,
            new HtmlDocumentComposer,
            new PdfRenderer,
        );
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->outputDir);

        parent::tearDown();
    }

    protected function removeDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        foreach (glob($dir.'/*') ?: [] as $path) {
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }

        rmdir($dir);
    }

    public function test_pdf_embeds_every_required_face_with_no_substitution_or_thin_weight(): void
    {
        $this->seedResumeVersion('2026.1.0', withBold: true);

        $pdfPath = $this->service->generatePdf()['path'];

        $fonts = $this->pdfFonts($pdfPath);

        $this->assertNotEmpty($fonts);

        $expectedFamilies = ['Josefin-Sans-Bold', 'Montserrat', 'Montserrat-Bold', 'Josefin-Sans-Italic'];

        foreach ($fonts as $font) {
            $this->assertSame('yes', $font['emb'], "Font '{$font['name']}' must be embedded");
            $this->assertStringEndsNotWith('-Thin', $font['name'], "Font '{$font['name']}' must not collapse to a Thin instance");

            $bareName = preg_replace('/^[A-Z]{6}\+/', '', $font['name']);
            $matchesExpected = false;
            foreach ($expectedFamilies as $expected) {
                if (str_contains($bareName, $expected)) {
                    $matchesExpected = true;
                    break;
                }
            }

            $this->assertTrue($matchesExpected, "Font '{$font['name']}' is not one of the template's required faces — a substituted fallback family");
        }
    }

    public function test_no_synthesized_bold_appears_in_content_streams(): void
    {
        $this->seedResumeVersion('2026.1.0', withBold: true);

        $pdfPath = $this->service->generatePdf()['path'];

        $decompressedPath = $pdfPath.'.qdf';
        $process = new \Symfony\Component\Process\Process(['qpdf', '--qdf', '--object-streams=disable', $pdfPath, $decompressedPath]);
        $process->run();
        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());

        $contents = file_get_contents($decompressedPath);
        @unlink($decompressedPath);

        $this->assertStringNotContainsString('2 Tr', $contents, 'No text should use the synthesized-bold render mode (Tr 2)');
    }

    public function test_section_level_page_parity_with_the_docx(): void
    {
        $this->requireBinary('libreoffice');

        $this->seedResumeVersion('2026.1.0');

        $docxPath = $this->service->generateDocx()['path'];
        $pdfPath = $this->service->generatePdf()['path'];

        // LibreOffice's own output filename matches the DOCX's basename
        // minus its extension — the same name generatePdf() just wrote its
        // PDF under. Convert into a separate directory so the reference
        // conversion cannot overwrite the file this test is comparing it to.
        $referenceDir = $this->outputDir.'/reference';
        mkdir($referenceDir, 0755, true);

        $convertProcess = new \Symfony\Component\Process\Process([
            'libreoffice', '--headless', '-env:UserInstallation=file:///tmp/pdf-fidelity-lo-'.uniqid(),
            '--convert-to', 'pdf:writer_pdf_Export', '--outdir', $referenceDir, $docxPath,
        ]);
        $convertProcess->setTimeout(60);
        $convertProcess->run();
        $this->assertTrue($convertProcess->isSuccessful(), $convertProcess->getErrorOutput());

        $referencePdfPath = $referenceDir.'/'.pathinfo($docxPath, PATHINFO_FILENAME).'.pdf';
        $this->assertFileExists($referencePdfPath);

        $this->assertSame(
            $this->pdfPageCount($referencePdfPath),
            $this->pdfPageCount($pdfPath),
            'The rendered PDF must have the same page count as the DOCX converted output'
        );

        $referencePages = $this->pageTextByHeading($referencePdfPath);
        $renderedPages = $this->pageTextByHeading($pdfPath);

        foreach (['TECHNICAL SKILLS', 'PROFESSIONAL EXPERIENCE', 'SELECTED PROJECTS', 'EDUCATION'] as $heading) {
            $this->assertSame(
                $referencePages[$heading] ?? null,
                $renderedPages[$heading] ?? null,
                "Section '{$heading}' should begin on the same page in both formats"
            );
        }
    }

    public function test_a_two_column_skills_region_taller_than_one_page_continues_and_redistributes(): void
    {
        $markdown = "# Skills\n";
        for ($i = 1; $i <= 40; $i++) {
            $markdown .= "## Category {$i}\nSkill A{$i}, Skill B{$i}, Skill C{$i}\n";
        }

        $htmlConverter = new MarkdownToHtmlConverter;
        $body = $htmlConverter->convert($markdown);

        $composer = new HtmlDocumentComposer;
        $html = $composer->compose($body, ['name' => 'Test', 'title' => '', 'email' => '', 'phone' => '', 'url' => '']);

        $pdfPath = $this->outputDir.'/overflow.pdf';
        $result = (new PdfRenderer)->render($html, $pdfPath);
        $this->assertTrue($result['success'], $result['error'] ?? '');

        $this->assertGreaterThan(1, $this->pdfPageCount($pdfPath), 'A 40-category skills section must overflow onto a second page');

        $process = new \Symfony\Component\Process\Process(['pdftotext', $pdfPath, '-']);
        $process->run();
        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
        $text = $process->getOutput();

        for ($i = 1; $i <= 40; $i++) {
            $this->assertStringContainsString("Category {$i}", $text, "Category {$i} must not be lost");
            $this->assertStringContainsString("Skill A{$i}, Skill B{$i}, Skill C{$i}", $text, "Category {$i}'s skill list must not be lost");
        }

        // No skill category may be split across a column/page boundary — its
        // heading and its own skill line must stay next to each other, not
        // interleaved with another category's content in between.
        $layoutProcess = new \Symfony\Component\Process\Process(['pdftotext', '-layout', $pdfPath, '-']);
        $layoutProcess->run();
        $this->assertTrue($layoutProcess->isSuccessful(), $layoutProcess->getErrorOutput());
        $layoutText = $layoutProcess->getOutput();

        for ($i = 1; $i <= 40; $i++) {
            $this->assertMatchesRegularExpression("/Category {$i}\b/", $layoutText, "Category {$i} heading not found");
            preg_match("/Category {$i}\b/", $layoutText, $match, PREG_OFFSET_CAPTURE);
            $headingPos = $match[0][1];

            $window = substr($layoutText, $headingPos, 160);
            $this->assertMatchesRegularExpression(
                "/Skill A{$i},/",
                $window,
                "Category {$i}'s own skill list must immediately follow its heading, not another category's"
            );
        }
    }

    protected function requireBinary(string $configKeyOrBinary): void
    {
        $binary = str_contains($configKeyOrBinary, '.') ? (string) config($configKeyOrBinary) : $configKeyOrBinary;
        exec('command -v '.escapeshellarg($binary).' 2>/dev/null', $output, $exitCode);

        if ($exitCode !== 0) {
            $this->markTestSkipped("Binary not available in this environment: {$binary}");
        }
    }

    protected function requireTool(string $binary): void
    {
        $this->requireBinary($binary);
    }

    /**
     * @return array<int, array{name: string, emb: string}>
     */
    protected function pdfFonts(string $pdfPath): array
    {
        $process = new \Symfony\Component\Process\Process(['pdffonts', $pdfPath]);
        $process->run();
        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());

        $lines = array_slice(explode("\n", trim($process->getOutput())), 2);
        $fonts = [];

        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }

            $columns = preg_split('/\s+/', trim($line));
            $fonts[] = ['name' => $columns[0], 'emb' => $columns[4] ?? ''];
        }

        return $fonts;
    }

    protected function pdfPageCount(string $pdfPath): int
    {
        $process = new \Symfony\Component\Process\Process(['pdfinfo', $pdfPath]);
        $process->run();
        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());

        preg_match('/^Pages:\s+(\d+)/m', $process->getOutput(), $matches);

        return (int) ($matches[1] ?? 0);
    }

    /**
     * @return array<string, int>
     */
    protected function pageTextByHeading(string $pdfPath): array
    {
        $process = new \Symfony\Component\Process\Process(['pdftotext', '-layout', $pdfPath, '-']);
        $process->run();
        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());

        $pages = explode("\f", $process->getOutput());
        $result = [];

        foreach ($pages as $pageIndex => $pageText) {
            foreach (['TECHNICAL SKILLS', 'PROFESSIONAL EXPERIENCE', 'SELECTED PROJECTS', 'EDUCATION'] as $heading) {
                if (! isset($result[$heading]) && str_contains($pageText, $heading)) {
                    $result[$heading] = $pageIndex + 1;
                }
            }
        }

        return $result;
    }

    protected function seedResumeVersion(string $version, bool $withBold = false): ResumeVersion
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
            'summary' => $withBold ? 'Engineer who ships **cross-functional** work.' : 'Engineer who ships.',
        ]);

        $category = ResumeSkillCategory::factory()->create([
            'version_id' => $resumeVersion->id,
            'group' => 'top',
            'title' => 'Languages',
        ]);
        $category->skills()->createMany([
            ['name' => 'PHP', 'sort_order' => 0],
            ['name' => 'JavaScript', 'sort_order' => 1],
        ]);

        $experience = ResumeExperience::factory()->create([
            'version_id' => $resumeVersion->id,
            'job_title' => 'Lead Developer',
            'company' => 'Acme Corp',
            'location' => 'Philadelphia, PA',
            'date_start' => '2020',
            'date_end' => '2024',
            'job_title_label' => null,
        ]);
        $experience->bullets()->createMany([
            ['content' => $withBold ? 'Cut latency **40%** through caching' : 'Built microservices', 'sort_order' => 0],
            ['content' => 'Key Technologies: PHP, Laravel', 'sort_order' => 1],
        ]);

        $project = ResumeProject::factory()->create([
            'version_id' => $resumeVersion->id,
            'project_name' => 'Portfolio Site',
            'description' => 'A Laravel personal site.',
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
}
