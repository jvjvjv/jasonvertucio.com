<?php

namespace App\Services;

use App\Models\CoverLetter;
use App\Services\Resume\CoverLetterBodyComposer;
use App\Services\Resume\DocumentRenderer;
use App\Services\Resume\SignatureImageService;
use Illuminate\Support\Facades\Log;

class CoverLetterDocumentService
{
    protected string $outputDir;

    public function __construct(
        protected CoverLetterBodyComposer $composer,
        protected DocumentRenderer $renderer,
        protected SignatureImageService $signature,
    ) {
        $this->outputDir = storage_path('app/cover-letters');
    }

    /**
     * Generate a DOCX file for the given cover letter.
     *
     * @return array{success: bool, path?: string, size?: int, error?: string}
     */
    public function generateDocx(CoverLetter $coverLetter): array
    {
        $filename = $coverLetter->generateFilename();
        $outputPath = $this->outputDir.'/'.$filename.'.docx';

        try {
            $signature = $this->signature->build();

            $media = $signature === null
                ? []
                : [SignatureImageService::MEDIA_NAME => [
                    'bytes' => $signature['bytes'],
                    'extension' => $signature['extension'],
                    'contentType' => $signature['contentType'],
                ]];

            $result = $this->renderer->render(
                config('resume.template'),
                $outputPath,
                $this->buildDocxData($coverLetter),
                function (array $relationshipIds) use ($coverLetter, $signature): string {
                    $signatureImage = null;

                    if ($signature !== null && isset($relationshipIds[SignatureImageService::MEDIA_NAME])) {
                        $signatureImage = [
                            'relationshipId' => $relationshipIds[SignatureImageService::MEDIA_NAME],
                            'cx' => $signature['cx'],
                            'cy' => $signature['cy'],
                        ];
                    }

                    return $this->composer->compose($coverLetter, $signatureImage);
                },
                $media,
            );

            if (! $result['success']) {
                Log::error('Cover letter DOCX generation failed', [
                    'error' => $result['error'],
                    'cover_letter_id' => $coverLetter->id,
                ]);

                return $result;
            }

            $coverLetter->docx_path = $outputPath;
            $coverLetter->save();

            return $result;
        } catch (\Exception $e) {
            Log::error('Cover letter DOCX generation exception', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'cover_letter_id' => $coverLetter->id,
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
     * Build the header placeholder values for the shared template.
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
