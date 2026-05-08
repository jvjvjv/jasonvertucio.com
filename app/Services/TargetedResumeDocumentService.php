<?php

namespace App\Services;

use App\Models\TargetedResume;
use App\Services\Resume\MarkdownToOpenXmlConverter;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Log;
use ZipArchive;

class TargetedResumeDocumentService
{
    protected const NAMESPACE_W = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    protected string $templatePath;

    protected string $outputDir;

    public function __construct(protected MarkdownToOpenXmlConverter $converter)
    {
        $this->templatePath = base_path('resources/resume/2026 targeted resume template.docx');
        $this->outputDir = storage_path('app/targeted-resumes');
    }

    /**
     * Generate a DOCX file for the given targeted resume.
     *
     * @return array{success: bool, path?: string, error?: string}
     */
    public function generateDocx(TargetedResume $targetedResume): array
    {
        $filename = $targetedResume->generateFilename();
        $outputPath = $this->outputDir . '/' . $filename . '.docx';

        if (!file_exists($this->outputDir)) {
            mkdir($this->outputDir, 0755, true);
        }

        try {
            $data = $this->buildTemplateData($targetedResume);

            copy($this->templatePath, $outputPath);

            $zip = new ZipArchive();
            if ($zip->open($outputPath) !== true) {
                return [
                    'success' => false,
                    'error' => 'Failed to open DOCX template as ZIP archive.',
                ];
            }

            $xml = $zip->getFromName('word/document.xml');
            if ($xml === false) {
                $zip->close();

                return [
                    'success' => false,
                    'error' => 'Failed to read word/document.xml from template.',
                ];
            }

            $xml = $this->replaceSimplePlaceholders($xml, $data);
            $xml = $this->replaceResumeContent($xml, $data['resume']);

            $zip->addFromString('word/document.xml', $xml);
            $zip->close();

            $targetedResume->docx_path = $outputPath;
            $targetedResume->save();

            $size = filesize($outputPath);

            return [
                'success' => true,
                'path' => $outputPath,
                'size' => $size,
            ];
        } catch (\Exception $e) {
            Log::error('Targeted resume DOCX generation failed', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'targeted_resume_id' => $targetedResume->id,
            ]);

            if (file_exists($outputPath)) {
                unlink($outputPath);
            }

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
    * Replace simple placeholders in the raw XML.
     *
     * @param array{name: string, title: string, email: string, phone: string, url: string, resume: string} $data
     */
    protected function replaceSimplePlaceholders(string $xml, array $data): string
    {
        $placeholders = [
            '{name}' => $data['name'],
            '{title}' => $data['title'],
            '{email}' => $data['email'],
            '{phone}' => $data['phone'],
            '{url}' => $data['url'],
        ];

        foreach ($placeholders as $placeholder => $value) {
            $xml = str_replace($placeholder, htmlspecialchars($value, ENT_XML1, 'UTF-8'), $xml);

            // Word sometimes splits placeholders into separate text runs (e.g. "{", "url", "}").
            $xml = $this->replaceSplitPlaceholderRuns($xml, trim($placeholder, '{}'), $value);
        }

        return $xml;
    }

    protected function replaceSplitPlaceholderRuns(string $xml, string $placeholder, string $value): string
    {
        $dom = new DOMDocument();
        if (! $dom->loadXML($xml)) {
            return $xml;
        }

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('w', self::NAMESPACE_W);
        $textNodes = $xpath->query('//w:t');

        if ($textNodes === false || $textNodes->length === 0) {
            return $xml;
        }

        $nodes = [];
        foreach ($textNodes as $textNode) {
            $nodes[] = $textNode;
        }

        $nodeCount = count($nodes);
        for ($i = 0; $i < $nodeCount; $i++) {
            if ($nodes[$i]->textContent !== '{') {
                continue;
            }

            $token = '';
            $endIndex = null;

            for ($j = $i + 1; $j < min($i + 12, $nodeCount); $j++) {
                $content = $nodes[$j]->textContent;
                if ($content === '}') {
                    $endIndex = $j;
                    break;
                }

                $token .= $content;
            }

            if ($endIndex === null || $token !== $placeholder) {
                continue;
            }

            $nodes[$i]->nodeValue = $value;
            for ($k = $i + 1; $k <= $endIndex; $k++) {
                $nodes[$k]->nodeValue = '';
            }

            $i = $endIndex;
        }

        return $dom->saveXML() ?: $xml;
    }

    /**
     * Find the {resume} placeholder, remove its <w:r>, and insert styled paragraphs.
     */
    protected function replaceResumeContent(string $xml, string $resumeMarkdown): string
    {
        $dom = new DOMDocument();
        $dom->preserveWhiteSpace = true;
        $dom->formatOutput = false;
        $dom->loadXML($xml);

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('w', self::NAMESPACE_W);

        $textNodes = $xpath->query('//w:t[contains(., "{resume}")]');

        if ($textNodes === false || $textNodes->length === 0) {
            return $dom->saveXML();
        }

        $resumeTextNode = $textNodes->item(0);
        $resumeRun = $resumeTextNode->parentNode;
        $resumeParagraph = $resumeRun->parentNode;
        $body = $xpath->query('//w:body')->item(0);

        $resumeRun->parentNode->removeChild($resumeRun);

        $openXmlFragment = $this->converter->convert($resumeMarkdown);

        if ($openXmlFragment === '') {
            return $dom->saveXML();
        }

        $sectPr = $xpath->query('//w:body/w:sectPr')->item(0);
        $insertBefore = $sectPr ?? null;

        $wrapperXml = '<?xml version="1.0" encoding="UTF-8"?>'
            . '<w:body xmlns:w="' . self::NAMESPACE_W . '">'
            . $openXmlFragment
            . '</w:body>';

        $fragmentDom = new DOMDocument();
        $fragmentDom->loadXML($wrapperXml);

        $paragraphs = $fragmentDom->getElementsByTagNameNS(self::NAMESPACE_W, 'p');
        $nodesToImport = [];
        foreach ($paragraphs as $p) {
            $nodesToImport[] = $p;
        }

        foreach ($nodesToImport as $p) {
            $imported = $dom->importNode($p, true);
            if ($insertBefore) {
                $body->insertBefore($imported, $insertBefore);
            } else {
                $body->appendChild($imported);
            }
        }

        return $dom->saveXML();
    }

    /**
     * Generate a PDF from the DOCX file for the given targeted resume.
     *
     * @return array{success: bool, path?: string, error?: string}
     */
    public function generatePdf(TargetedResume $targetedResume): array
    {
        if (!$targetedResume->docxExists()) {
            return [
                'success' => false,
                'error' => 'DOCX file not found. Generate DOCX first.',
            ];
        }

        $filename = $targetedResume->generateFilename();
        $pdfPath = $this->outputDir . '/' . $filename . '.pdf';

        try {
            $command = sprintf(
                'libreoffice --headless -env:UserInstallation=file:///tmp/libreoffice-user --convert-to pdf --outdir %s %s 2>&1',
                escapeshellarg($this->outputDir),
                escapeshellarg($targetedResume->docx_path)
            );

            exec($command, $output, $exitCode);

            if ($exitCode !== 0) {
                Log::error('Targeted resume PDF conversion failed', [
                    'command' => $command,
                    'output' => implode("\n", $output),
                    'exitCode' => $exitCode,
                    'targeted_resume_id' => $targetedResume->id,
                ]);

                return [
                    'success' => false,
                    'error' => 'LibreOffice conversion failed: ' . implode("\n", $output),
                ];
            }

            if (!file_exists($pdfPath)) {
                return [
                    'success' => false,
                    'error' => 'PDF file was not created.',
                ];
            }

            $targetedResume->pdf_path = $pdfPath;
            $targetedResume->save();

            return [
                'success' => true,
                'path' => $pdfPath,
                'size' => filesize($pdfPath),
            ];
        } catch (\Exception $e) {
            Log::error('Targeted resume PDF generation exception', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'targeted_resume_id' => $targetedResume->id,
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * @return array{name: string, title: string, email: string, phone: string, url: string, resume: string}
     */
    protected function buildTemplateData(TargetedResume $targetedResume): array
    {
        $targetedResume->loadMissing('resumeVersion.personalInfo');

        $personalInfo = $targetedResume->resumeVersion?->personalInfo;
        $resumeContent = (string) data_get($targetedResume->tailored_data, 'content', '');

        if ($resumeContent === '') {
            $resumeContent = (string) data_get($targetedResume->tailored_data, 'markdown', '');
        }

        return [
            'name' => $personalInfo?->name ?? '',
            'title' => $targetedResume->title ?? $personalInfo?->title ?? '',
            'email' => $personalInfo?->email ?? '',
            'phone' => $personalInfo?->phone ?? '',
            'url' => $this->formatDisplayUrl($personalInfo?->url),
            'resume' => $resumeContent,
        ];
    }

    protected function formatDisplayUrl(?string $url): string
    {
        if ($url === null || trim($url) === '') {
            return '';
        }

        return preg_replace('/^(?:https?:\/\/)?(?:www\.)?/i', '', trim($url)) ?? trim($url);
    }
}
