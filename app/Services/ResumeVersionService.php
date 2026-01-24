<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use RuntimeException;

class ResumeVersionService
{
    protected string $versionPath;
    protected string $savedDocumentsPath;
    protected string $templatePath;
    protected string $scriptPath;
    protected ResumeDataService $dataService;

    public function __construct(ResumeDataService $dataService)
    {
        $this->versionPath = config('resume.version_file');
        $this->savedDocumentsPath = config('resume.saved_documents');
        $this->templatePath = config('resume.template');
        $this->scriptPath = base_path('scripts/generate-resume.js');
        $this->dataService = $dataService;
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
     * Get the path to the latest DOCX file by current version
     */
    public function getLatestDocxPath(): ?string
    {
        $version = $this->getCurrentVersion();
        $filename = $this->getDocxFilename($version);
        $path = $this->savedDocumentsPath . '/' . $filename;

        return file_exists($path) ? $path : null;
    }

    /**
     * Get the DOCX filename for a given version
     */
    public function getDocxFilename(string $version): string
    {
        return "{$version} Jason Vertucio.docx";
    }

    /**
     * Check if a DOCX exists for the current version
     */
    public function docxExistsForCurrentVersion(): bool
    {
        return $this->getLatestDocxPath() !== null;
    }

    /**
     * Generate a DOCX file for the current version
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
            $data = $this->dataService->getDocxData();
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

            Log::info('Resume DOCX generated successfully', [
                'version' => $version,
                'path' => $outputPath,
                'size' => $result['size'] ?? null,
            ]);

            return $result;

        } finally {
            // Clean up temp file
            if (file_exists($tempDataPath)) {
                unlink($tempDataPath);
            }
        }
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
