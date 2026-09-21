<?php

namespace App\Services;

use App\Models\TargetedResume;
use App\Services\Resume\DocumentRenderer;
use App\Services\Resume\HtmlDocumentComposer;
use App\Services\Resume\MarkdownToHtmlConverter;
use App\Services\Resume\MarkdownToOpenXmlConverter;
use App\Services\Resume\PdfRenderer;
use Illuminate\Support\Facades\Log;

class TargetedResumeDocumentService
{
    protected string $outputDir;

    public function __construct(
        protected MarkdownToOpenXmlConverter $converter,
        protected DocumentRenderer $renderer,
        protected MarkdownToHtmlConverter $htmlConverter,
        protected HtmlDocumentComposer $htmlComposer,
        protected PdfRenderer $pdfRenderer,
    ) {
        $this->outputDir = storage_path('app/targeted-resumes');
    }

    /**
     * Generate a DOCX file for the given targeted resume.
     *
     * @return array{success: bool, path?: string, size?: int, error?: string}
     */
    public function generateDocx(TargetedResume $targetedResume): array
    {
        $filename = $targetedResume->generateFilename();
        $outputPath = $this->outputDir.'/'.$filename.'.docx';

        try {
            $data = $this->buildTemplateData($targetedResume);
            $resumeMarkdown = $data['resume'];
            unset($data['resume']);

            $result = $this->renderer->render(
                config('resume.template'),
                $outputPath,
                $data,
                $this->converter->convert($resumeMarkdown),
            );

            if (! $result['success']) {
                Log::error('Targeted resume DOCX generation failed', [
                    'error' => $result['error'],
                    'targeted_resume_id' => $targetedResume->id,
                ]);

                return $result;
            }

            $targetedResume->docx_path = $outputPath;
            $targetedResume->save();

            return $result;
        } catch (\Exception $e) {
            Log::error('Targeted resume DOCX generation failed', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'targeted_resume_id' => $targetedResume->id,
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
     * Generate a PDF for the given targeted resume, rendered from its stored
     * `tailored_data` rather than by converting the DOCX.
     *
     * @return array{success: bool, path?: string, error?: string}
     */
    public function generatePdf(TargetedResume $targetedResume): array
    {
        $filename = $targetedResume->generateFilename();
        $pdfPath = $this->outputDir.'/'.$filename.'.pdf';

        try {
            $data = $this->buildTemplateData($targetedResume);
            $resumeMarkdown = $data['resume'];
            unset($data['resume']);

            $bodyHtml = $this->htmlConverter->convert($resumeMarkdown);
            $html = $this->htmlComposer->compose($bodyHtml, $data);

            $result = $this->pdfRenderer->render($html, $pdfPath);

            if (! $result['success']) {
                Log::error('Targeted resume PDF generation failed', $result + [
                    'targeted_resume_id' => $targetedResume->id,
                ]);

                return $result;
            }

            $targetedResume->pdf_path = $pdfPath;
            $targetedResume->save();

            return $result;
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
     * @return array{name: string, title: string, email: string, phone: string, url: string, resume: string}
     */
    protected function buildTemplateData(TargetedResume $targetedResume): array
    {
        $targetedResume->loadMissing('resumeVersion.personalInfo');

        $personalInfo = $targetedResume->resumeVersion?->personalInfo;
        $resumeContent = (string) data_get($targetedResume->tailored_data, 'content', '');

        if ($resumeContent === '') {
            $resumeContent = (string) data_get($targetedResume->tailored_data, 'markdown', '');
        }

        return [
            'name' => $personalInfo?->name ?? '',
            'title' => $targetedResume->title ?? $personalInfo?->title ?? '',
            'email' => $personalInfo?->email ?? '',
            'phone' => $personalInfo?->phone ?? '',
            'url' => $this->formatDisplayUrl($personalInfo?->url),
            'resume' => $resumeContent,
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
