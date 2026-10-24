<?php

namespace App\Services\Resume;

use DOMDocument;
use DOMXPath;

/**
 * Substitutes `{token}` placeholders inside a `word/document.xml` fragment,
 * including ones Word has split across adjacent text runs (e.g. `{title} • {`
 * / `url` / `}`).
 *
 * Shared by `DocumentRenderer` (the DOCX body) and `HtmlDocumentComposer`
 * (the PDF's letterhead, read from the same template paragraphs) so the two
 * formats can never substitute a placeholder differently.
 */
class PlaceholderSubstitutor
{
    protected const NAMESPACE_W = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    /**
     * Word can split a placeholder across this many adjacent text runs before
     * we stop trying to piece it back together.
     */
    protected const MAX_SPLIT_RUN_LOOKAHEAD = 12;

    /**
     * Substitute {token} placeholders, including ones Word split across runs.
     *
     * @param  array<string, string>  $placeholders
     */
    public function substitute(string $xml, array $placeholders): string
    {
        foreach ($placeholders as $token => $value) {
            $xml = str_replace(
                '{'.$token.'}',
                htmlspecialchars((string) $value, ENT_XML1, 'UTF-8'),
                $xml
            );
        }

        return $this->substituteSplitRuns($xml, $placeholders);
    }

    /**
     * Word sometimes stores a placeholder as separate text runs (e.g. "{",
     * "url", "}"). Piece those back together and substitute them.
     *
     * @param  array<string, string>  $placeholders
     */
    protected function substituteSplitRuns(string $xml, array $placeholders): string
    {
        if ($placeholders === []) {
            return $xml;
        }

        $dom = $this->parseXml($xml);
        if ($dom === null) {
            return $xml;
        }

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('w', self::NAMESPACE_W);
        $textNodes = $xpath->query('//w:t');

        if ($textNodes === false || $textNodes->length === 0) {
            return $xml;
        }

        $nodes = [];
        foreach ($textNodes as $textNode) {
            $nodes[] = $textNode;
        }

        $nodeCount = count($nodes);

        for ($i = 0; $i < $nodeCount; $i++) {
            $openingText = $nodes[$i]->textContent;

            // The opening brace is often the tail of a run that also carries
            // surrounding text, e.g. " • {" before a split "{url}".
            if (! str_ends_with($openingText, '{')) {
                continue;
            }

            $token = '';
            $endIndex = null;
            $trailingText = '';
            $lookaheadLimit = min($i + self::MAX_SPLIT_RUN_LOOKAHEAD, $nodeCount);

            for ($j = $i + 1; $j < $lookaheadLimit; $j++) {
                $content = $nodes[$j]->textContent;
                $closingBrace = strpos($content, '}');

                if ($closingBrace !== false) {
                    $token .= substr($content, 0, $closingBrace);
                    $trailingText = substr($content, $closingBrace + 1);
                    $endIndex = $j;
                    break;
                }

                $token .= $content;
            }

            if ($endIndex === null || ! array_key_exists($token, $placeholders)) {
                continue;
            }

            $leadingText = substr($openingText, 0, -1);

            $this->setTextNodeValue($dom, $nodes[$i], $leadingText.(string) $placeholders[$token]);

            for ($k = $i + 1; $k < $endIndex; $k++) {
                $this->setTextNodeValue($dom, $nodes[$k], '');
            }

            $this->setTextNodeValue($dom, $nodes[$endIndex], $trailingText);

            $i = $endIndex;
        }

        return $dom->saveXML() ?: $xml;
    }

    /**
     * Replace a w:t node's text, keeping Word from collapsing significant
     * leading or trailing whitespace.
     */
    protected function setTextNodeValue(DOMDocument $dom, \DOMNode $node, string $value): void
    {
        $node->nodeValue = '';

        if ($value !== '') {
            $node->appendChild($dom->createTextNode($value));
        }

        if ($node instanceof \DOMElement && $value !== trim($value)) {
            $node->setAttribute('xml:space', 'preserve');
        }
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
}
