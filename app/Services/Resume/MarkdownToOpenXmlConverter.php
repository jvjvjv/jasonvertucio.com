<?php

namespace App\Services\Resume;

class MarkdownToOpenXmlConverter {
    protected const NAMESPACE_W = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    protected const BULLET_NUM_ID = '6';

    protected const STYLE_MAP = [
        'h1' => 'Heading1',
        'h2' => 'Heading2',
        'h3' => 'Heading3',
        'bullet' => 'ListParagraph',
        'paragraph' => 'Normal',
    ];

    protected const CONTEXTUAL_STYLES = [
        'Experience' => [
            'h2' => 'JobTitle',
            'h3' => 'CompanyInfo',
        ],
        'Education' => [
            'h2' => 'Heading2',
            'h3' => 'CompanyInfo',
        ],
    ];

    protected ?string $currentSection = null;

    /**
     * Convert a markdown string into an OpenXML fragment.
     */
    public function convert(string $markdown): string {
        $markdown = $this->stripCodeFences($markdown);
        $lines = $this->parseLines($markdown);

        if (empty($lines)) {
            return '';
        }

        $xml = '';

        // Process each line
        foreach ($lines as $line) {
            $xml .= $this->processLine($line);
        }

        if ($this->currentSection === 'Skills') {
            $xml .= $this->buildColumnBreak(1);
        }

        $this->currentSection = null;

        return $xml;
    }

    /**
     * Process each line and build the corresponding XML, handling section changes and special cases.
     */
    protected function processLine(array $line): string {
        // Initialize $xml
        $xml = '';
        // Check for section changes
        if ($line['type'] === 'h1') {
            if ($this->currentSection === 'Skills') {
                $xml = $this->buildColumnBreak(1);
            }

            $this->currentSection = $line['text'];
        }
        $xml .= $this->buildLineXml($line);

        if ($line['type'] === 'h1' && $line['text'] === 'Skills') {
            $xml .= $this->buildColumnBreak(2);
        }

        return $xml;
    }

    /**
     * Build XML for a single parsed line.
     */
    protected function buildLineXml(array $line): string {
        $styleId = $this->resolveStyleId($line['type'], $line['text']);
        $extraPpr = $line['type'] === 'bullet' ? $this->buildBulletNumPr() : null;

        if ($line['type'] === 'bullet' && str_starts_with($line['text'], 'Key Technologies:')) {
            $styleId = 'KeyTechnologies';
            $extraPpr = null;
        }

        return $this->buildParagraphXml($styleId, $line['text'], $extraPpr);
    }

    /**
     * Strip ```tailored-resume``` code fences from the markdown.
     */
    protected function stripCodeFences(string $markdown): string {
        $markdown = preg_replace('/^```tailored-resume\s*\n/m', '', $markdown);
        $markdown = preg_replace('/^```\s*$/m', '', $markdown);

        return trim($markdown);
    }

    /**
     * Parse markdown lines into structured type/text pairs.
     *
     * @return array<int, array{type: string, text: string}>
     */
    protected function parseLines(string $markdown): array {
        $lines = explode("\n", $markdown);
        $parsed = [];

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if ($trimmed === '') {
                continue;
            }

            if (preg_match('/^###\s+(.+)$/', $trimmed, $matches)) {
                $parsed[] = ['type' => 'h3', 'text' => trim($matches[1])];
            } elseif (preg_match('/^##\s+(.+)$/', $trimmed, $matches)) {
                $parsed[] = ['type' => 'h2', 'text' => trim($matches[1])];
            } elseif (preg_match('/^#\s+(.+)$/', $trimmed, $matches)) {
                $parsed[] = ['type' => 'h1', 'text' => trim($matches[1])];
            } elseif (preg_match('/^[-*]\s+(.+)$/', $trimmed, $matches)) {
                $parsed[] = ['type' => 'bullet', 'text' => trim($matches[1])];
            } else {
                $parsed[] = ['type' => 'paragraph', 'text' => $trimmed];
            }
        }

        return $parsed;
    }

    /**
     * Resolve the style ID based on line type and current section context.
     */
    protected function resolveStyleId(string $lineType, string $text): string {
        if ($this->currentSection !== null && isset(self::CONTEXTUAL_STYLES[$this->currentSection][$lineType])) {
            return self::CONTEXTUAL_STYLES[$this->currentSection][$lineType];
        }

        return self::STYLE_MAP[$lineType] ?? 'Normal';
    }

    /**
     * Build a <w:p> XML element with the given style and text content.
     */
    protected function buildParagraphXml(string $styleId, string $text, ?string $extraPpr = null): string {
        $ppr = '<w:pPr><w:pStyle w:val="' . $this->xmlEscape($styleId) . '"/>';
        if ($extraPpr !== null) {
            $ppr .= $extraPpr;
        }
        $ppr .= '</w:pPr>';

        $runs = $this->buildRunsXml($text);

        return '<w:p xmlns:w="' . self::NAMESPACE_W . '">' . $ppr . $runs . '</w:p>';
    }

    /**
     * Build <w:r> elements from text, handling **bold** inline formatting.
     */
    protected function buildRunsXml(string $text): string {
        $parts = preg_split('/(\*\*[^*]+\*\*)/', $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

        if ($parts === false || empty($parts)) {
            return '';
        }

        $xml = '';
        foreach ($parts as $part) {
            if (preg_match('/^\*\*(.+)\*\*$/', $part, $matches)) {
                $xml .= '<w:r><w:rPr><w:b/></w:rPr><w:t xml:space="preserve">'
                    . $this->xmlEscape($matches[1])
                    . '</w:t></w:r>';
            } else {
                $xml .= '<w:r><w:t xml:space="preserve">'
                    . $this->xmlEscape($part)
                    . '</w:t></w:r>';
            }
        }

        return $xml;
    }

    /**
     * Build a continuous section break paragraph that switches column count.
     */
    protected function buildColumnBreak(int $columns): string {
        $ns = self::NAMESPACE_W;

        return '<w:p xmlns:w="' . $ns . '">'
            . '<w:pPr>'
            . '<w:sectPr>'
            . '<w:pgMar w:top="1037" w:right="720" w:bottom="547" w:left="720"/>'
            . '<w:cols w:num="' . $columns . '" w:space="360"/>'
            . '<w:type w:val="continuous"/>'
            . '</w:sectPr>'
            . '</w:pPr>'
            . '</w:p>';
    }

    /**
     * Build the <w:numPr> element for bullet list items.
     */
    protected function buildBulletNumPr(): string {
        return '<w:numPr><w:ilvl w:val="0"/><w:numId w:val="' . self::BULLET_NUM_ID . '"/></w:numPr>';
    }

    /**
     * Escape text for safe XML insertion.
     */
    protected function xmlEscape(string $text): string {
        return htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
