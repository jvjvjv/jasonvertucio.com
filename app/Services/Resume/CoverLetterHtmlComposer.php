<?php

namespace App\Services\Resume;

use App\Models\CoverLetter;

/**
 * Builds the HTML body of a cover letter, mirroring `CoverLetterBodyComposer`
 * so the PDF and DOCX reproduce the same structure and signature placement
 * from the same configuration.
 */
class CoverLetterHtmlComposer
{
    public function __construct(protected MarkdownToHtmlConverter $converter = new MarkdownToHtmlConverter) {}

    /**
     * Compose the letter body as HTML.
     *
     * @param  array{path: string, cx: int, cy: int}|null  $signatureImage  Already-written temp PNG path plus its OOXML-unit size, or null when the signature could not be prepared.
     */
    public function compose(CoverLetter $coverLetter, ?array $signatureImage = null): string
    {
        $html = '';

        $html .= $this->paragraph($coverLetter->date?->format('F j, Y') ?? '');
        $html .= $this->addressBlock((string) $coverLetter->company_address);
        $html .= $this->paragraph((string) $coverLetter->greeting);
        $html .= $this->converter->convert((string) $coverLetter->message_body);
        $html .= $this->signOff((string) $coverLetter->closing, (string) $coverLetter->signature, $signatureImage);

        return $html;
    }

    /**
     * The closing, the blank signing space and the typed name, wrapped in one
     * block so a page break cannot fall inside it.
     *
     * @param  array{path: string, cx: int, cy: int}|null  $signatureImage
     */
    protected function signOff(string $closing, string $name, ?array $signatureImage): string
    {
        $imageHtml = $signatureImage === null ? '' : $this->signatureImg($signatureImage);

        return '<div class="sign-off">'
            .'<p class="Normal">'.$this->escape($closing).'</p>'
            .'<div class="signing-space"></div>'
            .'<p class="Normal signed-name">'.$imageHtml.$this->escape($name).'</p>'
            .'</div>';
    }

    /**
     * Position the signature so it overlaps the closing and the typed name,
     * the same way the DOCX's floating anchor does — out of the text flow,
     * lifted above the name paragraph by the configured rise.
     *
     * @param  array{path: string, cx: int, cy: int}  $signatureImage
     */
    protected function signatureImg(array $signatureImage): string
    {
        $emuPerInch = SignatureImageService::EMU_PER_INCH;

        $rise = (int) config('resume.signature_rise') / $emuPerInch;
        $indent = (int) config('resume.signature_indent') / $emuPerInch;
        $width = $signatureImage['cx'] / $emuPerInch;
        $height = $signatureImage['cy'] / $emuPerInch;

        $style = 'position:absolute;'
            .'left:'.$this->trimNumber($indent).'in;'
            .'top:'.$this->trimNumber($rise).'in;'
            .'width:'.$this->trimNumber($width).'in;'
            .'height:'.$this->trimNumber($height).'in;';

        $src = 'file://'.$signatureImage['path'];

        return '<img class="signature" style="'.$style.'" src="'.htmlspecialchars($src, ENT_QUOTES | ENT_HTML5, 'UTF-8').'" alt="">';
    }

    protected function addressBlock(string $address): string
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($address)) ?: [];
        $lines = array_values(array_filter($lines, fn (string $line): bool => trim($line) !== ''));

        $html = '';
        foreach ($lines as $line) {
            $html .= $this->paragraph(trim($line));
        }

        return $html;
    }

    protected function paragraph(string $text): string
    {
        return '<p class="Normal">'.$this->escape($text).'</p>';
    }

    protected function escape(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    protected function trimNumber(float $number): string
    {
        return rtrim(rtrim(sprintf('%.4f', $number), '0'), '.');
    }
}
