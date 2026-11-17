<?php

namespace App\Services\Resume\Diff;

/**
 * One entry (a job, school, project, skill category or the personal info)
 * as compared. `before` and `after` are the normalised entries; one is
 * empty when the entry was added or removed.
 */
final readonly class EntryDiff
{
    public const string ADDED = 'added';

    public const string REMOVED = 'removed';

    public const string MODIFIED = 'modified';

    public const string UNCHANGED = 'unchanged';

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @param  list<FieldChange>  $changes  everything reported for the entry, in reading order
     * @param  list<array{status: string, text: string, tokens: list<array{op: string, text: string}>|null}>  $bullets
     * @param  list<array{status: string, text: string}>  $items  skill items, removed ones in place
     */
    public function __construct(
        public string $status,
        public string $label,
        public array $before,
        public array $after,
        public bool $moved = false,
        public array $changes = [],
        public array $bullets = [],
        public array $items = [],
        public ?string $group = null,
    ) {}

    public function isAdded(): bool
    {
        return $this->status === self::ADDED;
    }

    public function isRemoved(): bool
    {
        return $this->status === self::REMOVED;
    }

    /**
     * The change reported for one field of a matched entry, if any.
     */
    public function field(string $field): ?FieldChange
    {
        foreach ($this->changes as $change) {
            if ($change->field === $field) {
                return $change;
            }
        }

        return null;
    }

    /**
     * A field's value as displayed: the draft's, or the old one for a removed entry.
     */
    public function value(string $field): string
    {
        $source = $this->isRemoved() ? $this->before : $this->after;
        $value = $source[$field] ?? '';

        return is_string($value) ? $value : '';
    }
}
