<?php

namespace App\Services\Resume;

class MarkdownToOpenXmlConverter
{
    protected const NAMESPACE_W = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    protected const BULLET_NUM_ID = '6';

    protected const STYLE_MAP = [
        'h1' => 'Heading1',
        'h2' => 'Heading2',
        'h3' => 'Heading3',
        'bullet' => 'ListParagraph',
        'paragraph' => 'Normal',
    ];

    /**
     * Section headings that are dropped rather than rendered.
     *
     * The summary opens the document directly under the letterhead, where a
     * "SUMMARY" heading repeats what its position already says and stacks a
     * second rule against the letterhead's. Suppressing it here rather than
     * only in ResumeMarkdownComposer also covers targeted resumes, whose
     * stored markdown already carries "# Summary" from the agent prompt.
     */
    protected const SUPPRESSED_HEADINGS = ['summary'];

    /**
     * Heading text, lowercased, mapped to the canonical section key.
     *
     * The canonical key — not the printed label — is what selects paragraph
     * styling and the column flow, so the label can change without touching
     * stored content or the targeted-resume agent prompt. Both spellings
     * resolve to the same section, so a targeted resume hand-edited to read
     * "# Professional Experience" keeps the styling "# Experience" gives it.
     */
    protected const SECTION_ALIASES = [
        'skills' => 'Skills',
        'technical skills' => 'Skills',
        'experience' => 'Experience',
        'professional experience' => 'Experience',
        'projects' => 'Projects',
        'selected projects' => 'Projects',
        'education' => 'Education',
    ];

    /**
     * The label printed for each canonical section key.
     *
     * The Heading1 style carries `<w:caps/>`, so these are stored in title
     * case and Word renders them uppercase — which also keeps the stored text
     * matching the headings on the site's own resume page.
     */
    protected const SECTION_LABELS = [
        'Skills' => 'Technical Skills',
        'Experience' => 'Professional Experience',
        'Projects' => 'Selected Projects',
        'Education' => 'Education',
    ];

    protected const SKILLS_SECTION = 'Skills';

    protected const SINGLE_COLUMN = 1;

    protected const SKILLS_COLUMNS = 2;

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
     * The column count of the run of paragraphs currently being emitted.
     *
     * Starts at one because that is what the template's own body `sectPr`
     * specifies, which is what governs everything after the last break.
     */
    protected int $currentColumns = self::SINGLE_COLUMN;

    /**
     * Convert a markdown string into an OpenXML fragment.
     */
    public function convert(string $markdown): string
    {
        $markdown = $this->stripCodeFences($markdown);

        // Remove any leading newlines that could cause blank first paragraph
        $markdown = ltrim($markdown, "\r");
        $markdown = ltrim($markdown, "\n");

        $lines = $this->parseLines($markdown);

        if (empty($lines)) {
            return '';
        }

        $this->currentSection = null;
        $this->currentColumns = self::SINGLE_COLUMN;

        $columns = $this->resolveColumns($lines);

        $xml = '';

        // Process each line
        foreach ($lines as $index => $line) {
            $xml .= $this->processLine($line, $columns[$index]);
        }

        if ($this->currentColumns !== self::SINGLE_COLUMN) {
            $xml .= $this->buildColumnBreak($this->currentColumns);
        }

        $this->currentSection = null;
        $this->currentColumns = self::SINGLE_COLUMN;

        return $xml;
    }

    /**
     * Process each line and build the corresponding XML, handling section changes and special cases.
     */
    protected function processLine(array $line, int $columns): string
    {
        $xml = '';

        if ($columns !== $this->currentColumns) {
            // A sectPr describes the section that *ends* with its paragraph, so
            // the break closing the preceding run carries that run's own column
            // count, not the count of what follows it.
            $xml .= $this->buildColumnBreak($this->currentColumns);
            $this->currentColumns = $columns;
        }

        if ($line['type'] === 'h1') {
            $this->currentSection = $line['text'];
        }

        if ($line['type'] === 'rule') {
            return $xml;
        }

        return $xml.$this->buildLineXml($line);
    }

    /**
     * Decide how many columns each parsed line's content belongs in.
     *
     * Only the skills section is ever multi-column. Within it a rule marker
     * divides the emphasized leading categories from the rest: content before
     * the marker stays full width and content after it flows in two columns. A
     * skills section with no marker — which is every stored targeted resume —
     * flows entirely in two columns.
     *
     * The marker line itself stays in the preceding run, so a marker with no
     * content after it leaves no empty two-column region behind.
     *
     * @param  array<int, array{type: string, text: string}>  $lines
     * @return array<int, int>
     */
    protected function resolveColumns(array $lines): array
    {
        $columns = [];
        $section = null;
        $sectionHasRule = false;
        $pastRule = false;

        foreach ($lines as $index => $line) {
            if ($line['type'] === 'h1') {
                $section = $line['text'];
                $sectionHasRule = $section === self::SKILLS_SECTION
                    && $this->sectionHasRule($lines, $index);
                $pastRule = false;
            }

            $columns[$index] = match (true) {
                $section !== self::SKILLS_SECTION => self::SINGLE_COLUMN,
                $line['type'] === 'h1' => self::SINGLE_COLUMN,
                $line['type'] === 'rule' => self::SINGLE_COLUMN,
                $sectionHasRule && ! $pastRule => self::SINGLE_COLUMN,
                default => self::SKILLS_COLUMNS,
            };

            if ($line['type'] === 'rule') {
                $pastRule = true;
            }
        }

        return $columns;
    }

    /**
     * Whether the section opened by the heading at the given index contains a
     * rule marker before the next heading ends it.
     *
     * @param  array<int, array{type: string, text: string}>  $lines
     */
    protected function sectionHasRule(array $lines, int $headingIndex): bool
    {
        $count = count($lines);

        for ($index = $headingIndex + 1; $index < $count; $index++) {
            if ($lines[$index]['type'] === 'h1') {
                return false;
            }

            if ($lines[$index]['type'] === 'rule') {
                return true;
            }
        }

        return false;
    }

    /**
     * Build XML for a single parsed line.
     */
    protected function buildLineXml(array $line): string
    {
        $styleId = $this->resolveStyleId($line['type'], $line['text']);
        $extraPpr = $line['type'] === 'bullet' ? $this->buildBulletNumPr() : null;
        $text = $line['type'] === 'h1'
            ? (self::SECTION_LABELS[$line['text']] ?? $line['text'])
            : $line['text'];

        if ($line['type'] === 'bullet' && str_starts_with($line['text'], 'Key Technologies:')) {
            $styleId = 'KeyTechnologies';
            $extraPpr = null;
        }

        return $this->buildParagraphXml($styleId, $text, $extraPpr);
    }

    /**
     * Strip ```tailored-resume``` code fences from the markdown.
     */
    protected function stripCodeFences(string $markdown): string
    {
        $markdown = preg_replace('/^```tailored-resume\s*\n/m', '', $markdown);
        $markdown = preg_replace('/^```\s*$/m', '', $markdown);

        return trim($markdown);
    }

    /**
     * Parse markdown lines into structured type/text pairs.
     *
     * @return array<int, array{type: string, text: string}>
     */
    protected function parseLines(string $markdown): array
    {
        $lines = explode("\n", $markdown);
        $parsed = [];

        foreach ($lines as $line) {
            $trimmed = trim($line);

            // Skip empty lines and whitespace-only lines
            if ($trimmed === '' || preg_match('/^\s*$/', $line)) {
                continue;
            }

            if (preg_match('/^###\s+(.+)$/', $trimmed, $matches)) {
                $parsed[] = ['type' => 'h3', 'text' => trim($matches[1])];
            } elseif (preg_match('/^##\s+(.+)$/', $trimmed, $matches)) {
                $parsed[] = ['type' => 'h2', 'text' => trim($matches[1])];
            } elseif (preg_match('/^#\s+(.+)$/', $trimmed, $matches)) {
                $heading = trim($matches[1]);

                if (in_array(strtolower($heading), self::SUPPRESSED_HEADINGS, true)) {
                    continue;
                }

                $parsed[] = [
                    'type' => 'h1',
                    'text' => self::SECTION_ALIASES[strtolower($heading)] ?? $heading,
                ];
            } elseif (preg_match('/^(?:-{3,}|\*{3,}|_{3,})$/', $trimmed)) {
                $parsed[] = ['type' => 'rule', 'text' => ''];
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
    protected function resolveStyleId(string $lineType, string $text): string
    {
        if ($this->currentSection !== null && isset(self::CONTEXTUAL_STYLES[$this->currentSection][$lineType])) {
            return self::CONTEXTUAL_STYLES[$this->currentSection][$lineType];
        }

        return self::STYLE_MAP[$lineType] ?? 'Normal';
    }

    /**
     * Build a <w:p> XML element with the given style and text content.
     */
    protected function buildParagraphXml(string $styleId, string $text, ?string $extraPpr = null): string
    {
        $ppr = '<w:pPr><w:pStyle w:val="'.$this->xmlEscape($styleId).'"/>';
        if ($extraPpr !== null) {
            $ppr .= $extraPpr;
        }
        $ppr .= '</w:pPr>';

        $runs = $this->buildRunsXml($text);

        return '<w:p xmlns:w="'.self::NAMESPACE_W.'">'.$ppr.$runs.'</w:p>';
    }

    /**
     * Build <w:r> elements from text, handling **bold** inline formatting.
     */
    protected function buildRunsXml(string $text): string
    {
        $parts = preg_split('/(\*\*[^*]+\*\*)/', $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

        if ($parts === false || empty($parts)) {
            return '';
        }

        $xml = '';
        foreach ($parts as $part) {
            if (preg_match('/^\*\*(.+)\*\*$/', $part, $matches)) {
                $xml .= '<w:r><w:rPr><w:b/></w:rPr><w:t xml:space="preserve">'
                    .$this->xmlEscape($matches[1])
                    .'</w:t></w:r>';
            } else {
                $xml .= '<w:r><w:t xml:space="preserve">'
                    .$this->xmlEscape($part)
                    .'</w:t></w:r>';
            }
        }

        return $xml;
    }

    /**
     * Build a continuous section break paragraph that switches column count.
     */
    protected function buildColumnBreak(int $columns): string
    {
        $ns = self::NAMESPACE_W;

        return '<w:p xmlns:w="'.$ns.'">'
            .'<w:pPr>'
            .'<w:sectPr>'
            .'<w:pgMar w:top="1037" w:right="720" w:bottom="547" w:left="720"/>'
            .'<w:cols w:num="'.$columns.'" w:space="360"/>'
            .'<w:type w:val="continuous"/>'
            .'</w:sectPr>'
            .'</w:pPr>'
            .'</w:p>';
    }

    /**
     * Build the <w:numPr> element for bullet list items.
     */
    protected function buildBulletNumPr(): string
    {
        return '<w:numPr><w:ilvl w:val="0"/><w:numId w:val="'.self::BULLET_NUM_ID.'"/></w:numPr>';
    }

    /**
     * Escape text for safe XML insertion.
     */
    protected function xmlEscape(string $text): string
    {
        return htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
