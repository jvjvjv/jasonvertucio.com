<?php

namespace App\Services\Resume\Diff;

/**
 * One reported difference: a field of an entry, a bullet, a skill, or the
 * entry as a whole (added, removed, moved).
 */
final readonly class FieldChange
{
    public const string ADDED = 'added';

    public const string REMOVED = 'removed';

    public const string MODIFIED = 'modified';

    public const string FORMATTING = 'formatting';

    public const string MOVED = 'moved';

    public const string ORDER_CHANGED = 'order_changed';

    /**
     * @param  string  $field  the field's key (`jobTitle`, `bullets.3`, `list`), empty for the entry itself
     * @param  string  $label  the field as a reader names it ("Job title", "Bullet 3"), empty for the entry itself
     * @param  list<array{op: string, text: string}>|null  $tokens  word segments for a modified text field
     */
    public function __construct(
        public string $field,
        public string $label,
        public string $kind,
        public ?string $before = null,
        public ?string $after = null,
        public bool $shown = true,
        public ?array $tokens = null,
    ) {}
}
