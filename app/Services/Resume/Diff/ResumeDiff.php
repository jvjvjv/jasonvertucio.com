<?php

namespace App\Services\Resume\Diff;

/**
 * The differences between a baseline and a draft revision, by section in
 * résumé order. The Changes panel, the marked-up résumé and the MCP tool all
 * read this one result, so they always agree.
 */
final readonly class ResumeDiff
{
    /**
     * @param  array<string, SectionDiff>  $sections  keyed `personal`, `skills`, `experience`, `education`, `projects`
     */
    public function __construct(public array $sections) {}

    public function section(string $key): SectionDiff
    {
        return $this->sections[$key];
    }

    /**
     * Every change, flattened, in résumé order.
     *
     * @return list<array{section: string, section_key: string, item: string, field: string, kind: string, before: ?string, after: ?string, shown: bool, tokens: list<array{op: string, text: string}>|null}>
     */
    public function changes(): array
    {
        $changes = [];

        foreach ($this->sections as $section) {
            foreach ($section->entries as $entry) {
                foreach ($entry->changes as $change) {
                    $changes[] = [
                        'section' => $section->title,
                        'section_key' => $section->key,
                        'item' => $entry->label,
                        'field' => $change->label,
                        'kind' => $change->kind,
                        'before' => $change->before,
                        'after' => $change->after,
                        'shown' => $change->shown,
                        'tokens' => $change->tokens,
                    ];
                }
            }
        }

        return $changes;
    }

    /**
     * The flat changes grouped by section title, sections with none left out.
     *
     * @return array<string, list<array{section: string, section_key: string, item: string, field: string, kind: string, before: ?string, after: ?string, shown: bool, tokens: list<array{op: string, text: string}>|null}>>
     */
    public function changesBySection(): array
    {
        $grouped = [];

        foreach ($this->changes() as $change) {
            $grouped[$change['section']][] = $change;
        }

        return $grouped;
    }

    public function count(): int
    {
        return count($this->changes());
    }

    public function isEmpty(): bool
    {
        return $this->count() === 0;
    }
}
