<?php

namespace App\Services\Resume\Diff;

use App\Enums\SalaryPeriod;
use SebastianBergmann\Diff\Differ;
use SebastianBergmann\Diff\Output\UnifiedDiffOutputBuilder;

/**
 * Compares two résumé snapshots in the editor's shape
 * (`DatabaseResumeDataService::getAllEditableData()`).
 *
 * Both sides are shaped the way the résumé components tolerate a malformed
 * draft, so a bad snapshot is compared rather than thrown on. Entries are
 * matched by identity, not position; leftovers that still share at least
 * half their fields are paired as one modified entry. Only the fields listed
 * here are compared, and missing, null and empty are equal.
 */
class ResumeSnapshotDiffer
{
    /** Leftover entries sharing at least this share of fields are one modified entry. */
    private const float ENTRY_SIMILARITY = 0.5;

    /** A removed and an added bullet sharing at least this share of words are one reworded bullet. */
    private const float BULLET_SIMILARITY = 0.5;

    /**
     * Per section: its title, its compared fields in reading order (with the
     * label a reader knows them by), and the fields the résumé never shows.
     *
     * @var array<string, array{title: string, fields: array<string, string>, hidden: list<string>, plain: list<string>}>
     */
    private const array SECTIONS = [
        'personal' => [
            'title' => 'Personal info',
            'fields' => [
                'name' => 'Name',
                'title' => 'Title',
                'summary' => 'Summary',
                'email' => 'Email',
                'phone' => 'Phone',
                'linkedin' => 'LinkedIn',
                'url' => 'Website',
            ],
            'hidden' => ['email', 'phone', 'linkedin', 'url'],
            'plain' => [],
        ],
        'skills' => [
            'title' => 'Skills',
            'fields' => ['title' => 'Category'],
            'hidden' => [],
            'plain' => [],
        ],
        'experience' => [
            'title' => 'Experience',
            'fields' => [
                'jobTitle' => 'Job title',
                'company' => 'Company',
                'dates' => 'Dates',
                'location' => 'Location',
                'jobTitleLabel' => 'Title label',
                'salaryStart' => 'Salary (start)',
                'salaryEnd' => 'Salary (end)',
                'isFreelance' => 'Freelance',
            ],
            'hidden' => ['jobTitleLabel', 'salaryStart', 'salaryEnd', 'isFreelance'],
            'plain' => ['salaryStart', 'salaryEnd', 'isFreelance'],
        ],
        'education' => [
            'title' => 'Education',
            'fields' => [
                'institution' => 'Institution',
                'degree' => 'Degree',
                'level' => 'Level',
                'dates' => 'Dates',
                'location' => 'Location',
                'description' => 'Description',
            ],
            'hidden' => [],
            'plain' => [],
        ],
        'projects' => [
            'title' => 'Projects',
            'fields' => ['projectName' => 'Name', 'description' => 'Description'],
            'hidden' => [],
            'plain' => [],
        ],
    ];

    /**
     * @param  array<mixed>  $before  the baseline
     * @param  array<mixed>  $after  the draft
     */
    public function diff(array $before, array $after): ResumeDiff
    {
        return new ResumeDiff([
            'personal' => $this->personal($before, $after),
            'skills' => $this->skills($before, $after),
            'experience' => $this->listSection('experience', $before, $after),
            'education' => $this->listSection('education', $before, $after),
            'projects' => $this->listSection('projects', $before, $after),
        ]);
    }

    private function personal(array $before, array $after): SectionDiff
    {
        $old = $this->normalizeEntry('personal', is_array($before['personal'] ?? null) ? $before['personal'] : []);
        $new = $this->normalizeEntry('personal', is_array($after['personal'] ?? null) ? $after['personal'] : []);

        return new SectionDiff('personal', self::SECTIONS['personal']['title'], [
            $this->compareEntries('personal', $old, $new, false),
        ]);
    }

    private function skills(array $before, array $after): SectionDiff
    {
        $entries = [];

        foreach (['top', 'other'] as $group) {
            $old = $this->skillGroup($before, $group);
            $new = $this->skillGroup($after, $group);

            foreach ($this->matchAndOrder('skills', $old, $new) as $entry) {
                $entries[] = $entry;
            }
        }

        return new SectionDiff('skills', self::SECTIONS['skills']['title'], $entries);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function skillGroup(array $data, string $group): array
    {
        $skills = is_array($data['skills'] ?? null) ? $data['skills'] : [];
        $categories = is_array($skills[$group] ?? null) ? $skills[$group] : [];

        return array_map(
            fn (array $category): array => $this->normalizeEntry('skills', $category) + ['group' => $group],
            array_values(array_filter($categories, 'is_array')),
        );
    }

    private function listSection(string $key, array $before, array $after): SectionDiff
    {
        return new SectionDiff(
            $key,
            self::SECTIONS[$key]['title'],
            $this->matchAndOrder($key, $this->entries($key, $before), $this->entries($key, $after)),
        );
    }

    /**
     * A section's entries, shaped like the components shape them: one entry
     * sent where the list belongs is that one entry; non-arrays are dropped.
     *
     * @return list<array<string, mixed>>
     */
    private function entries(string $key, array $data): array
    {
        $section = $data[$key] ?? [];

        if (! is_array($section)) {
            return [];
        }

        $entries = array_is_list($section) ? $section : [$section];

        return array_map(
            fn (array $entry): array => $this->normalizeEntry($key, $entry),
            array_values(array_filter($entries, 'is_array')),
        );
    }

    /**
     * The compared fields as display strings, plus `bullets` (experience and
     * projects) or `list` (skills) as lists of trimmed strings.
     *
     * @param  array<mixed>  $entry
     * @return array<string, mixed>
     */
    private function normalizeEntry(string $section, array $entry): array
    {
        $normalized = [];

        foreach (array_keys(self::SECTIONS[$section]['fields']) as $field) {
            $normalized[$field] = match ($field) {
                'dates' => $this->dates($entry['dates'] ?? null),
                'salaryStart', 'salaryEnd' => $this->salary($entry[$field] ?? null),
                'isFreelance' => ! empty($entry['isFreelance']) ? 'Yes' : '',
                default => $this->text($entry[$field] ?? null),
            };
        }

        if (in_array($section, ['experience', 'projects'], true)) {
            $normalized['bullets'] = $this->strings($entry['bullets'] ?? null);
        }

        if ($section === 'skills') {
            $normalized['list'] = $this->strings($entry['list'] ?? null);
        }

        return $normalized;
    }

    private function text(mixed $value): string
    {
        return is_scalar($value) && ! is_bool($value) ? trim((string) $value) : '';
    }

    private function dates(mixed $value): string
    {
        if (! is_array($value)) {
            return $this->text($value);
        }

        return implode(' - ', array_filter(array_map($this->text(...), $value), fn (string $part): bool => $part !== ''));
    }

    private function salary(mixed $value): string
    {
        if (! is_array($value) || ! is_numeric($value['amount'] ?? null) || (float) $value['amount'] <= 0) {
            return '';
        }

        $amount = (float) $value['amount'];
        $formatted = number_format($amount, floor($amount) === $amount ? 0 : 2);
        $period = match (is_string($value['period'] ?? null) ? SalaryPeriod::tryFrom($value['period']) : null) {
            SalaryPeriod::PerHour => 'hour',
            SalaryPeriod::PerMonth => 'month',
            SalaryPeriod::PerYear => 'year',
            null => null,
        };

        return $period !== null ? "{$formatted} / {$period}" : $formatted;
    }

    /**
     * @return list<string>
     */
    private function strings(mixed $value): array
    {
        if (is_string($value)) {
            $value = [$value];
        }

        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map($this->text(...), $value), fn (string $item): bool => $item !== ''));
    }

    /**
     * Match a section's entries, then list them in display order: the
     * draft's order, with each removed entry after the entry that preceded
     * it in the baseline.
     *
     * @param  list<array<string, mixed>>  $old
     * @param  list<array<string, mixed>>  $new
     * @return list<EntryDiff>
     */
    private function matchAndOrder(string $section, array $old, array $new): array
    {
        $oldFor = $this->match($section, $old, $new);
        $newFor = array_flip($oldFor);
        $moved = $this->movedNewIndexes($oldFor);

        $removedAfter = [];

        foreach (array_keys($old) as $oldIndex) {
            if (isset($newFor[$oldIndex])) {
                continue;
            }

            $anchor = -1;

            for ($previous = $oldIndex - 1; $previous >= 0; $previous--) {
                if (isset($newFor[$previous])) {
                    $anchor = $newFor[$previous];
                    break;
                }
            }

            $removedAfter[$anchor][] = $oldIndex;
        }

        $ordered = [];
        $pushRemoved = function (int $anchor) use (&$ordered, $removedAfter, $old, $section): void {
            foreach ($removedAfter[$anchor] ?? [] as $oldIndex) {
                $ordered[] = $this->wholeEntry($section, EntryDiff::REMOVED, $old[$oldIndex]);
            }
        };

        $pushRemoved(-1);

        foreach ($new as $newIndex => $entry) {
            $ordered[] = isset($oldFor[$newIndex])
                ? $this->compareEntries($section, $old[$oldFor[$newIndex]], $entry, isset($moved[$newIndex]))
                : $this->wholeEntry($section, EntryDiff::ADDED, $entry);
            $pushRemoved($newIndex);
        }

        return $ordered;
    }

    /**
     * Pair draft entries with baseline entries: by identity first, then
     * leftovers by shared fields.
     *
     * @param  list<array<string, mixed>>  $old
     * @param  list<array<string, mixed>>  $new
     * @return array<int, int> new index => old index
     */
    private function match(string $section, array $old, array $new): array
    {
        $byIdentity = [];

        foreach ($old as $oldIndex => $entry) {
            $byIdentity[$this->identity($section, $entry)][] = $oldIndex;
        }

        $oldFor = [];

        foreach ($new as $newIndex => $entry) {
            $key = $this->identity($section, $entry);

            if (! empty($byIdentity[$key])) {
                $oldFor[$newIndex] = array_shift($byIdentity[$key]);
            }
        }

        $matchedOld = array_flip($oldFor);
        $candidates = [];

        foreach ($new as $newIndex => $newEntry) {
            if (isset($oldFor[$newIndex])) {
                continue;
            }

            foreach ($old as $oldIndex => $oldEntry) {
                if (isset($matchedOld[$oldIndex])) {
                    continue;
                }

                $similarity = $this->entrySimilarity($section, $oldEntry, $newEntry);

                if ($similarity >= self::ENTRY_SIMILARITY) {
                    $candidates[] = [$similarity, $oldIndex, $newIndex];
                }
            }
        }

        usort($candidates, fn (array $a, array $b): int => [$b[0], $a[1], $a[2]] <=> [$a[0], $b[1], $b[2]]);

        foreach ($candidates as [, $oldIndex, $newIndex]) {
            if (! isset($oldFor[$newIndex]) && ! isset($matchedOld[$oldIndex])) {
                $oldFor[$newIndex] = $oldIndex;
                $matchedOld[$oldIndex] = $newIndex;
            }
        }

        ksort($oldFor);

        return $oldFor;
    }

    /**
     * @param  array<string, mixed>  $entry
     */
    private function identity(string $section, array $entry): string
    {
        $parts = match ($section) {
            'experience' => [$entry['company'], explode(' - ', $entry['dates'])[0]],
            'education' => [$entry['institution'], $entry['degree']],
            'projects' => [$entry['projectName']],
            'skills' => [$entry['group'], WordDiff::plain($entry['title'])],
            default => [],
        };

        return implode("\u{1F}", array_map(fn (string $part): string => mb_strtolower(trim($part)), $parts));
    }

    /**
     * The share of fields the two entries agree on, counting only fields
     * that are set on at least one side.
     *
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     */
    private function entrySimilarity(string $section, array $old, array $new): float
    {
        $compared = 0;
        $equal = 0;

        foreach (array_keys(self::SECTIONS[$section]['fields']) as $field) {
            if ($old[$field] === '' && $new[$field] === '') {
                continue;
            }

            $compared++;
            $equal += $old[$field] === $new[$field] ? 1 : 0;
        }

        foreach (['bullets', 'list'] as $field) {
            if (! isset($old[$field]) || ($old[$field] === [] && $new[$field] === [])) {
                continue;
            }

            $compared++;
            $equal += $this->sameItems($old[$field], $new[$field]) ? 1 : 0;
        }

        return $compared === 0 ? 1.0 : $equal / $compared;
    }

    /**
     * @param  list<string>  $old
     * @param  list<string>  $new
     */
    private function sameItems(array $old, array $new): bool
    {
        $key = fn (string $item): string => mb_strtolower(WordDiff::plain($item));
        $oldKeys = array_unique(array_map($key, $old));
        $newKeys = array_unique(array_map($key, $new));
        sort($oldKeys);
        sort($newKeys);

        return $oldKeys === $newKeys;
    }

    /**
     * The matched entries off the longest run that kept its relative order:
     * the fewest entries that explain the reordering.
     *
     * @param  array<int, int>  $oldFor  new index => old index, sorted by new index
     * @return array<int, true> new indexes of moved entries
     */
    private function movedNewIndexes(array $oldFor): array
    {
        $newIndexes = array_keys($oldFor);
        $sequence = array_values($oldFor);
        $count = count($sequence);

        if ($count < 2) {
            return [];
        }

        $length = array_fill(0, $count, 1);
        $previous = array_fill(0, $count, -1);

        for ($i = 1; $i < $count; $i++) {
            for ($j = 0; $j < $i; $j++) {
                if ($sequence[$j] < $sequence[$i] && $length[$i] < $length[$j] + 1) {
                    $length[$i] = $length[$j] + 1;
                    $previous[$i] = $j;
                }
            }
        }

        $kept = [];
        $at = array_keys($length, max($length))[0];

        while ($at !== -1) {
            $kept[$at] = true;
            $at = $previous[$at];
        }

        $moved = [];

        foreach ($newIndexes as $position => $newIndex) {
            if (! isset($kept[$position])) {
                $moved[$newIndex] = true;
            }
        }

        return $moved;
    }

    /**
     * An entry that exists on only one side, reported as one change.
     *
     * @param  array<string, mixed>  $entry
     */
    private function wholeEntry(string $section, string $status, array $entry): EntryDiff
    {
        $summary = $this->summary($section, $entry);
        $isAdded = $status === EntryDiff::ADDED;

        return new EntryDiff(
            status: $status,
            label: $this->label($section, $entry),
            before: $isAdded ? [] : $entry,
            after: $isAdded ? $entry : [],
            changes: [new FieldChange(
                field: '',
                label: '',
                kind: $isAdded ? FieldChange::ADDED : FieldChange::REMOVED,
                before: $isAdded ? null : $summary,
                after: $isAdded ? $summary : null,
            )],
            bullets: array_map(fn (string $bullet): array => ['status' => 'equal', 'text' => $bullet, 'tokens' => null], $entry['bullets'] ?? []),
            items: array_map(fn (string $item): array => ['status' => 'equal', 'text' => $item], $entry['list'] ?? []),
            group: $entry['group'] ?? null,
        );
    }

    /**
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     */
    private function compareEntries(string $section, array $old, array $new, bool $moved): EntryDiff
    {
        $definition = self::SECTIONS[$section];
        $changes = $moved ? [new FieldChange('', 'Position', FieldChange::MOVED)] : [];

        foreach ($definition['fields'] as $field => $label) {
            $change = $this->compareField(
                $field,
                $label,
                $old[$field],
                $new[$field],
                ! in_array($field, $definition['hidden'], true),
                ! in_array($field, $definition['plain'], true),
            );

            if ($change !== null) {
                $changes[] = $change;
            }
        }

        $bullets = [];

        if (array_key_exists('bullets', $new)) {
            [$bullets, $bulletChanges] = $this->compareBullets($old['bullets'], $new['bullets']);
            $changes = array_merge($changes, $bulletChanges);
        }

        $items = [];

        if (array_key_exists('list', $new)) {
            [$items, $itemChanges] = $this->compareItems($old['list'], $new['list']);
            $changes = array_merge($changes, $itemChanges);
        }

        $hasFieldChanges = array_filter($changes, fn (FieldChange $change): bool => $change->kind !== FieldChange::MOVED) !== [];

        return new EntryDiff(
            status: $hasFieldChanges ? EntryDiff::MODIFIED : EntryDiff::UNCHANGED,
            label: $this->label($section, $new),
            before: $old,
            after: $new,
            moved: $moved,
            changes: $changes,
            bullets: $bullets,
            items: $items,
            group: $new['group'] ?? null,
        );
    }

    private function compareField(string $field, string $label, string $before, string $after, bool $shown, bool $isText): ?FieldChange
    {
        if ($before === $after) {
            return null;
        }

        $display = fn (string $value): ?string => $value !== '' ? $value : ($field === 'isFreelance' ? 'No' : null);

        if ($isText && WordDiff::plain($before) === WordDiff::plain($after)) {
            return new FieldChange($field, $label, FieldChange::FORMATTING, $before, $after, $shown);
        }

        return new FieldChange(
            field: $field,
            label: $label,
            kind: FieldChange::MODIFIED,
            before: $display($before),
            after: $display($after),
            shown: $shown,
            tokens: $isText ? WordDiff::tokens($before, $after) : null,
        );
    }

    /**
     * Unchanged bullets anchor the alignment; inside each gap between them, a
     * removed and an added bullet that share enough words are one reworded
     * bullet.
     *
     * @param  list<string>  $old
     * @param  list<string>  $new
     * @return array{0: list<array{status: string, text: string, tokens: list<array{op: string, text: string}>|null}>, 1: list<FieldChange>}
     */
    private function compareBullets(array $old, array $new): array
    {
        $rows = [];
        $changes = [];
        $removed = [];
        $added = [];
        $oldNumber = 0;
        $newNumber = 0;

        $flush = function () use (&$rows, &$changes, &$removed, &$added): void {
            $pairs = $this->pairBullets(array_column($removed, 'text'), array_column($added, 'text'));
            $pairedRemoved = array_flip($pairs);

            foreach ($removed as $index => $bullet) {
                if (! isset($pairedRemoved[$index])) {
                    $rows[] = ['status' => 'removed', 'text' => $bullet['text'], 'tokens' => null];
                    $changes[] = new FieldChange("bullets.{$bullet['number']}", "Bullet {$bullet['number']}", FieldChange::REMOVED, before: $bullet['text']);
                }
            }

            foreach ($added as $index => $bullet) {
                if (isset($pairs[$index])) {
                    $before = $removed[$pairs[$index]]['text'];
                    $tokens = WordDiff::tokens($before, $bullet['text']);
                    $isFormatting = WordDiff::plain($before) === WordDiff::plain($bullet['text']);
                    $rows[] = ['status' => 'modified', 'text' => $bullet['text'], 'tokens' => $tokens];
                    $changes[] = new FieldChange(
                        "bullets.{$bullet['number']}",
                        "Bullet {$bullet['number']}",
                        $isFormatting ? FieldChange::FORMATTING : FieldChange::MODIFIED,
                        $before,
                        $bullet['text'],
                        tokens: $isFormatting ? null : $tokens,
                    );

                    continue;
                }

                $rows[] = ['status' => 'added', 'text' => $bullet['text'], 'tokens' => null];
                $changes[] = new FieldChange("bullets.{$bullet['number']}", "Bullet {$bullet['number']}", FieldChange::ADDED, after: $bullet['text']);
            }

            $removed = [];
            $added = [];
        };

        foreach ($this->alignLists($old, $new) as [$text, $op]) {
            if ($op === Differ::OLD) {
                $flush();
                $oldNumber++;
                $newNumber++;
                $rows[] = ['status' => 'equal', 'text' => $text, 'tokens' => null];
            } elseif ($op === Differ::REMOVED) {
                $removed[] = ['text' => $text, 'number' => ++$oldNumber];
            } else {
                $added[] = ['text' => $text, 'number' => ++$newNumber];
            }
        }

        $flush();

        return [$rows, $changes];
    }

    /**
     * @param  list<string>  $old
     * @param  list<string>  $new
     * @return list<array{0: string, 1: int}>
     */
    private function alignLists(array $old, array $new): array
    {
        if ($old === [] && $new === []) {
            return [];
        }

        return array_values(array_filter(
            (new Differ(new UnifiedDiffOutputBuilder))->diffToArray($old, $new),
            fn (array $row): bool => in_array($row[1], [Differ::OLD, Differ::ADDED, Differ::REMOVED], true),
        ));
    }

    /**
     * Greedy pairing by word-set similarity, best pairs first.
     *
     * @param  list<string>  $removed
     * @param  list<string>  $added
     * @return array<int, int> added index => removed index
     */
    private function pairBullets(array $removed, array $added): array
    {
        $removedWords = array_map($this->words(...), $removed);
        $addedWords = array_map($this->words(...), $added);
        $candidates = [];

        foreach ($removedWords as $r => $before) {
            foreach ($addedWords as $a => $after) {
                $union = count(array_unique(array_merge($before, $after)));
                $similarity = $union === 0 ? 1.0 : count(array_intersect($before, $after)) / $union;

                if ($similarity >= self::BULLET_SIMILARITY) {
                    $candidates[] = [$similarity, $r, $a];
                }
            }
        }

        usort($candidates, fn (array $x, array $y): int => [$y[0], $x[1], $x[2]] <=> [$x[0], $y[1], $y[2]]);

        $pairs = [];
        $usedRemoved = [];

        foreach ($candidates as [, $r, $a]) {
            if (! isset($pairs[$a]) && ! isset($usedRemoved[$r])) {
                $pairs[$a] = $r;
                $usedRemoved[$r] = true;
            }
        }

        return $pairs;
    }

    /**
     * A bullet's distinct words, lowercased, for similarity.
     *
     * @return list<string>
     */
    private function words(string $text): array
    {
        return array_values(array_unique(array_filter(
            array_map('mb_strtolower', WordDiff::split(WordDiff::plain($text))),
            fn (string $token): bool => preg_match('/[\p{L}\p{N}]/u', $token) === 1,
        )));
    }

    /**
     * Skills compared as a set, ignoring case; a change only in the order of
     * the skills both sides keep is one order change.
     *
     * @param  list<string>  $old
     * @param  list<string>  $new
     * @return array{0: list<array{status: string, text: string}>, 1: list<FieldChange>}
     */
    private function compareItems(array $old, array $new): array
    {
        $key = fn (string $item): string => mb_strtolower(WordDiff::plain($item));
        $oldKeys = array_map($key, $old);
        $newKeys = array_map($key, $new);
        $oldSet = array_flip($oldKeys);
        $newSet = array_flip($newKeys);

        $rows = [];
        $changes = [];
        $removedAfter = [];

        foreach ($old as $oldIndex => $item) {
            if (isset($newSet[$oldKeys[$oldIndex]])) {
                continue;
            }

            $anchor = null;

            for ($previous = $oldIndex - 1; $previous >= 0; $previous--) {
                if (isset($newSet[$oldKeys[$previous]])) {
                    $anchor = $oldKeys[$previous];
                    break;
                }
            }

            $removedAfter[$anchor ?? ''][] = $item;
        }

        $pushRemoved = function (string $anchor) use (&$rows, &$changes, &$removedAfter): void {
            foreach ($removedAfter[$anchor] ?? [] as $item) {
                $rows[] = ['status' => 'removed', 'text' => $item];
                $changes[] = new FieldChange('list', 'Skill', FieldChange::REMOVED, before: WordDiff::plain($item));
            }

            unset($removedAfter[$anchor]);
        };

        $pushRemoved('');

        foreach ($new as $newIndex => $item) {
            $isKept = isset($oldSet[$newKeys[$newIndex]]);
            $rows[] = ['status' => $isKept ? 'equal' : 'added', 'text' => $item];

            if (! $isKept) {
                $changes[] = new FieldChange('list', 'Skill', FieldChange::ADDED, after: WordDiff::plain($item));
            }

            $pushRemoved($newKeys[$newIndex]);
        }

        $keptInOldOrder = array_values(array_unique(array_filter($oldKeys, fn (string $item): bool => isset($newSet[$item]))));
        $keptInNewOrder = array_values(array_unique(array_filter($newKeys, fn (string $item): bool => isset($oldSet[$item]))));

        if ($keptInOldOrder !== $keptInNewOrder) {
            $changes[] = new FieldChange('list', 'Skill order', FieldChange::ORDER_CHANGED);
        }

        return [$rows, $changes];
    }

    /**
     * @param  array<string, mixed>  $entry
     */
    private function label(string $section, array $entry): string
    {
        $plain = fn (string $field): string => WordDiff::plain((string) ($entry[$field] ?? ''));
        $join = fn (string ...$parts): string => implode(' — ', array_filter($parts, fn (string $part): bool => $part !== ''));

        $label = match ($section) {
            'personal' => 'Personal info',
            'skills' => $plain('title'),
            'experience' => $join($plain('company'), $plain('jobTitle')),
            'education' => $join($plain('institution'), $plain('degree')),
            'projects' => $plain('projectName'),
            default => '',
        };

        return $label !== '' ? $label : 'Untitled';
    }

    /**
     * A one-line description of a whole entry, for an added or removed row.
     *
     * @param  array<string, mixed>  $entry
     */
    private function summary(string $section, array $entry): string
    {
        $parts = match ($section) {
            'experience' => [$entry['jobTitle'], $entry['company'], $entry['dates']],
            'education' => [$entry['institution'], trim($entry['degree'].' '.$entry['level']), $entry['dates']],
            'projects' => [$entry['projectName'], $entry['description']],
            'skills' => [$entry['title'].($entry['list'] !== [] ? ': '.implode(', ', $entry['list']) : '')],
            default => [],
        };

        $summary = implode(', ', array_filter(array_map(WordDiff::plain(...), $parts), fn (string $part): bool => $part !== ''));
        $bulletCount = count($entry['bullets'] ?? []);

        if ($bulletCount > 0) {
            $summary .= $bulletCount === 1 ? ' (1 bullet)' : " ({$bulletCount} bullets)";
        }

        return $summary;
    }
}
