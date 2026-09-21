<?php

namespace App\Services\Resume;

use App\Models\CoverLetter;

/**
 * Builds the OOXML body of a cover letter.
 *
 * The shared template carries no body, so the letter's structure — date,
 * company address, greeting, message, closing, signature — is generated here
 * rather than bound to placeholders in a letter-specific template.
 */
class CoverLetterBodyComposer
{
    protected const NAMESPACE_W = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    /**
     * Twentieths of a point of space after a block. 240 is 12pt.
     */
    protected const BLOCK_SPACING = 240;

    /**
     * Space after each message-body paragraph.
     *
     * The shared template's Normal style is tuned for a dense resume and sets
     * no paragraph spacing, so a letter rendered from it would run together
     * into one block. 160 is the pPrDefault the letter-specific template used
     * to supply.
     */
    protected const BODY_PARAGRAPH_SPACING = 160;

    /**
     * How far the signature is lifted above the typed-name paragraph, in EMU.
     *
     * The signature is anchored to the name and positioned above it, so it
     * falls into the blank space left between the closing and the name — where
     * a pen would put it — rather than being given a slot of its own in the
     * text flow. Solved from a hand-placed reference so the signature's first
     * ink lands 0.041in above the closing's centre; the value accounts for the
     * 0.149in of empty margin at the top of the source PNG.
     */
    protected const SIGNATURE_RISE = -395080;

    /**
     * Height of the blank line between the closing and the typed name, in
     * twentieths of a point. 186 is 0.129in, which puts the name 0.326in below
     * the closing — matching the reference. Pinned with lineRule="exact" so it
     * does not drift with the template's line spacing.
     */
    protected const SIGNATURE_GAP = 186;

    /**
     * Horizontal offset from the left margin, in EMU. A signature sits a
     * little in from the text rather than flush against it.
     */
    protected const SIGNATURE_INDENT = 91440;

    public function __construct(
        protected MarkdownToOpenXmlConverter $converter,
        protected InlineImageBuilder $imageBuilder,
    ) {}

    /**
     * Compose the letter body.
     *
     * @param  array{relationshipId: string, cx: int, cy: int}|null  $signatureImage  Omitted when the signature could not be prepared.
     */
    public function compose(CoverLetter $coverLetter, ?array $signatureImage = null): string
    {
        $xml = '';

        $xml .= $this->paragraph($coverLetter->date?->format('F j, Y') ?? '', self::BLOCK_SPACING);

        $xml .= $this->addressBlock((string) $coverLetter->company_address);

        $xml .= $this->paragraph((string) $coverLetter->greeting, self::BLOCK_SPACING);

        $xml .= $this->prepareBodyParagraphs(
            $this->converter->convert((string) $coverLetter->message_body)
        );

        // The sign-off is laid out the way it would be typed: a blank line, the
        // closing, two blank lines that leave room to sign, then the name. The
        // signature is anchored to the name paragraph and lifted above it, so
        // it lands in that space the way a pen would. keepNext binds each
        // paragraph to the next, so a page break can only fall before the
        // block, never inside it.
        $xml .= $this->paragraph('', 0, keepNext: true);
        $xml .= $this->paragraph((string) $coverLetter->closing, 0, keepNext: true);
        $xml .= $this->signingSpace();

        $xml .= $this->signedName((string) $coverLetter->signature, $signatureImage);

        return $xml;
    }

    /**
     * Space the converted message body, and bind its last paragraph to the
     * sign-off.
     *
     * Spacing: the converter is shared with the resumes, where paragraphs are
     * deliberately tight, so the letter adds its own breathing room here
     * rather than changing the converter for everyone. List items keep their
     * tight spacing so a list still reads as one block.
     *
     * Binding: when the sign-off does not fit in what is left of the page it
     * has to move, and the only question is what moves with it. Binding the
     * final body paragraph means the last page opens with a paragraph of prose
     * rather than a signature sitting alone at the top.
     */
    protected function prepareBodyParagraphs(string $fragment): string
    {
        if (trim($fragment) === '') {
            return '';
        }

        $dom = new \DOMDocument;

        $previous = libxml_use_internal_errors(true);
        $loaded = $dom->loadXML(
            '<w:body xmlns:w="'.self::NAMESPACE_W.'">'.$fragment.'</w:body>'
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded) {
            return $fragment;
        }

        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('w', self::NAMESPACE_W);

        $paragraphs = $xpath->query('/w:body/w:p');
        $lastIndex = $paragraphs->length - 1;

        foreach ($paragraphs as $index => $paragraph) {
            $pPr = $xpath->query('w:pPr', $paragraph)->item(0);

            if ($pPr === null) {
                $pPr = $dom->createElementNS(self::NAMESPACE_W, 'w:pPr');
                $paragraph->insertBefore($pPr, $paragraph->firstChild);
            }

            // A column break paragraph carries its own sectPr; leave it alone.
            if ($xpath->query('w:sectPr', $pPr)->length > 0) {
                continue;
            }

            if ($index === $lastIndex) {
                $this->bindToNext($dom, $xpath, $pPr);
            }

            if ($xpath->query('w:numPr', $pPr)->length > 0) {
                continue;
            }

            if ($xpath->query('w:spacing', $pPr)->length > 0) {
                continue;
            }

            $spacing = $dom->createElementNS(self::NAMESPACE_W, 'w:spacing');
            $spacing->setAttributeNS(self::NAMESPACE_W, 'w:after', (string) self::BODY_PARAGRAPH_SPACING);
            $pPr->appendChild($spacing);
        }

        $spaced = '';
        foreach ($dom->documentElement->childNodes as $child) {
            $spaced .= $dom->saveXML($child);
        }

        return $spaced;
    }

    /**
     * The blank line between the closing and the typed name that the
     * signature is written across.
     */
    protected function signingSpace(): string
    {
        return '<w:p xmlns:w="'.self::NAMESPACE_W.'">'
            .'<w:pPr><w:pStyle w:val="Normal"/><w:keepNext/>'
            .'<w:spacing w:line="'.self::SIGNATURE_GAP.'" w:lineRule="exact" w:after="0"/>'
            .'</w:pPr></w:p>';
    }

    /**
     * The typed name, with the signature anchored to it.
     *
     * Anchoring to the name rather than to a spacer paragraph is what makes
     * the placement stable: the signature travels with the name wherever the
     * name ends up, and the blank lines above it are ordinary empty
     * paragraphs, so the sign-off costs the page five short lines instead of
     * the signature's full two inches.
     *
     * @param  array{relationshipId: string, cx: int, cy: int}|null  $signatureImage
     */
    protected function signedName(string $name, ?array $signatureImage): string
    {
        $run = '';

        if ($signatureImage !== null) {
            $run = $this->imageBuilder->floatingRun(
                $signatureImage['relationshipId'],
                $signatureImage['cx'],
                $signatureImage['cy'],
                self::SIGNATURE_RISE,
                self::SIGNATURE_INDENT,
                'Signature',
            );
        }

        return '<w:p xmlns:w="'.self::NAMESPACE_W.'">'
            .'<w:pPr><w:pStyle w:val="Normal"/></w:pPr>'
            .$run
            .$this->runs($name)
            .'</w:p>';
    }

    /**
     * Add w:keepNext to a paragraph's properties.
     *
     * OOXML fixes the order of pPr's children: keepNext sits directly after
     * pStyle and before numPr and spacing, so it is inserted rather than
     * appended.
     */
    protected function bindToNext(\DOMDocument $dom, \DOMXPath $xpath, \DOMNode $pPr): void
    {
        if ($xpath->query('w:keepNext', $pPr)->length > 0) {
            return;
        }

        $keepNext = $dom->createElementNS(self::NAMESPACE_W, 'w:keepNext');
        $pStyle = $xpath->query('w:pStyle', $pPr)->item(0);

        if ($pStyle !== null && $pStyle->nextSibling !== null) {
            $pPr->insertBefore($keepNext, $pStyle->nextSibling);

            return;
        }

        if ($pStyle !== null) {
            $pPr->appendChild($keepNext);

            return;
        }

        $pPr->insertBefore($keepNext, $pPr->firstChild);
    }

    /**
     * Render the company address as one paragraph per line, tight together,
     * with block spacing after the last line.
     */
    protected function addressBlock(string $address): string
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($address)) ?: [];
        $lines = array_values(array_filter($lines, fn (string $line): bool => trim($line) !== ''));

        if ($lines === []) {
            return '';
        }

        $xml = '';
        $lastIndex = count($lines) - 1;

        foreach ($lines as $index => $line) {
            $xml .= $this->paragraph(trim($line), $index === $lastIndex ? self::BLOCK_SPACING : 0);
        }

        return $xml;
    }

    /**
     * Build a Normal-styled paragraph with optional trailing space.
     *
     * $keepNext binds the paragraph to the following one so a page break
     * cannot fall between them.
     */
    protected function paragraph(string $text, int $spaceAfter = 0, bool $keepNext = false): string
    {
        $properties = '<w:pStyle w:val="Normal"/>';

        if ($keepNext) {
            $properties .= '<w:keepNext/>';
        }

        if ($spaceAfter > 0) {
            $properties .= '<w:spacing w:after="'.$spaceAfter.'"/>';
        }

        return '<w:p xmlns:w="'.self::NAMESPACE_W.'">'
            .'<w:pPr>'.$properties.'</w:pPr>'
            .$this->runs($text)
            .'</w:p>';
    }

    /**
     * Build the runs for a line, honouring **bold** the same way the Markdown
     * converter does so the letter reads consistently.
     */
    protected function runs(string $text): string
    {
        if ($text === '') {
            return '';
        }

        $parts = preg_split('/(\*\*[^*]+\*\*)/', $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

        if ($parts === false || $parts === []) {
            return '';
        }

        $xml = '';

        foreach ($parts as $part) {
            if (preg_match('/^\*\*(.+)\*\*$/', $part, $matches)) {
                $xml .= '<w:r><w:rPr><w:b/></w:rPr><w:t xml:space="preserve">'
                    .$this->escape($matches[1])
                    .'</w:t></w:r>';

                continue;
            }

            $xml .= '<w:r><w:t xml:space="preserve">'.$this->escape($part).'</w:t></w:r>';
        }

        return $xml;
    }

    protected function escape(string $text): string
    {
        return htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
