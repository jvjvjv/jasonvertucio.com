<?php

namespace App\Services;

use App\Models\CoverLetter;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Log;
use ZipArchive;

class CoverLetterDocumentService
{
    protected const NAMESPACE_W = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    protected string $templatePath;

    protected string $scriptPath;

    protected string $outputDir;

    public function __construct()
    {
        $this->templatePath = base_path('resources/resume/2026 cover letter template.docx');
        $this->scriptPath = base_path('scripts/generate-cover-letter.js');
        $this->outputDir = storage_path('app/cover-letters');
    }

    /**
     * Generate a DOCX file for the given cover letter.
     *
     * @return array{success: bool, path?: string, error?: string}
     */
    public function generateDocx(CoverLetter $coverLetter): array
    {
        $filename = $coverLetter->generateFilename();
        $outputPath = $this->outputDir.'/'.$filename.'.docx';
        $preparedTemplatePath = storage_path('app/temp/cover-letter-template-'.uniqid().'.docx');

        if (! file_exists($this->outputDir)) {
            mkdir($this->outputDir, 0755, true);
        }

        $tempDataPath = storage_path('app/temp/cover-letter-data-'.uniqid().'.json');
        $tempDir = dirname($tempDataPath);
        if (! file_exists($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        try {
            $data = $this->buildDocxData($coverLetter);
            file_put_contents($tempDataPath, json_encode($data, JSON_PRETTY_PRINT));

            $this->prepareTemplateForDocxtemplater($preparedTemplatePath, array_keys($data));

            $command = sprintf(
                'node %s %s %s %s 2>&1',
                escapeshellarg($this->scriptPath),
                escapeshellarg($preparedTemplatePath),
                escapeshellarg($tempDataPath),
                escapeshellarg($outputPath)
            );

            $output = shell_exec($command);
            $result = json_decode($output, true);

            if (! $result) {
                Log::error('Cover letter DOCX generation failed: Invalid JSON output', [
                    'output' => $output,
                    'command' => $command,
                ]);

                return [
                    'success' => false,
                    'error' => 'Invalid output from generator script: '.$output,
                ];
            }

            if (! $result['success']) {
                Log::error('Cover letter DOCX generation failed', $result);

                return $result;
            }

            $coverLetter->docx_path = $outputPath;
            $coverLetter->save();

            return $result;

        } finally {
            if (file_exists($tempDataPath)) {
                unlink($tempDataPath);
            }

            if (file_exists($preparedTemplatePath)) {
                unlink($preparedTemplatePath);
            }
        }
    }

    /**
     * Word may split placeholders into separate runs (e.g. "{", "url", "}").
     * This normalizes supported placeholders back into single {token} runs so
     * docxtemplater can bind them correctly.
     *
     * @param  array<int, string>  $supportedTokens
     */
    protected function prepareTemplateForDocxtemplater(string $destinationPath, array $supportedTokens): void
    {
        copy($this->templatePath, $destinationPath);

        $zip = new ZipArchive;
        if ($zip->open($destinationPath) !== true) {
            return;
        }

        $xml = $zip->getFromName('word/document.xml');
        if ($xml === false) {
            $zip->close();

            return;
        }

        $normalizedXml = $this->normalizeSplitPlaceholders($xml, $supportedTokens);
        $zip->addFromString('word/document.xml', $normalizedXml);
        $zip->close();
    }

    /**
     * @param  array<int, string>  $supportedTokens
     */
    protected function normalizeSplitPlaceholders(string $xml, array $supportedTokens): string
    {
        $dom = new DOMDocument;
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

        $tokenMap = array_fill_keys($supportedTokens, true);
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

            if ($endIndex === null || ! isset($tokenMap[$token])) {
                continue;
            }

            $nodes[$i]->nodeValue = '{'.$token.'}';
            for ($k = $i + 1; $k <= $endIndex; $k++) {
                $nodes[$k]->nodeValue = '';
            }

            $i = $endIndex;
        }

        return $dom->saveXML() ?: $xml;
    }

    /**
     * Generate a PDF from the DOCX file for the given cover letter.
     *
     * @return array{success: bool, path?: string, error?: string}
     */
    public function generatePdf(CoverLetter $coverLetter): array
    {
        if (! $coverLetter->docxExists()) {
            return [
                'success' => false,
                'error' => 'DOCX file not found. Generate DOCX first.',
            ];
        }

        $filename = $coverLetter->generateFilename();
        $pdfPath = $this->outputDir.'/'.$filename.'.pdf';

        try {
            $command = sprintf(
                'libreoffice --headless -env:UserInstallation=file:///tmp/libreoffice-user --convert-to pdf --outdir %s %s 2>&1',
                escapeshellarg($this->outputDir),
                escapeshellarg($coverLetter->docx_path)
            );

            exec($command, $output, $exitCode);

            if ($exitCode !== 0) {
                Log::error('Cover letter PDF conversion failed', [
                    'command' => $command,
                    'output' => implode("\n", $output),
                    'exitCode' => $exitCode,
                ]);

                return [
                    'success' => false,
                    'error' => 'LibreOffice conversion failed: '.implode("\n", $output),
                ];
            }

            if (! file_exists($pdfPath)) {
                return [
                    'success' => false,
                    'error' => 'PDF file was not created.',
                ];
            }

            $coverLetter->pdf_path = $pdfPath;
            $coverLetter->save();

            return [
                'success' => true,
                'path' => $pdfPath,
                'size' => filesize($pdfPath),
            ];

        } catch (\Exception $e) {
            Log::error('Cover letter PDF generation exception', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Build the data array for docxtemplater.
     *
     * @return array<string, string>
     */
    protected function buildDocxData(CoverLetter $coverLetter): array
    {
        $personalInfo = $coverLetter->resumeVersion?->personalInfo;

        return [
            'name' => $personalInfo?->name ?? '',
            'title' => $personalInfo?->title ?? '',
            'email' => $personalInfo?->email ?? '',
            'phone' => $personalInfo?->phone ?? '',
            'url' => $this->formatDisplayUrl($personalInfo?->url ?? 'https://jasonvertucio.com'),
            'date' => $coverLetter->date->format('F j, Y'),
            'companyAddress' => $coverLetter->company_address ?? '',
            'greeting' => $coverLetter->greeting ?? '',
            'messageBody' => $coverLetter->message_body ?? '',
            'closing' => $coverLetter->closing ?? '',
            'signature' => $coverLetter->signature ?? '',
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
