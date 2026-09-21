<?php

namespace Tests\Unit\Services\Resume;

use App\Services\Resume\DocumentRenderer;
use DOMDocument;
use DOMXPath;
use PHPUnit\Framework\TestCase;
use ZipArchive;

class DocumentRendererTest extends TestCase
{
    protected const NAMESPACE_W = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    protected DocumentRenderer $renderer;

    protected string $templatePath;

    protected string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->renderer = new DocumentRenderer;
        $this->templatePath = dirname(__DIR__, 4).'/resources/resume/2026 template.docx';

        if (! file_exists($this->templatePath)) {
            $this->markTestSkipped('Shared template not found: '.$this->templatePath);
        }

        $this->tempDir = sys_get_temp_dir().'/document-renderer-test-'.uniqid();
        mkdir($this->tempDir, 0755, true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempDir)) {
            array_map('unlink', glob($this->tempDir.'/*') ?: []);
            rmdir($this->tempDir);
        }

        parent::tearDown();
    }

    public function test_rendered_document_xml_is_well_formed(): void
    {
        $result = $this->render(
            ['name' => 'Jason Vertucio'],
            '<w:p><w:pPr><w:pStyle w:val="Heading1"/></w:pPr><w:r><w:t>Summary</w:t></w:r></w:p>'
        );

        $this->assertTrue($result['success'], $result['error'] ?? '');

        $dom = new DOMDocument;
        $this->assertTrue(
            $dom->loadXML($this->readDocumentXml($result['path'])),
            'Rendered document.xml must be well-formed XML'
        );
    }

    public function test_body_paragraphs_are_inserted_before_sect_pr(): void
    {
        $result = $this->render(
            [],
            '<w:p><w:r><w:t>Inserted body line</w:t></w:r></w:p>'
        );

        $dom = new DOMDocument;
        $dom->loadXML($this->readDocumentXml($result['path']));

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('w', self::NAMESPACE_W);

        $sectPr = $xpath->query('//w:body/w:sectPr');
        $this->assertSame(1, $sectPr->length, 'Template sectPr must survive body insertion');

        $following = $xpath->query('//w:body/w:sectPr/following-sibling::w:p[w:r/w:t="Inserted body line"]');
        $this->assertSame(0, $following->length, 'Body must not be inserted after sectPr');

        $preceding = $xpath->query('//w:body/w:p[w:r/w:t="Inserted body line"]');
        $this->assertSame(1, $preceding->length, 'Body paragraph should be present in the body');
    }

    public function test_placeholders_are_substituted(): void
    {
        $result = $this->render([
            'name' => 'Jason Vertucio',
            'title' => 'Senior Software Engineer',
            'email' => 'jason@example.com',
            'phone' => '555-123-4567',
            'url' => 'jasonvertucio.com',
        ]);

        $xml = $this->readDocumentXml($result['path']);

        $this->assertStringContainsString('Jason Vertucio', $xml);
        $this->assertStringContainsString('Senior Software Engineer', $xml);

        foreach (['name', 'title', 'email', 'phone', 'url'] as $token) {
            $this->assertStringNotContainsString('{'.$token.'}', $xml);
        }
    }

    public function test_placeholder_split_across_runs_is_substituted(): void
    {
        // {url} is stored as separate "{", "url", "}" runs in the real template.
        $result = $this->render(['url' => 'jasonvertucio.com']);

        $xml = $this->readDocumentXml($result['path']);

        $this->assertStringContainsString('jasonvertucio.com', $xml);
        $this->assertStringNotContainsString('url</w:t>', $xml);
    }

    public function test_missing_placeholder_value_renders_empty_and_still_succeeds(): void
    {
        $result = $this->render(['name' => '', 'title' => '', 'email' => '', 'phone' => '', 'url' => '']);

        $this->assertTrue($result['success']);

        $xml = $this->readDocumentXml($result['path']);
        $this->assertStringNotContainsString('{name}', $xml);
    }

    public function test_special_characters_in_placeholders_are_escaped(): void
    {
        $result = $this->render([
            'name' => "O'Brien & Associates",
            'title' => 'Dev <Lead>',
        ]);

        $dom = new DOMDocument;
        $this->assertTrue($dom->loadXML($this->readDocumentXml($result['path'])));
    }

    public function test_styles_xml_is_preserved_unchanged(): void
    {
        $originalStyles = $this->readZipEntry($this->templatePath, 'word/styles.xml');

        $result = $this->render(['name' => 'Test'], '<w:p><w:r><w:t>Body</w:t></w:r></w:p>');

        $this->assertSame($originalStyles, $this->readZipEntry($result['path'], 'word/styles.xml'));
    }

    public function test_missing_template_returns_error_and_writes_nothing(): void
    {
        $outputPath = $this->tempDir.'/missing-template.docx';
        $missingTemplate = $this->tempDir.'/does-not-exist.docx';

        $result = $this->renderer->render($missingTemplate, $outputPath, []);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString($missingTemplate, $result['error']);
        $this->assertFileDoesNotExist($outputPath);
    }

    public function test_unreadable_template_returns_error_and_leaves_no_partial_output(): void
    {
        $notADocx = $this->tempDir.'/not-a-docx.docx';
        file_put_contents($notADocx, 'this is plain text, not a zip archive');

        $outputPath = $this->tempDir.'/bad-template-output.docx';

        $result = $this->renderer->render($notADocx, $outputPath, []);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString($notADocx, $result['error']);
        $this->assertFileDoesNotExist($outputPath);
    }

    public function test_media_is_embedded_with_a_unique_relationship_id(): void
    {
        $outputPath = $this->tempDir.'/with-media.docx';

        $existingRels = $this->readZipEntry($this->templatePath, 'word/_rels/document.xml.rels');
        preg_match_all('/Id="(rId\d+)"/', $existingRels, $existingMatches);

        $capturedIds = [];

        $result = $this->renderer->render(
            $this->templatePath,
            $outputPath,
            [],
            function (array $relationshipIds) use (&$capturedIds): string {
                $capturedIds = $relationshipIds;

                return '<w:p><w:r><w:t>body</w:t></w:r></w:p>';
            },
            ['signature.png' => [
                'bytes' => $this->onePixelPng(),
                'extension' => 'png',
                'contentType' => 'image/png',
            ]]
        );

        $this->assertTrue($result['success'], $result['error'] ?? '');
        $this->assertArrayHasKey('signature.png', $capturedIds);
        $this->assertNotContains($capturedIds['signature.png'], $existingMatches[1], 'Relationship id must not collide');

        $rels = $this->readZipEntry($outputPath, 'word/_rels/document.xml.rels');
        $this->assertStringContainsString('Id="'.$capturedIds['signature.png'].'"', $rels);
        $this->assertStringContainsString('Target="media/signature.png"', $rels);

        $this->assertSame($this->onePixelPng(), $this->readZipEntry($outputPath, 'word/media/signature.png'));
    }

    public function test_png_content_type_default_is_declared_exactly_once(): void
    {
        $outputPath = $this->tempDir.'/content-type.docx';

        $this->renderer->render(
            $this->templatePath,
            $outputPath,
            [],
            '',
            [
                'signature.png' => ['bytes' => $this->onePixelPng(), 'extension' => 'png', 'contentType' => 'image/png'],
                'logo.png' => ['bytes' => $this->onePixelPng(), 'extension' => 'png', 'contentType' => 'image/png'],
            ]
        );

        $contentTypes = $this->readZipEntry($outputPath, '[Content_Types].xml');

        $this->assertSame(
            1,
            substr_count($contentTypes, '<Default Extension="png"'),
            'png Default content type must be declared exactly once'
        );
    }

    public function test_render_without_media_leaves_relationships_untouched(): void
    {
        $result = $this->render(['name' => 'Test']);

        $this->assertSame(
            $this->readZipEntry($this->templatePath, 'word/_rels/document.xml.rels'),
            $this->readZipEntry($result['path'], 'word/_rels/document.xml.rels')
        );
    }

    /**
     * @param  array<string, string>  $placeholders
     * @return array{success: bool, path?: string, size?: int, error?: string}
     */
    protected function render(array $placeholders = [], string $body = ''): array
    {
        return $this->renderer->render(
            $this->templatePath,
            $this->tempDir.'/output.docx',
            $placeholders,
            $body
        );
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

    protected function onePixelPng(): string
    {
        return base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
        );
    }
}
