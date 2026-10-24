<?php

namespace App\Services\Resume;

/**
 * Turns the structured resume data into the Markdown dialect the document
 * renderer understands.
 *
 * MarkdownToOpenXmlConverter picks its paragraph styles from the current H1's
 * text — "Skills" switches into a two-column flow, "Experience" maps H2/H3 to
 * the JobTitle/CompanyInfo styles — so these section headings are a contract,
 * not a presentation choice. They match the structure the targeted-resume
 * agent is instructed to produce, which is what lets both resumes render
 * identically from the same template.
 */
class ResumeMarkdownComposer
{
    /**
     * Shown in place of an end date for a role that is still current.
     */
    protected const PRESENT = 'Present';

    /**
     * Compose the resume body.
     *
     * @param  array<string, mixed>  $data  The array returned by ResumeDataServiceContract::getDocxData().
     */
    public function compose(array $data): string
    {
        $sections = array_filter([
            $this->summarySection($data),
            $this->skillsSection($data),
            $this->experienceSection($data),
            $this->projectsSection($data),
            $this->educationSection($data),
        ], fn (string $section): bool => $section !== '');

        return implode("\n\n", $sections);
    }

    /**
     * The summary opens the resume directly, with no heading.
     *
     * A "SUMMARY" heading sits immediately under the letterhead and repeats
     * what the position on the page already says, and its rule stacks against
     * the letterhead's own rule. The text alone reads better.
     *
     * @param  array<string, mixed>  $data
     */
    protected function summarySection(array $data): string
    {
        return trim((string) ($data['summary'] ?? ''));
    }

    /**
     * Marks where the emphasized leading skills end and the rest begin.
     *
     * MarkdownToOpenXmlConverter reads this as the boundary between the
     * full-width run and the two-column flow, matching how the site's resume
     * page stacks the top groups and grids the remainder. It is emitted only
     * when there are top categories; without it the converter flows the whole
     * section in two columns, which is what targeted resumes want.
     */
    protected const COLUMN_MARKER = '---';

    /**
     * Top skills lead, then the remaining categories, in one section divided
     * by the column marker.
     *
     * @param  array<string, mixed>  $data
     */
    protected function skillsSection(array $data): string
    {
        $groups = $data['skills'] ?? [];

        $top = $this->skillLines(is_array($groups['top'] ?? null) ? $groups['top'] : []);
        $other = $this->skillLines(is_array($groups['other'] ?? null) ? $groups['other'] : []);

        if ($top === [] && $other === []) {
            return '';
        }

        $lines = $top === []
            ? $other
            : array_merge($top, [self::COLUMN_MARKER], $other);

        return "# Skills\n".implode("\n", $lines);
    }

    /**
     * Render one group of skill categories as heading/list line pairs.
     *
     * @param  array<int, mixed>  $categories
     * @return array<int, string>
     */
    protected function skillLines(array $categories): array
    {
        $lines = [];

        foreach ($categories as $category) {
            if (! is_array($category)) {
                continue;
            }

            $title = trim((string) ($category['title'] ?? ''));
            $list = trim((string) ($category['listJoined'] ?? ''));

            if ($list === '' && isset($category['list']) && is_array($category['list'])) {
                $list = implode(', ', $category['list']);
            }

            if ($title === '' && $list === '') {
                continue;
            }

            if ($title !== '') {
                $lines[] = '## '.$title;
            }

            if ($list !== '') {
                $lines[] = $list;
            }
        }

        return $lines;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function experienceSection(array $data): string
    {
        $jobs = is_array($data['experience'] ?? null) ? $data['experience'] : [];
        $blocks = [];

        foreach ($jobs as $job) {
            $jobTitle = trim((string) ($job['jobTitle'] ?? ''));
            $lines = [];

            if ($jobTitle !== '') {
                $lines[] = '## '.$jobTitle;
            }

            $metaLine = $this->joinMeta([
                trim((string) ($job['company'] ?? '')),
                trim((string) ($job['location'] ?? '')),
                $this->dateRange($job, endsWithPresent: true),
            ]);

            if ($metaLine !== '') {
                $lines[] = '### '.$metaLine;
            }

            foreach ($this->bullets($job) as $bullet) {
                $lines[] = '- '.$bullet;
            }

            if ($lines !== []) {
                $blocks[] = implode("\n", $lines);
            }
        }

        if ($blocks === []) {
            return '';
        }

        return "# Experience\n".implode("\n\n", $blocks);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function projectsSection(array $data): string
    {
        $projects = is_array($data['projects'] ?? null) ? $data['projects'] : [];
        $blocks = [];

        foreach ($projects as $project) {
            $name = trim((string) ($project['projectName'] ?? ''));
            $description = trim((string) ($project['description'] ?? ''));
            $lines = [];

            if ($name !== '') {
                $lines[] = '## '.$name;
            }

            if ($description !== '') {
                $lines[] = $description;
            }

            foreach ($this->bullets($project) as $bullet) {
                $lines[] = '- '.$bullet;
            }

            if ($lines !== []) {
                $blocks[] = implode("\n", $lines);
            }
        }

        if ($blocks === []) {
            return '';
        }

        return "# Projects\n".implode("\n\n", $blocks);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function educationSection(array $data): string
    {
        $entries = is_array($data['education'] ?? null) ? $data['education'] : [];
        $blocks = [];

        foreach ($entries as $entry) {
            $degree = trim((string) ($entry['degree'] ?? ''));
            $lines = [];

            if ($degree !== '') {
                $lines[] = '## '.$degree;
            }

            $metaLine = $this->joinMeta([
                trim((string) ($entry['institution'] ?? '')),
                trim((string) ($entry['location'] ?? '')),
                $this->dateRange($entry, endsWithPresent: false),
                trim((string) ($entry['level'] ?? '')),
            ]);

            if ($metaLine !== '') {
                $lines[] = '### '.$metaLine;
            }

            $description = trim((string) ($entry['description'] ?? ''));
            if ($description !== '') {
                $lines[] = $description;
            }

            if ($lines !== []) {
                $blocks[] = implode("\n", $lines);
            }
        }

        if ($blocks === []) {
            return '';
        }

        return "# Education\n".implode("\n\n", $blocks);
    }

    /**
     * Build the date portion of a meta line.
     *
     * An entry with no dates at all contributes nothing. A role with a start
     * but no end is still current, so it reads "Present"; an education entry
     * with the same shape just shows its start year.
     *
     * @param  array<string, mixed>  $entry
     */
    protected function dateRange(array $entry, bool $endsWithPresent): string
    {
        $start = trim((string) ($entry['dateStart'] ?? ''));
        $end = trim((string) ($entry['dateEnd'] ?? ''));

        if ($start === '' && $end === '') {
            return '';
        }

        if ($start === '') {
            return $end;
        }

        if ($end !== '') {
            return $start.' - '.$end;
        }

        return $endsWithPresent
            ? $start.' - '.self::PRESENT
            : $start;
    }

    /**
     * @param  array<int, string>  $parts
     */
    protected function joinMeta(array $parts): string
    {
        return implode(' - ', array_filter($parts, fn (string $part): bool => $part !== ''));
    }

    /**
     * @param  array<string, mixed>  $entry
     * @return array<int, string>
     */
    protected function bullets(array $entry): array
    {
        $bullets = is_array($entry['bullets'] ?? null) ? $entry['bullets'] : [];

        $cleaned = [];
        foreach ($bullets as $bullet) {
            $text = trim((string) $bullet);

            if ($text !== '') {
                $cleaned[] = $text;
            }
        }

        return $cleaned;
    }
}
