<?php

namespace App\Services\Resume;

/**
 * Parses the shared resume/cover-letter Markdown dialect into a structured
 * line list, so the OOXML and HTML emitters consume one parse instead of
 * each re-implementing the section vocabulary and column rules.
 *
 * A divergence here — a heading alias resolved one way for the DOCX and
 * another for the PDF — would be invisible until someone compared the two
 * documents by eye, which is exactly what this class exists to prevent.
 */
class MarkdownDocumentParser
{
    /**
     * Section headings that are dropped rather than rendered.
     *
     * The summary opens the document directly under the letterhead, where a
     * "SUMMARY" heading repeats what its position already says and stacks a
     * second rule against the letterhead's. Suppressing it here rather than
     * in each emitter also covers targeted resumes, whose stored markdown
     * already carries "# Summary" from the agent prompt.
     */
    public const SUPPRESSED_HEADINGS = ['summary'];

    /**
     * Heading text, lowercased, mapped to the canonical section key.
     *
     * The canonical key — not the printed label — is what selects paragraph
     * styling and the column flow, so the label can change without touching
     * stored content or the targeted-resume agent prompt. Both spellings
     * resolve to the same section, so a targeted resume hand-edited to read
     * "# Professional Experience" keeps the styling "# Experience" gives it.
     */
    public const SECTION_ALIASES = [
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
    public const SECTION_LABELS = [
        'Skills' => 'Technical Skills',
        'Experience' => 'Professional Experience',
        'Projects' => 'Selected Projects',
        'Education' => 'Education',
    ];

    public const SKILLS_SECTION = 'Skills';

    public const SINGLE_COLUMN = 1;

    public const SKILLS_COLUMNS = 2;

    /**
     * Parse a Markdown string into a structured line list.
     *
     * Each line carries its type (`h1`, `h2`, `h3`, `bullet`, `paragraph`,
     * `rule`), its text (the section key, not the printed label, for `h1`),
     * and the column count the region it belongs in should be laid out in.
     *
     * @return array<int, array{type: string, text: string, columns: int}>
     */
    public function parse(string $markdown): array
    {
        $markdown = $this->stripCodeFences($markdown);

        // Remove any leading newlines that could cause a blank first paragraph.
        $markdown = ltrim($markdown, "\r");
        $markdown = ltrim($markdown, "\n");

        $lines = $this->parseLines($markdown);

        if ($lines === []) {
            return [];
        }

        $columns = $this->resolveColumns($lines);

        foreach ($lines as $index => &$line) {
            $line['columns'] = $columns[$index];
        }

        return $lines;
    }

    /**
     * Strip ```tailored-resume``` code fences from the markdown.
     */
    public function stripCodeFences(string $markdown): string
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
    public function parseLines(string $markdown): array
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
    public function resolveColumns(array $lines): array
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
    public function sectionHasRule(array $lines, int $headingIndex): bool
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
}
