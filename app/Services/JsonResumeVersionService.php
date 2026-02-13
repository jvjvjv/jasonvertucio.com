<?php

namespace App\Services;

use App\Contracts\ResumeDataServiceContract;
use App\Contracts\ResumeVersionServiceContract;
use App\Services\Concerns\GeneratesResumeDocuments;
use RuntimeException;

class JsonResumeVersionService implements ResumeVersionServiceContract
{
    use GeneratesResumeDocuments;

    protected string $versionPath;
    protected ResumeDataServiceContract $dataService;

    public function __construct(ResumeDataServiceContract $dataService)
    {
        $this->versionPath = config('resume.version_file');
        $this->dataService = $dataService;
        $this->initDocumentPaths();
    }

    /**
     * Get the data service instance for document generation.
     */
    protected function getDataService(): ResumeDataServiceContract
    {
        return $this->dataService;
    }

    /**
     * Get the current version from version.json
     */
    public function getCurrentVersion(): string
    {
        if (!file_exists($this->versionPath)) {
            return '0.0.0';
        }

        $content = file_get_contents($this->versionPath);
        $version = json_decode($content, true);

        // Handle both string and direct value formats
        return is_string($version) ? $version : ($content ? trim($content) : '0.0.0');
    }

    /**
     * Set the version in version.json
     */
    public function setVersion(string $version): void
    {
        // Validate version format (YYYY.X.X)
        if (!preg_match('/^\d{4}\.\d+\.\d+$/', $version)) {
            throw new RuntimeException("Invalid version format. Expected YYYY.X.X (e.g., 2026.1.0)");
        }

        file_put_contents($this->versionPath, json_encode($version));
    }

    /**
     * Get all available DOCX versions
     *
     * @return array<array{version: string, path: string, created: int}>
     */
    public function getAvailableVersions(): array
    {
        if (!file_exists($this->savedDocumentsPath)) {
            return [];
        }

        $files = glob($this->savedDocumentsPath . '/*.docx');
        $versions = [];

        foreach ($files as $file) {
            $filename = basename($file);
            // Match pattern: "YYYY.X.X Jason Vertucio.docx"
            if (preg_match('/^(\d{4}\.\d+\.\d+) Jason Vertucio\.docx$/', $filename, $matches)) {
                $versions[] = [
                    'version' => $matches[1],
                    'path' => $file,
                    'created' => filemtime($file),
                ];
            }
        }

        // Sort by version descending
        usort($versions, function ($a, $b) {
            return version_compare($b['version'], $a['version']);
        });

        return $versions;
    }
}
