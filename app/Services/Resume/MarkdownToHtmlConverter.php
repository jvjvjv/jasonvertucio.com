<?php

namespace App\Services\Resume;

/**
 * Converts the shared resume/cover-letter Markdown dialect into HTML,
 * honouring the same section-key contract and column rules
 * `MarkdownToOpenXmlConverter` uses for the DOCX, from the same parsed
 * document model.
 */
class MarkdownToHtmlConverter
{
    protected const CONTEXTUAL_CLASSES = [
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

    public function __construct(protected MarkdownDocumentParser $parser = new MarkdownDocumentParser) {}

    /**
     * Convert a markdown string into an HTML fragment.
     */
    public function convert(string $markdown): string
    {
        $lines = $this->parser->parse($markdown);

        if ($lines === []) {
            return '';
        }

        $this->currentSection = null;

        $html = '';
        $index = 0;
        $count = count($lines);

        while ($index < $count) {
            $columns = $lines[$index]['columns'];
            $end = $index;

            while ($end < $count && $lines[$end]['columns'] === $columns) {
                $end++;
            }

            $region = array_slice($lines, $index, $end - $index);

            $html .= $columns === MarkdownDocumentParser::SKILLS_COLUMNS
                ? $this->columnsHtml($region)
                : $this->renderSequential($region);

            $index = $end;
        }

        return $html;
    }

    /**
     * Render a two-column skills region, split into per-category blocks at
     * each `h2` boundary so a category never splits across the column/page
     * break WeasyPrint lays the region out with.
     *
     * @param  array<int, array{type: string, text: string, columns: int}>  $region
     */
    protected function columnsHtml(array $region): string
    {
        $categories = [];
        $current = [];

        foreach ($region as $line) {
            if ($line['type'] === 'h2' && $current !== []) {
                $categories[] = $current;
                $current = [];
            }

            $current[] = $line;
        }

        if ($current !== []) {
            $categories[] = $current;
        }

        $html = '<div class="skills-columns">';

        foreach ($categories as $category) {
            $html .= '<div class="skill-category">'.$this->renderSequential($category).'</div>';
        }

        return $html.'</div>';
    }

    /**
     * Render an ordered run of lines, grouping consecutive plain bullets into
     * one list the way the OOXML emitter's numbering does.
     *
     * @param  array<int, array{type: string, text: string, columns: int}>  $lines
     */
    protected function renderSequential(array $lines): string
    {
        $html = '';
        $pendingBullets = [];

        foreach ($lines as $line) {
            if ($line['type'] === 'bullet' && ! str_starts_with($line['text'], 'Key Technologies:')) {
                $pendingBullets[] = '<li>'.$this->inline($line['text']).'</li>';

                continue;
            }

            $html .= $this->flushBullets($pendingBullets);

            if ($line['type'] === 'h1') {
                $this->currentSection = $line['text'];
            }

            $html .= $this->buildLineHtml($line);
        }

        return $html.$this->flushBullets($pendingBullets);
    }

    /**
     * @param  array<int, string>  $pendingBullets
     */
    protected function flushBullets(array &$pendingBullets): string
    {
        if ($pendingBullets === []) {
            return '';
        }

        $html = '<ul class="ListParagraph">'.implode('', $pendingBullets).'</ul>';
        $pendingBullets = [];

        return $html;
    }

    /**
     * @param  array{type: string, text: string, columns: int}  $line
     */
    protected function buildLineHtml(array $line): string
    {
        if ($line['type'] === 'rule') {
            return '';
        }

        // Only a Key Technologies bullet reaches here — plain bullets are
        // grouped into a list by renderSequential() before this is called.
        if ($line['type'] === 'bullet') {
            return '<p class="KeyTechnologies">'.$this->inline($line['text']).'</p>';
        }

        if ($line['type'] === 'h1') {
            $label = MarkdownDocumentParser::SECTION_LABELS[$line['text']] ?? $line['text'];

            return '<h1 class="Heading1">'.$this->escape($label).'</h1>';
        }

        if ($line['type'] === 'h2') {
            $class = self::CONTEXTUAL_CLASSES[$this->currentSection]['h2'] ?? 'Heading2';
            $tag = $class === 'Heading2' ? 'h2' : 'p';

            return "<{$tag} class=\"{$class}\">".$this->inline($line['text'])."</{$tag}>";
        }

        if ($line['type'] === 'h3') {
            $class = self::CONTEXTUAL_CLASSES[$this->currentSection]['h3'] ?? 'Heading3';
            $tag = $class === 'Heading3' ? 'h3' : 'p';

            return "<{$tag} class=\"{$class}\">".$this->inline($line['text'])."</{$tag}>";
        }

        return '<p class="Normal">'.$this->inline($line['text']).'</p>';
    }

    /**
     * Render inline **bold** formatting the same way the OOXML converter does.
     */
    protected function inline(string $text): string
    {
        $parts = preg_split('/(\*\*[^*]+\*\*)/', $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

        if ($parts === false || $parts === []) {
            return '';
        }

        $html = '';
        foreach ($parts as $part) {
            if (preg_match('/^\*\*(.+)\*\*$/', $part, $matches)) {
                $html .= '<strong>'.$this->escape($matches[1]).'</strong>';
            } else {
                $html .= $this->escape($part);
            }
        }

        return $html;
    }

    protected function escape(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
