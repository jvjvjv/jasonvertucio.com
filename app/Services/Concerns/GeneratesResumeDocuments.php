<?php

namespace App\Services\Concerns;

use App\Contracts\ResumeDataServiceContract;
use Illuminate\Support\Facades\Log;

trait GeneratesResumeDocuments
{
    protected string $savedDocumentsPath;
    protected string $templatePath;
    protected string $scriptPath;

    /**
     * Initialize document generation paths from config.
     */
    protected function initDocumentPaths(): void
    {
        $this->savedDocumentsPath = config('resume.saved_documents');
        $this->templatePath = config('resume.template');
        $this->scriptPath = base_path('scripts/generate-resume.js');
    }

    /**
     * Get the data service instance.
     */
    abstract protected function getDataService(): ResumeDataServiceContract;

    /**
     * Get the path to the latest DOCX file by current version.
     */
    public function getLatestDocxPath(): ?string
    {
        $version = $this->getCurrentVersion();
        $filename = $this->getDocxFilename($version);
        $path = $this->savedDocumentsPath . '/' . $filename;

        return file_exists($path) ? $path : null;
    }

    /**
     * Get the path to the latest PDF file by current version.
     */
    public function getLatestPdfPath(): ?string
    {
        $version = $this->getCurrentVersion();
        $filename = "{$version} Jason Vertucio.pdf";
        $path = $this->savedDocumentsPath . '/' . $filename;

        return file_exists($path) ? $path : null;
    }

    /**
     * Get the DOCX filename for a given version.
     */
    public function getDocxFilename(string $version): string
    {
        return "{$version} Jason Vertucio.docx";
    }

    /**
     * Check if a DOCX exists for the current version.
     */
    public function docxExistsForCurrentVersion(): bool
    {
        return $this->getLatestDocxPath() !== null;
    }

    /**
     * Generate a DOCX file for the current version.
     *
     * @return array{success: bool, path?: string, error?: string}
     */
    public function generateDocx(): array
    {
        $version = $this->getCurrentVersion();
        $outputPath = $this->savedDocumentsPath . '/' . $this->getDocxFilename($version);

        // Ensure output directory exists
        if (!file_exists($this->savedDocumentsPath)) {
            mkdir($this->savedDocumentsPath, 0755, true);
        }

        // Create temp file for resume data
        $tempDataPath = storage_path('app/temp/resume-data-' . uniqid() . '.json');
        $tempDir = dirname($tempDataPath);
        if (!file_exists($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        try {
            // Get flattened data for docxtemplater
            $data = $this->getDataService()->getDocxData();
            file_put_contents($tempDataPath, json_encode($data, JSON_PRETTY_PRINT));

            // Build command
            $command = sprintf(
                'node %s %s %s %s 2>&1',
                escapeshellarg($this->scriptPath),
                escapeshellarg($this->templatePath),
                escapeshellarg($tempDataPath),
                escapeshellarg($outputPath)
            );

            // Execute Node.js script
            $output = shell_exec($command);

            // Parse result
            $result = json_decode($output, true);

            if (!$result) {
                Log::error('Resume DOCX generation failed: Invalid JSON output', [
                    'output' => $output,
                    'command' => $command,
                ]);
                return [
                    'success' => false,
                    'error' => 'Invalid output from generator script: ' . $output,
                ];
            }

            if (!$result['success']) {
                Log::error('Resume DOCX generation failed', $result);
                return $result;
            }

            return $result;

        } finally {
            // Clean up temp file
            if (file_exists($tempDataPath)) {
                unlink($tempDataPath);
            }
        }
    }

    /**
     * Generate a PDF from the DOCX file for the current version.
     *
     * @return array{success: bool, path?: string, error?: string}
     */
    public function generatePdf(): array
    {
        $docxPath = $this->getLatestDocxPath();

        if (!$docxPath) {
            return [
                'success' => false,
                'error' => 'DOCX file not found. Generate DOCX first.',
            ];
        }

        $version = $this->getCurrentVersion();
        $pdfFilename = "{$version} Jason Vertucio.pdf";
        $outputDir = $this->savedDocumentsPath;
        $pdfPath = $outputDir . '/' . $pdfFilename;

        try {
            // Build LibreOffice command
            $command = sprintf(
                'libreoffice --headless -env:UserInstallation=file:///tmp/libreoffice-user --convert-to pdf --outdir %s %s 2>&1',
                escapeshellarg($outputDir),
                escapeshellarg($docxPath)
            );

            exec($command, $output, $exitCode);

            if ($exitCode !== 0) {
                Log::error('PDF conversion failed', [
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

            return [
                'success' => true,
                'path' => $pdfPath,
                'size' => filesize($pdfPath),
            ];

        } catch (\Exception $e) {
            Log::error('PDF generation exception', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}
