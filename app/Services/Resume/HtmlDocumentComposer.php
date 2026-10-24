<?php

namespace App\Services\Resume;

use DOMDocument;
use DOMElement;
use DOMXPath;
use ZipArchive;

/**
 * Assembles the full HTML document a document's PDF is rendered from: the
 * translated template CSS, `@font-face` rules for the same static faces the
 * DOCX embeds, the letterhead read from the shared template's own body
 * paragraphs with placeholders substituted, and the converted body.
 */
class HtmlDocumentComposer
{
    protected const NAMESPACE_W = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    /**
     * Body paragraph style ids the template's letterhead carries, in the
     * order they appear, mapped to the tag the PDF renders them as.
     */
    protected const LETTERHEAD_STYLES = [
        'Title' => 'h1',
        'Header' => 'p',
    ];

    public function __construct(
        protected StylesheetTranslator $stylesheet = new StylesheetTranslator,
        protected FontEmbedder $fontEmbedder = new FontEmbedder,
        protected PlaceholderSubstitutor $placeholders = new PlaceholderSubstitutor,
    ) {}

    /**
     * Compose the full HTML document.
     *
     * @param  array<string, string>  $placeholderValues  Token name (without braces) => replacement value.
     */
    public function compose(string $bodyHtml, array $placeholderValues, string $extraCss = ''): string
    {
        $css = $this->fontFaceCss().$this->stylesheet->css().$this->layoutCss().$extraCss;
        $letterhead = $this->letterheadHtml((string) config('resume.template'), $placeholderValues);

        return '<!DOCTYPE html><html><head><meta charset="utf-8"><style>'.$css.'</style></head>'
            .'<body>'.$letterhead.$bodyHtml.'</body></html>';
    }

    /**
     * Read the template's own letterhead paragraphs, substitute placeholders
     * the same way the DOCX does, and render them as HTML.
     *
     * @param  array<string, string>  $placeholderValues
     */
    protected function letterheadHtml(string $templatePath, array $placeholderValues): string
    {
        if (! file_exists($templatePath)) {
            return '';
        }

        $zip = new ZipArchive;
        if ($zip->open($templatePath) !== true) {
            return '';
        }

        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        if ($xml === false) {
            return '';
        }

        $substituted = $this->placeholders->substitute($xml, $placeholderValues);

        $dom = $this->parseXml($substituted);
        if ($dom === null) {
            return '';
        }

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('w', self::NAMESPACE_W);

        $html = '';

        foreach ($xpath->query('//w:body/w:p') as $paragraph) {
            $styleNode = $xpath->query('w:pPr/w:pStyle', $paragraph)->item(0);
            $styleId = $styleNode instanceof DOMElement
                ? $styleNode->getAttributeNS(self::NAMESPACE_W, 'val')
                : null;

            if ($styleId === null || ! isset(self::LETTERHEAD_STYLES[$styleId])) {
                continue;
            }

            $text = '';
            foreach ($xpath->query('.//w:t', $paragraph) as $textNode) {
                $text .= $textNode->textContent;
            }

            $tag = self::LETTERHEAD_STYLES[$styleId];
            $html .= "<{$tag} class=\"{$styleId}\">"
                .htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8')
                ."</{$tag}>";
        }

        return $html;
    }

    /**
     * `@font-face` rules for the static faces `resume:embed-fonts` also
     * embeds into the DOCX, so the two formats cannot drift onto different
     * faces.
     */
    protected function fontFaceCss(): string
    {
        $directory = (string) config('resume.fonts');

        if (! is_dir($directory)) {
            return '';
        }

        $css = '';

        foreach ($this->fontEmbedder->loadFaces($directory) as $face) {
            if ($face['variable']) {
                continue;
            }

            $weight = str_contains($face['subfamily'], 'Bold') ? 'bold' : 'normal';
            $style = str_contains($face['subfamily'], 'Italic') ? 'italic' : 'normal';
            $path = realpath($face['path']) ?: $face['path'];

            $css .= '@font-face{font-family:"'.$face['family'].'";font-weight:'.$weight.';font-style:'.$style
                .';src:url("file://'.$path.'");}';
        }

        return $css;
    }

    /**
     * CSS for the synthetic wrapper classes `MarkdownToHtmlConverter` emits,
     * which have no counterpart in the template's own named styles.
     */
    protected function layoutCss(): string
    {
        return '.skills-columns{column-count:2;column-gap:0.25in;}'
            .'.skill-category{break-inside:avoid;}'
            .'ul.ListParagraph{margin:0;list-style-position:outside;}'
            // The sign-off — closing, signing space, typed name — must move
            // to the next page as one unit, never split across a break.
            .'.sign-off{break-inside:avoid;}'
            .'.signing-space{height:0.5in;}'
            // .signed-name is the positioning context the floating signature
            // is placed relative to, out of the text flow so it overlaps
            // rather than displaces the closing and the name beneath it.
            .'.signed-name{position:relative;}'
            .'.signature{position:absolute;}';
    }

    protected function parseXml(string $xml): ?DOMDocument
    {
        $dom = new DOMDocument;
        $dom->preserveWhiteSpace = true;

        $previous = libxml_use_internal_errors(true);
        $loaded = $dom->loadXML($xml);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $loaded ? $dom : null;
    }
}
