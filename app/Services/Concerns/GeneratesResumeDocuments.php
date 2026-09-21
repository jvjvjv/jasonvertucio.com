<?php

namespace App\Services\Concerns;

use App\Contracts\ResumeDataServiceContract;
use App\Services\Resume\DocumentRenderer;
use App\Services\Resume\MarkdownToOpenXmlConverter;
use App\Services\Resume\ResumeMarkdownComposer;
use Illuminate\Support\Facades\Log;

trait GeneratesResumeDocuments
{
    protected string $savedDocumentsPath;

    protected string $templatePath;

    /**
     * Initialize document generation paths from config.
     */
    protected function initDocumentPaths(): void
    {
        $this->savedDocumentsPath = config('resume.saved_documents');
        $this->templatePath = config('resume.template');
    }

    /**
     * Get the data service instance.
     */
    abstract protected function getDataService(): ResumeDataServiceContract;

    /**
     * Get the shared document renderer.
     */
    abstract protected function getDocumentRenderer(): DocumentRenderer;

    /**
     * Get the composer that turns resume data into renderable Markdown.
     */
    abstract protected function getMarkdownComposer(): ResumeMarkdownComposer;

    /**
     * Get the Markdown to OpenXML converter.
     */
    abstract protected function getMarkdownConverter(): MarkdownToOpenXmlConverter;

    /**
     * Get the path to the latest DOCX file by current version.
     */
    public function getLatestDocxPath(): ?string
    {
        $version = $this->getCurrentVersion();
        $filename = $this->getDocxFilename($version);
        $path = $this->savedDocumentsPath.'/'.$filename;

        return file_exists($path) ? $path : null;
    }

    /**
     * Get the path to the latest PDF file by current version.
     */
    public function getLatestPdfPath(): ?string
    {
        $version = $this->getCurrentVersion();
        $filename = "{$version} Jason Vertucio.pdf";
        $path = $this->savedDocumentsPath.'/'.$filename;

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
        $outputPath = $this->savedDocumentsPath.'/'.$this->getDocxFilename($version);

        $data = $this->getDataService()->getDocxData();

        $result = $this->getDocumentRenderer()->render(
            $this->templatePath,
            $outputPath,
            $this->buildPlaceholders($data),
            $this->getMarkdownConverter()->convert($this->getMarkdownComposer()->compose($data)),
        );

        if (! $result['success']) {
            Log::error('Resume DOCX generation failed', $result);
        }

        return $result;
    }

    /**
     * Map the resume data onto the shared template's header placeholders.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, string>
     */
    protected function buildPlaceholders(array $data): array
    {
        return [
            'name' => (string) ($data['name'] ?? ''),
            'title' => (string) ($data['title'] ?? ''),
            'email' => (string) ($data['email'] ?? ''),
            'phone' => (string) ($data['phone'] ?? ''),
            'url' => $this->formatDisplayUrl($data['url'] ?? null),
        ];
    }

    /**
     * Strip the scheme and www prefix so the URL reads as plain text.
     */
    protected function formatDisplayUrl(?string $url): string
    {
        if ($url === null || trim($url) === '') {
            return '';
        }

        return preg_replace('/^(?:https?:\/\/)?(?:www\.)?/i', '', trim($url)) ?? trim($url);
    }

    /**
     * Generate a PDF from the DOCX file for the current version.
     *
     * @return array{success: bool, path?: string, error?: string}
     */
    public function generatePdf(): array
    {
        $docxPath = $this->getLatestDocxPath();

        if (! $docxPath) {
            return [
                'success' => false,
                'error' => 'DOCX file not found. Generate DOCX first.',
            ];
        }

        $version = $this->getCurrentVersion();
        $pdfFilename = "{$version} Jason Vertucio.pdf";
        $outputDir = $this->savedDocumentsPath;
        $pdfPath = $outputDir.'/'.$pdfFilename;

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
                    'error' => 'LibreOffice conversion failed: '.implode("\n", $output),
                ];
            }

            if (! file_exists($pdfPath)) {
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
