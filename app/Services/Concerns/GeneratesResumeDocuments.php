<?php

namespace App\Services\Concerns;

use App\Contracts\ResumeDataServiceContract;
use App\Services\Resume\DocumentRenderer;
use App\Services\Resume\HtmlDocumentComposer;
use App\Services\Resume\MarkdownToHtmlConverter;
use App\Services\Resume\MarkdownToOpenXmlConverter;
use App\Services\Resume\PdfRenderer;
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
     * Get the Markdown to HTML converter, for the PDF body.
     */
    abstract protected function getHtmlConverter(): MarkdownToHtmlConverter;

    /**
     * Get the composer that assembles the full HTML document the PDF is rendered from.
     */
    abstract protected function getHtmlComposer(): HtmlDocumentComposer;

    /**
     * Get the PDF renderer.
     */
    abstract protected function getPdfRenderer(): PdfRenderer;

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
     * Generate a PDF for the current version, rendered from the same data
     * the DOCX is composed from rather than by converting the DOCX.
     *
     * @return array{success: bool, path?: string, error?: string}
     */
    public function generatePdf(): array
    {
        $version = $this->getCurrentVersion();
        $pdfPath = $this->savedDocumentsPath.'/'."{$version} Jason Vertucio.pdf";

        try {
            $data = $this->getDataService()->getDocxData();

            $bodyHtml = $this->getHtmlConverter()->convert($this->getMarkdownComposer()->compose($data));
            $html = $this->getHtmlComposer()->compose($bodyHtml, $this->buildPlaceholders($data));

            $result = $this->getPdfRenderer()->render($html, $pdfPath);

            if (! $result['success']) {
                Log::error('Resume PDF generation failed', $result);
            }

            return $result;
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
