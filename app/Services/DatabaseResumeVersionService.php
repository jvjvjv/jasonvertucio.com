<?php

namespace App\Services;

use App\Contracts\ResumeDataServiceContract;
use App\Contracts\ResumeVersionServiceContract;
use App\Models\ResumeVersion;
use App\Services\Concerns\GeneratesResumeDocuments;
use App\Services\Resume\DocumentRenderer;
use App\Services\Resume\MarkdownToOpenXmlConverter;
use App\Services\Resume\ResumeMarkdownComposer;
use RuntimeException;

class DatabaseResumeVersionService implements ResumeVersionServiceContract
{
    use GeneratesResumeDocuments;

    public function __construct(
        protected ResumeDataServiceContract $dataService,
        protected DocumentRenderer $renderer,
        protected ResumeMarkdownComposer $markdownComposer,
        protected MarkdownToOpenXmlConverter $markdownConverter,
    ) {
        $this->initDocumentPaths();
    }

    /**
     * Get the data service instance for document generation.
     */
    protected function getDataService(): ResumeDataServiceContract
    {
        return $this->dataService;
    }

    protected function getDocumentRenderer(): DocumentRenderer
    {
        return $this->renderer;
    }

    protected function getMarkdownComposer(): ResumeMarkdownComposer
    {
        return $this->markdownComposer;
    }

    protected function getMarkdownConverter(): MarkdownToOpenXmlConverter
    {
        return $this->markdownConverter;
    }

    /**
     * Get the current version from the database.
     */
    public function getCurrentVersion(): string
    {
        return ResumeVersion::current()->first()?->version ?? '0.0.0';
    }

    /**
     * Set the version in the database.
     */
    public function setVersion(string $version): void
    {
        if (! preg_match('/^\d{4}\.\d+\.\d+$/', $version)) {
            throw new RuntimeException('Invalid version format. Expected YYYY.X.X (e.g., 2026.1.0)');
        }

        // Unset all current versions
        ResumeVersion::where('is_current', true)->update(['is_current' => false]);

        // Create or find the version and set as current
        $versionModel = ResumeVersion::firstOrCreate(
            ['version' => $version],
        );
        $versionModel->update(['is_current' => true]);
    }

    /**
     * Get all available versions from the database.
     *
     * @return array<array{version: string, path: string, created: int}>
     */
    public function getAvailableVersions(): array
    {
        return ResumeVersion::orderByDesc('version')
            ->get()
            ->map(function ($version) {
                $docxPath = $this->savedDocumentsPath.'/'.$this->getDocxFilename($version->version);

                return [
                    'version' => $version->version,
                    'path' => $docxPath,
                    'created' => $version->created_at->timestamp,
                ];
            })
            ->filter(function ($item) {
                return file_exists($item['path']);
            })
            ->values()
            ->toArray();
    }
}
