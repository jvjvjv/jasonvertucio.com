<?php

namespace App\Services;

use App\Models\CoverLetter;
use App\Services\Resume\CoverLetterBodyComposer;
use App\Services\Resume\CoverLetterHtmlComposer;
use App\Services\Resume\DocumentRenderer;
use App\Services\Resume\HtmlDocumentComposer;
use App\Services\Resume\PdfRenderer;
use App\Services\Resume\SignatureImageService;
use Illuminate\Support\Facades\Log;

class CoverLetterDocumentService
{
    protected string $outputDir;

    public function __construct(
        protected CoverLetterBodyComposer $composer,
        protected DocumentRenderer $renderer,
        protected SignatureImageService $signature,
        protected CoverLetterHtmlComposer $htmlComposer,
        protected HtmlDocumentComposer $htmlDocumentComposer,
        protected PdfRenderer $pdfRenderer,
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
     * Generate a PDF for the given cover letter, rendered from its stored
     * fields rather than by converting the DOCX.
     *
     * @return array{success: bool, path?: string, error?: string}
     */
    public function generatePdf(CoverLetter $coverLetter): array
    {
        $filename = $coverLetter->generateFilename();
        $pdfPath = $this->outputDir.'/'.$filename.'.pdf';
        $signatureTempPath = null;

        try {
            $signature = $this->signature->build();
            $signatureImage = null;

            if ($signature !== null) {
                $signatureTempPath = tempnam(sys_get_temp_dir(), 'resume-signature-').'.png';
                file_put_contents($signatureTempPath, $signature['bytes']);

                $signatureImage = [
                    'path' => $signatureTempPath,
                    'cx' => $signature['cx'],
                    'cy' => $signature['cy'],
                ];
            }

            $bodyHtml = $this->htmlComposer->compose($coverLetter, $signatureImage);
            $html = $this->htmlDocumentComposer->compose($bodyHtml, $this->buildDocxData($coverLetter));

            $result = $this->pdfRenderer->render($html, $pdfPath);

            if (! $result['success']) {
                Log::error('Cover letter PDF generation failed', $result + [
                    'cover_letter_id' => $coverLetter->id,
                ]);

                return $result;
            }

            $coverLetter->pdf_path = $pdfPath;
            $coverLetter->save();

            return $result;
        } catch (\Exception $e) {
            Log::error('Cover letter PDF generation exception', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'cover_letter_id' => $coverLetter->id,
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        } finally {
            if ($signatureTempPath !== null) {
                @unlink($signatureTempPath);
            }
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
