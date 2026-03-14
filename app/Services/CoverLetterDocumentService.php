<?php

namespace App\Services;

use App\Models\CoverLetter;
use Illuminate\Support\Facades\Log;

class CoverLetterDocumentService
{
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
        $outputPath = $this->outputDir . '/' . $filename . '.docx';

        if (!file_exists($this->outputDir)) {
            mkdir($this->outputDir, 0755, true);
        }

        $tempDataPath = storage_path('app/temp/cover-letter-data-' . uniqid() . '.json');
        $tempDir = dirname($tempDataPath);
        if (!file_exists($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        try {
            $data = $this->buildDocxData($coverLetter);
            file_put_contents($tempDataPath, json_encode($data, JSON_PRETTY_PRINT));

            $command = sprintf(
                'node %s %s %s %s 2>&1',
                escapeshellarg($this->scriptPath),
                escapeshellarg($this->templatePath),
                escapeshellarg($tempDataPath),
                escapeshellarg($outputPath)
            );

            $output = shell_exec($command);
            $result = json_decode($output, true);

            if (!$result) {
                Log::error('Cover letter DOCX generation failed: Invalid JSON output', [
                    'output' => $output,
                    'command' => $command,
                ]);
                return [
                    'success' => false,
                    'error' => 'Invalid output from generator script: ' . $output,
                ];
            }

            if (!$result['success']) {
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
        }
    }

    /**
     * Generate a PDF from the DOCX file for the given cover letter.
     *
     * @return array{success: bool, path?: string, error?: string}
     */
    public function generatePdf(CoverLetter $coverLetter): array
    {
        if (!$coverLetter->docxExists()) {
            return [
                'success' => false,
                'error' => 'DOCX file not found. Generate DOCX first.',
            ];
        }

        $filename = $coverLetter->generateFilename();
        $pdfPath = $this->outputDir . '/' . $filename . '.pdf';

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
                    'error' => 'LibreOffice conversion failed: ' . implode("\n", $output),
                ];
            }

            if (!file_exists($pdfPath)) {
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
            'date' => $coverLetter->date->format('F j, Y'),
            'companyAddress' => $coverLetter->company_address ?? '',
            'greeting' => $coverLetter->greeting ?? '',
            'messageBody' => $coverLetter->message_body ?? '',
            'closing' => $coverLetter->closing ?? '',
            'signature' => $coverLetter->signature ?? '',
        ];
    }
}
