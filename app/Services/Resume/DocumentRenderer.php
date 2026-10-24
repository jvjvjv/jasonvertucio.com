<?php

namespace App\Services\Resume;

use Closure;
use DOMDocument;
use DOMXPath;
use ZipArchive;

/**
 * Renders a DOCX from the shared document template.
 *
 * The shared template carries only header placeholders and no body, so every
 * document type supplies its own OOXML body fragment. This class owns the
 * mechanics common to all of them: placeholder substitution (including
 * placeholders Word has split across text runs), body insertion ahead of the
 * section properties, and embedding image media with collision-free
 * relationship ids.
 */
class DocumentRenderer
{
    protected const NAMESPACE_W = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    protected const RELATIONSHIP_IMAGE = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/image';

    public function __construct(protected PlaceholderSubstitutor $placeholders = new PlaceholderSubstitutor) {}

    /**
     * Render a document from the template.
     *
     * When media is supplied, $body may be a closure receiving the map of
     * media name => relationship id, so the body can reference an embedded
     * image whose relationship id is only known once the template's existing
     * ids have been scanned.
     *
     * @param  array<string, string>  $placeholders  Token name (without braces) => replacement value.
     * @param  Closure(array<string, string>): string|string  $body  OOXML body fragment, or a builder receiving media relationship ids.
     * @param  array<string, array{bytes: string, extension: string, contentType: string}>  $media  Media file name => file payload.
     * @return array{success: bool, path?: string, size?: int, error?: string}
     */
    public function render(
        string $templatePath,
        string $outputPath,
        array $placeholders,
        Closure|string $body = '',
        array $media = [],
    ): array {
        if (! file_exists($templatePath)) {
            return $this->failure("Template file not found: {$templatePath}");
        }

        $outputDir = dirname($outputPath);
        if (! is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        if (! copy($templatePath, $outputPath)) {
            return $this->failure("Failed to copy template to output path: {$outputPath}");
        }

        $zip = new ZipArchive;
        if ($zip->open($outputPath) !== true) {
            return $this->failure("Template is not a readable Word document: {$templatePath}", $outputPath);
        }

        $xml = $zip->getFromName('word/document.xml');
        if ($xml === false) {
            $zip->close();

            return $this->failure("Template is missing word/document.xml: {$templatePath}", $outputPath);
        }

        $dom = $this->parseXml($xml);
        if ($dom === null) {
            $zip->close();

            return $this->failure("Template word/document.xml could not be parsed: {$templatePath}", $outputPath);
        }

        $mediaRelationshipIds = $media === [] ? [] : $this->embedMedia($zip, $media);

        $bodyXml = $body instanceof Closure ? $body($mediaRelationshipIds) : $body;

        $xml = $this->placeholders->substitute($xml, $placeholders);
        $xml = $this->insertBody($xml, $bodyXml);

        $zip->addFromString('word/document.xml', $xml);
        $zip->close();

        return [
            'success' => true,
            'path' => $outputPath,
            'size' => filesize($outputPath),
        ];
    }

    /**
     * Insert a body fragment ahead of the section properties, so the
     * template's page setup is preserved.
     */
    protected function insertBody(string $xml, string $bodyFragment): string
    {
        if (trim($bodyFragment) === '') {
            return $xml;
        }

        $dom = $this->parseXml($xml);
        if ($dom === null) {
            return $xml;
        }

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('w', self::NAMESPACE_W);

        $body = $xpath->query('//w:body')->item(0);
        if ($body === null) {
            return $xml;
        }

        $insertBefore = $xpath->query('//w:body/w:sectPr')->item(0);

        $wrapperXml = '<?xml version="1.0" encoding="UTF-8"?>'
            .'<w:body xmlns:w="'.self::NAMESPACE_W.'"'
            .' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"'
            .' xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing"'
            .' xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"'
            .' xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture">'
            .$bodyFragment
            .'</w:body>';

        $fragmentDom = $this->parseXml($wrapperXml);
        if ($fragmentDom === null) {
            return $xml;
        }

        $paragraphs = $fragmentDom->getElementsByTagNameNS(self::NAMESPACE_W, 'p');

        /** @var array<int, \DOMNode> $imported */
        $imported = [];
        foreach ($paragraphs as $paragraph) {
            $imported[] = $dom->importNode($paragraph, true);
        }

        foreach ($imported as $node) {
            if ($insertBefore) {
                $body->insertBefore($node, $insertBefore);
            } else {
                $body->appendChild($node);
            }
        }

        return $dom->saveXML() ?: $xml;
    }

    /**
     * Add image parts to the package and return their relationship ids.
     *
     * @param  array<string, array{bytes: string, extension: string, contentType: string}>  $media
     * @return array<string, string> Media name => relationship id.
     */
    protected function embedMedia(ZipArchive $zip, array $media): array
    {
        $relsPath = 'word/_rels/document.xml.rels';
        $relsXml = $zip->getFromName($relsPath);

        if ($relsXml === false) {
            return [];
        }

        $nextRelNumber = $this->nextRelationshipNumber($relsXml);
        $relationshipIds = [];
        $newRelationships = '';

        foreach ($media as $name => $payload) {
            $relationshipId = 'rId'.$nextRelNumber;
            $nextRelNumber++;

            $zip->addFromString('word/media/'.$name, $payload['bytes']);

            $newRelationships .= sprintf(
                '<Relationship Id="%s" Type="%s" Target="media/%s"/>',
                $relationshipId,
                self::RELATIONSHIP_IMAGE,
                $name
            );

            $relationshipIds[$name] = $relationshipId;
        }

        $zip->addFromString(
            $relsPath,
            str_replace('</Relationships>', $newRelationships.'</Relationships>', $relsXml)
        );

        $this->ensureContentTypeDefaults($zip, $media);

        return $relationshipIds;
    }

    /**
     * Find the first relationship number not already used in the package.
     */
    protected function nextRelationshipNumber(string $relsXml): int
    {
        preg_match_all('/Id="rId(\d+)"/', $relsXml, $matches);

        $highest = 0;
        foreach ($matches[1] as $number) {
            $highest = max($highest, (int) $number);
        }

        return $highest + 1;
    }

    /**
     * Declare a Default content type for each media extension not already declared.
     *
     * @param  array<string, array{bytes: string, extension: string, contentType: string}>  $media
     */
    protected function ensureContentTypeDefaults(ZipArchive $zip, array $media): void
    {
        $contentTypesPath = '[Content_Types].xml';
        $contentTypesXml = $zip->getFromName($contentTypesPath);

        if ($contentTypesXml === false) {
            return;
        }

        $additions = '';
        $declared = [];

        foreach ($media as $payload) {
            $extension = strtolower($payload['extension']);

            if (isset($declared[$extension])) {
                continue;
            }

            $declared[$extension] = true;

            if (stripos($contentTypesXml, 'Extension="'.$extension.'"') !== false) {
                continue;
            }

            $additions .= sprintf(
                '<Default Extension="%s" ContentType="%s"/>',
                $extension,
                $payload['contentType']
            );
        }

        if ($additions === '') {
            return;
        }

        $zip->addFromString(
            $contentTypesPath,
            preg_replace('/(<Types[^>]*>)/', '$1'.$additions, $contentTypesXml, 1)
        );
    }

    /**
     * Parse XML without letting libxml warnings escape to the error handler.
     */
    protected function parseXml(string $xml): ?DOMDocument
    {
        $dom = new DOMDocument;
        $dom->preserveWhiteSpace = true;
        $dom->formatOutput = false;

        $previous = libxml_use_internal_errors(true);
        $loaded = $dom->loadXML($xml);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $loaded ? $dom : null;
    }

    /**
     * @return array{success: false, error: string}
     */
    protected function failure(string $error, ?string $partialOutputPath = null): array
    {
        if ($partialOutputPath !== null && file_exists($partialOutputPath)) {
            unlink($partialOutputPath);
        }

        return [
            'success' => false,
            'error' => $error,
        ];
    }
}
