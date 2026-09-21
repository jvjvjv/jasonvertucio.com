<?php

namespace App\Services\Resume;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use ZipArchive;

/**
 * Translates the shared DOCX template's `styles.xml` and body `sectPr` into
 * CSS, so the PDF renders from the same design the DOCX does with no second
 * stylesheet to keep in sync.
 *
 * Only the styles the template's own body and the generated document bodies
 * actually use are translated (`RECOGNIZED_STYLES`); everything else the
 * template defines is either on the explicit `KNOWN_IGNORABLE_STYLES` list or
 * logged as unrecognized, never failing the render.
 */
class StylesheetTranslator
{
    protected const NAMESPACE_W = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    /**
     * Style ids this translator emits CSS for, keyed to how the OOXML emitter
     * and the HTML emitter both use them.
     */
    public const RECOGNIZED_STYLES = [
        'Normal', 'Title', 'Header', 'Heading1', 'Heading2', 'Heading3',
        'JobTitle', 'CompanyInfo', 'ListParagraph', 'KeyTechnologies',
    ];

    /**
     * Style ids the template defines but no generated document ever renders
     * with — Word's own built-ins, table/list scaffolding, and character
     * styles whose paragraph counterpart is already covered.
     */
    public const KNOWN_IGNORABLE_STYLES = [
        'Heading4', 'Heading5', 'Heading6', 'DefaultParagraphFont', 'TableNormal',
        'NoList', 'Strong1', 'Hyperlink', 'FootnoteReference', 'FootnoteText',
        'FootnoteTextChar', 'UnresolvedMention', 'Heading2Char', 'HeaderChar',
        'Footer', 'FooterChar', 'FollowedHyperlink', 'HTMLPreformatted',
        'HTMLPreformattedChar', 'LineNumber', 'TitleChar',
    ];

    protected const TWENTIETHS_PER_POINT = 20;

    protected const HALF_POINTS_PER_POINT = 2;

    protected const EIGHTHS_PER_POINT = 8;

    protected const TWIPS_PER_INCH = 1440;

    /**
     * Return the CSS translated from `config('resume.template')`, cached
     * against the template's path and mtime so a Word save invalidates it and
     * nothing else has to.
     */
    public function css(): string
    {
        $path = (string) config('resume.template');

        if (! file_exists($path)) {
            return '';
        }

        $mtime = filemtime($path) ?: 0;
        $cacheKey = 'resume.stylesheet-translator.'.md5($path);
        $cached = Cache::get($cacheKey);

        if (is_array($cached) && ($cached['mtime'] ?? null) === $mtime) {
            return $cached['css'];
        }

        $css = $this->translate($path);

        Cache::forever($cacheKey, ['mtime' => $mtime, 'css' => $css]);

        return $css;
    }

    /**
     * The style ids the given template's `styles.xml` defines, for verifying
     * every one is accounted for by `RECOGNIZED_STYLES` or
     * `KNOWN_IGNORABLE_STYLES`.
     *
     * @return array<int, string>
     */
    public function styleIdsIn(string $templatePath): array
    {
        $zip = new ZipArchive;
        if ($zip->open($templatePath) !== true) {
            return [];
        }

        $stylesXml = $zip->getFromName('word/styles.xml');
        $zip->close();

        if ($stylesXml === false) {
            return [];
        }

        $dom = $this->parseXml($stylesXml);
        if ($dom === null) {
            return [];
        }

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('w', self::NAMESPACE_W);

        $ids = [];
        foreach ($xpath->query('//w:style[@w:styleId]') as $style) {
            /** @var DOMElement $style */
            $ids[] = $style->getAttributeNS(self::NAMESPACE_W, 'styleId');
        }

        return $ids;
    }

    protected function translate(string $path): string
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            return '';
        }

        $stylesXml = $zip->getFromName('word/styles.xml');
        $documentXml = $zip->getFromName('word/document.xml');
        $zip->close();

        if ($stylesXml === false) {
            return '';
        }

        $dom = $this->parseXml($stylesXml);
        if ($dom === null) {
            return '';
        }

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('w', self::NAMESPACE_W);

        $docDefaults = $this->parseRPr($xpath->query('//w:docDefaults/w:rPrDefault/w:rPr')->item(0));
        $styles = $this->parseStyles($xpath);

        $this->logUnrecognizedStyles(array_keys($styles));

        $css = $this->docDefaultsCss($docDefaults);

        if ($documentXml !== false) {
            $documentDom = $this->parseXml($documentXml);
            if ($documentDom !== null) {
                $documentXpath = new DOMXPath($documentDom);
                $documentXpath->registerNamespace('w', self::NAMESPACE_W);
                $css .= $this->pageCss($documentXpath);
            }
        }

        foreach (self::RECOGNIZED_STYLES as $styleId) {
            if (! isset($styles[$styleId])) {
                continue;
            }

            $css .= $this->styleCss($styleId, $this->resolve($styleId, $styles, $docDefaults));
        }

        return $css;
    }

    /**
     * @param  array<string, array{basedOn: ?string, pPr: array<string, mixed>, rPr: array<string, mixed>}>  $styles
     */
    protected function logUnrecognizedStyles(array $styleIds): void
    {
        foreach ($styleIds as $styleId) {
            if (in_array($styleId, self::RECOGNIZED_STYLES, true) || in_array($styleId, self::KNOWN_IGNORABLE_STYLES, true)) {
                continue;
            }

            Log::warning('Template style is neither translated nor known-ignorable; it will render unstyled in the PDF.', [
                'styleId' => $styleId,
            ]);
        }
    }

    /**
     * Resolve a style's effective properties by merging docDefaults, then
     * each ancestor in its `basedOn` chain from furthest to nearest, then the
     * style itself — a style with no `basedOn` inherits directly from
     * docDefaults, per the OOXML default.
     *
     * @param  array<string, array{basedOn: ?string, pPr: array<string, mixed>, rPr: array<string, mixed>}>  $styles
     * @param  array<string, mixed>  $docDefaults
     * @return array<string, mixed>
     */
    protected function resolve(string $styleId, array $styles, array $docDefaults): array
    {
        $chain = [];
        $current = $styleId;
        $visited = [];

        while ($current !== null && isset($styles[$current]) && ! isset($visited[$current])) {
            array_unshift($chain, $current);
            $visited[$current] = true;
            $current = $styles[$current]['basedOn'];
        }

        $resolved = $docDefaults;

        foreach ($chain as $ancestorId) {
            $resolved = array_merge($resolved, $styles[$ancestorId]['pPr'], $styles[$ancestorId]['rPr']);
        }

        return $resolved;
    }

    /**
     * @param  DOMXPath  $xpath
     * @return array<string, array{basedOn: ?string, pPr: array<string, mixed>, rPr: array<string, mixed>}>
     */
    protected function parseStyles(DOMXPath $xpath): array
    {
        $styles = [];

        foreach ($xpath->query('//w:style[@w:styleId]') as $style) {
            /** @var DOMElement $style */
            $styleId = $style->getAttributeNS(self::NAMESPACE_W, 'styleId');

            $basedOnNode = $xpath->query('w:basedOn', $style)->item(0);
            $basedOn = $basedOnNode instanceof DOMElement
                ? $basedOnNode->getAttributeNS(self::NAMESPACE_W, 'val')
                : null;

            $styles[$styleId] = [
                'basedOn' => $basedOn === '' ? null : $basedOn,
                'pPr' => $this->parsePPr($xpath->query('w:pPr', $style)->item(0)),
                'rPr' => $this->parseRPr($xpath->query('w:rPr', $style)->item(0)),
            ];
        }

        return $styles;
    }

    /**
     * @return array<string, mixed>
     */
    protected function parsePPr(?\DOMNode $pPr): array
    {
        if (! $pPr instanceof DOMElement) {
            return [];
        }

        $props = [];

        $spacing = $this->firstElement($pPr, 'spacing');
        if ($spacing !== null) {
            if ($spacing->hasAttributeNS(self::NAMESPACE_W, 'before')) {
                $props['spacingBefore'] = (int) $spacing->getAttributeNS(self::NAMESPACE_W, 'before');
            }
            if ($spacing->hasAttributeNS(self::NAMESPACE_W, 'after')) {
                $props['spacingAfter'] = (int) $spacing->getAttributeNS(self::NAMESPACE_W, 'after');
            }
        }

        $ind = $this->firstElement($pPr, 'ind');
        if ($ind !== null && $ind->hasAttributeNS(self::NAMESPACE_W, 'left')) {
            $props['indLeft'] = (int) $ind->getAttributeNS(self::NAMESPACE_W, 'left');
        }

        if ($this->firstElement($pPr, 'keepNext') !== null) {
            $props['keepNext'] = true;
        }

        $jc = $this->firstElement($pPr, 'jc');
        if ($jc !== null) {
            $props['jc'] = $jc->getAttributeNS(self::NAMESPACE_W, 'val');
        }

        $pBdr = $this->firstElement($pPr, 'pBdr');
        if ($pBdr !== null) {
            $bottom = $this->firstElement($pBdr, 'bottom');
            if ($bottom !== null && $bottom->getAttributeNS(self::NAMESPACE_W, 'val') !== 'none') {
                $props['pBdrBottom'] = [
                    'sz' => (int) $bottom->getAttributeNS(self::NAMESPACE_W, 'sz'),
                    'color' => $bottom->getAttributeNS(self::NAMESPACE_W, 'color'),
                ];
            } elseif ($bottom !== null) {
                $props['pBdrBottom'] = null;
            }
        }

        return $props;
    }

    /**
     * @return array<string, mixed>
     */
    protected function parseRPr(?\DOMNode $rPr): array
    {
        if (! $rPr instanceof DOMElement) {
            return [];
        }

        $props = [];

        $rFonts = $this->firstElement($rPr, 'rFonts');
        if ($rFonts !== null && $rFonts->hasAttributeNS(self::NAMESPACE_W, 'ascii')) {
            // The template can carry a per-weight pseudo-family directly in
            // rFonts (e.g. "Josefin Sans Bold" on Title) — fold it back onto
            // the real family so the CSS names a family @font-face actually
            // declares, the same way FontEmbedder does for the DOCX.
            [$family] = FontEmbedder::splitPerWeightFamily($rFonts->getAttributeNS(self::NAMESPACE_W, 'ascii'));
            $props['fontFamily'] = $family;
        }

        $sz = $this->firstElement($rPr, 'sz');
        if ($sz !== null) {
            $props['sizeHalfPoints'] = (int) $sz->getAttributeNS(self::NAMESPACE_W, 'val');
        }

        $b = $this->firstElement($rPr, 'b');
        if ($b !== null) {
            $props['bold'] = $this->booleanAttr($b);
        }

        $i = $this->firstElement($rPr, 'i');
        if ($i !== null) {
            $props['italic'] = $this->booleanAttr($i);
        }

        $caps = $this->firstElement($rPr, 'caps');
        if ($caps !== null) {
            $props['caps'] = $this->booleanAttr($caps);
        }

        $color = $this->firstElement($rPr, 'color');
        if ($color !== null) {
            $val = $color->getAttributeNS(self::NAMESPACE_W, 'val');
            if ($val !== '' && strtolower($val) !== 'auto') {
                $props['color'] = $val;
            }
        }

        return $props;
    }

    /**
     * An OOXML boolean toggle element: present with no `w:val` means true; an
     * explicit `w:val="0"` or `w:val="false"` means false.
     */
    protected function booleanAttr(DOMElement $element): bool
    {
        if (! $element->hasAttributeNS(self::NAMESPACE_W, 'val')) {
            return true;
        }

        return ! in_array($element->getAttributeNS(self::NAMESPACE_W, 'val'), ['0', 'false'], true);
    }

    protected function firstElement(DOMElement $parent, string $localName): ?DOMElement
    {
        foreach ($parent->childNodes as $child) {
            if ($child instanceof DOMElement
                && $child->namespaceURI === self::NAMESPACE_W
                && $child->localName === $localName) {
                return $child;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $docDefaults
     */
    protected function docDefaultsCss(array $docDefaults): string
    {
        $decls = [];

        if (isset($docDefaults['fontFamily'])) {
            $decls[] = 'font-family:"'.$docDefaults['fontFamily'].'"';
        }

        if (isset($docDefaults['sizeHalfPoints'])) {
            $decls[] = 'font-size:'.$this->halfPointsToPt($docDefaults['sizeHalfPoints']);
        }

        if ($decls === []) {
            return '';
        }

        return 'body{'.implode(';', $decls).';}';
    }

    protected function pageCss(DOMXPath $documentXpath): string
    {
        $pgSz = $documentXpath->query('//w:body/w:sectPr/w:pgSz')->item(0);
        $pgMar = $documentXpath->query('//w:body/w:sectPr/w:pgMar')->item(0);

        if (! $pgSz instanceof DOMElement || ! $pgMar instanceof DOMElement) {
            return '';
        }

        $width = $this->twipsToIn((int) $pgSz->getAttributeNS(self::NAMESPACE_W, 'w'));
        $height = $this->twipsToIn((int) $pgSz->getAttributeNS(self::NAMESPACE_W, 'h'));

        $top = $this->twipsToIn((int) $pgMar->getAttributeNS(self::NAMESPACE_W, 'top'));
        $right = $this->twipsToIn((int) $pgMar->getAttributeNS(self::NAMESPACE_W, 'right'));
        $bottom = $this->twipsToIn((int) $pgMar->getAttributeNS(self::NAMESPACE_W, 'bottom'));
        $left = $this->twipsToIn((int) $pgMar->getAttributeNS(self::NAMESPACE_W, 'left'));

        return '@page{size:'.$width.' '.$height.';margin:'.$top.' '.$right.' '.$bottom.' '.$left.';}';
    }

    /**
     * @param  array<string, mixed>  $props
     */
    protected function styleCss(string $styleId, array $props): string
    {
        $decls = [];

        if (isset($props['fontFamily'])) {
            $decls[] = 'font-family:"'.$props['fontFamily'].'"';
        }

        if (isset($props['sizeHalfPoints'])) {
            $decls[] = 'font-size:'.$this->halfPointsToPt($props['sizeHalfPoints']);
        }

        if (array_key_exists('bold', $props)) {
            $decls[] = 'font-weight:'.($props['bold'] ? 'bold' : 'normal');
        }

        if (array_key_exists('italic', $props)) {
            $decls[] = 'font-style:'.($props['italic'] ? 'italic' : 'normal');
        }

        if (isset($props['color'])) {
            $decls[] = 'color:#'.$props['color'];
        }

        if (array_key_exists('caps', $props)) {
            $decls[] = 'text-transform:'.($props['caps'] ? 'uppercase' : 'none');
        }

        if (isset($props['spacingBefore'])) {
            $decls[] = 'margin-top:'.$this->twentiethsToPt($props['spacingBefore']);
        }

        if (isset($props['spacingAfter'])) {
            $decls[] = 'margin-bottom:'.$this->twentiethsToPt($props['spacingAfter']);
        }

        if (isset($props['indLeft'])) {
            $decls[] = 'margin-left:'.$this->twentiethsToPt($props['indLeft']);
        }

        if (! empty($props['keepNext'])) {
            $decls[] = 'break-after:avoid';
        }

        if (isset($props['jc'])) {
            $decls[] = 'text-align:'.$props['jc'];
        }

        if (! empty($props['pBdrBottom'])) {
            $decls[] = 'border-bottom:'.$this->eighthsToPt($props['pBdrBottom']['sz']).' solid #'.$props['pBdrBottom']['color'];
        }

        if ($decls === []) {
            return '';
        }

        return '.'.$styleId.'{'.implode(';', $decls).';}';
    }

    protected function halfPointsToPt(int $halfPoints): string
    {
        return $this->trimNumber($halfPoints / self::HALF_POINTS_PER_POINT).'pt';
    }

    protected function twentiethsToPt(int $twentieths): string
    {
        return $this->trimNumber($twentieths / self::TWENTIETHS_PER_POINT).'pt';
    }

    protected function eighthsToPt(int $eighths): string
    {
        return $this->trimNumber($eighths / self::EIGHTHS_PER_POINT).'pt';
    }

    protected function twipsToIn(int $twips): string
    {
        return $this->trimNumber(round($twips / self::TWIPS_PER_INCH, 2)).'in';
    }

    protected function trimNumber(float $number): string
    {
        return rtrim(rtrim(sprintf('%.4f', $number), '0'), '.');
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
