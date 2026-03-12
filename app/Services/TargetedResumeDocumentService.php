<?php

namespace App\Services;

use App\Models\TargetedResume;
use Illuminate\Support\Facades\Log;

class TargetedResumeDocumentService
{
    protected string $templatePath;

    protected string $scriptPath;

    protected string $outputDir;

    public function __construct()
    {
        $this->templatePath = base_path('resources/resume/2026 targeted resume template.docx');
        $this->scriptPath = base_path('scripts/generate-targeted-resume.js');
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

        $tempDataPath = storage_path('app/temp/targeted-resume-data-' . uniqid() . '.json');
        $tempDir = dirname($tempDataPath);
        if (!file_exists($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        try {
            $data = $this->buildTemplateData($targetedResume);
            file_put_contents($tempDataPath, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

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
                Log::error('Targeted resume DOCX generation failed: Invalid JSON output', [
                    'output' => $output,
                    'command' => $command,
                    'targeted_resume_id' => $targetedResume->id,
                ]);

                return [
                    'success' => false,
                    'error' => 'Invalid output from targeted resume generator script: ' . $output,
                ];
            }

            if (!$result['success']) {
                Log::error('Targeted resume DOCX generation failed', $result + [
                    'targeted_resume_id' => $targetedResume->id,
                ]);

                return $result;
            }

            $targetedResume->docx_path = $outputPath;
            $targetedResume->save();

            Log::info('Targeted resume DOCX generated successfully', [
                'id' => $targetedResume->id,
                'path' => $outputPath,
                'size' => $result['size'] ?? null,
            ]);

            return $result;
        } finally {
            if (file_exists($tempDataPath)) {
                unlink($tempDataPath);
            }
        }
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

            Log::info('Targeted resume PDF generated successfully', [
                'id' => $targetedResume->id,
                'path' => $pdfPath,
                'size' => filesize($pdfPath),
            ]);

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
     * @return array{name: string, title: string, email: string, phone: string, resume: string}
     */
    protected function buildTemplateData(TargetedResume $targetedResume): array
    {
        $targetedResume->loadMissing('resumeVersion.personalInfo');

        $personalInfo = $targetedResume->resumeVersion?->personalInfo;
        $resumeHtml = (string) data_get($targetedResume->tailored_data, 'html', '');

        return [
            'name' => $personalInfo?->name ?? '',
            'title' => $personalInfo?->title ?? '',
            'email' => $personalInfo?->email ?? '',
            'phone' => $personalInfo?->phone ?? '',
            'resume' => $this->htmlToResumeText($resumeHtml),
        ];
    }

    protected function htmlToResumeText(string $html): string
    {
        $formatted = preg_replace('/<li[^>]*>/i', "\n• ", $html) ?? $html;
        $formatted = preg_replace('/<\/(p|h1|h2|h3|ul|ol|li)>/i', "\n", $formatted) ?? $formatted;
        $formatted = preg_replace('/<br\s*\/?\s*>/i', "\n", $formatted) ?? $formatted;
        $formatted = strip_tags($formatted);
        $formatted = html_entity_decode($formatted, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $formatted = preg_replace("/\n{3,}/", "\n\n", $formatted) ?? $formatted;

        return trim($formatted);
    }
}
