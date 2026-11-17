<?php

namespace App\Services\Resume\Diff;

/**
 * One résumé section's entries in display order: the draft's order, with
 * removed entries kept where they used to be.
 */
final readonly class SectionDiff
{
    /**
     * @param  list<EntryDiff>  $entries
     */
    public function __construct(
        public string $key,
        public string $title,
        public array $entries,
    ) {}

    /**
     * The entries of one skills group (`top` or `other`).
     *
     * @return list<EntryDiff>
     */
    public function group(string $group): array
    {
        return array_values(array_filter($this->entries, fn (EntryDiff $entry): bool => $entry->group === $group));
    }

    /**
     * @return list<FieldChange>
     */
    public function changes(): array
    {
        return array_merge([], ...array_map(fn (EntryDiff $entry): array => $entry->changes, $this->entries));
    }
}
