<?php

namespace App\Services\Resume\Diff;

use SebastianBergmann\Diff\Differ;
use SebastianBergmann\Diff\Output\UnifiedDiffOutputBuilder;

/**
 * A word-level diff of two strings, for highlighting what a draft changed
 * inside one field. Words, whitespace runs and punctuation are separate
 * tokens; the inline tags résumé text may carry (`b`, `strong`, `i`, `em`,
 * `br`) are stripped first, so a formatting-only change produces no tokens.
 */
final class WordDiff
{
    /** Above this many tokens on either side, a field is shown as replaced whole. */
    public const int MAX_TOKENS = 2000;

    public const string EQUAL = 'equal';

    public const string INSERT = 'insert';

    public const string DELETE = 'delete';

    /**
     * The segments that turn `$before` into `$after`, adjacent segments with
     * the same op merged.
     *
     * @return list<array{op: string, text: string}>
     */
    public static function tokens(string $before, string $after): array
    {
        $before = self::plain($before);
        $after = self::plain($after);

        if ($before === $after) {
            return $before === '' ? [] : [['op' => self::EQUAL, 'text' => $before]];
        }

        $from = self::split($before);
        $to = self::split($after);

        if (count($from) > self::MAX_TOKENS || count($to) > self::MAX_TOKENS) {
            return self::replacement($before, $after);
        }

        $segments = [];

        foreach ((new Differ(new UnifiedDiffOutputBuilder))->diffToArray($from, $to) as [$token, $op]) {
            $mapped = match ($op) {
                Differ::OLD => self::EQUAL,
                Differ::ADDED => self::INSERT,
                Differ::REMOVED => self::DELETE,
                default => null,
            };

            if ($mapped !== null) {
                self::append($segments, $mapped, (string) $token);
            }
        }

        return $segments;
    }

    /**
     * The text a reader sees: allowed inline tags removed, `<br>` as a space,
     * line endings normalised, trimmed.
     */
    public static function plain(string $text): string
    {
        $text = preg_replace('/<br\b[^>]*>/i', ' ', $text) ?? $text;
        $text = preg_replace('/<\/?(?:b|strong|i|em)\b[^>]*>/i', '', $text) ?? $text;

        return trim(str_replace(["\r\n", "\r"], "\n", $text));
    }

    /**
     * Words (letters and digits, with inner apostrophes), whitespace runs and
     * single punctuation characters, in order. Concatenated they give back
     * the input.
     *
     * @return list<string>
     */
    public static function split(string $text): array
    {
        if ($text === '') {
            return [];
        }

        if (preg_match_all("/\\s+|[\\p{L}\\p{N}]+(?:['\u{2019}][\\p{L}\\p{N}]+)*|[^\\s\\p{L}\\p{N}]/u", $text, $matches) !== false) {
            return $matches[0];
        }

        return preg_split('/(\s+)/', $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [$text];
    }

    /**
     * The segments with long unchanged stretches shortened to the words
     * nearest a change, for a list of changes rather than a whole résumé.
     * A stretch keeps `$context` words on each side that touches a change.
     *
     * @param  list<array{op: string, text: string}>  $segments
     * @return list<array{op: string, text: string}>
     */
    public static function elide(array $segments, int $context = 10): array
    {
        $last = array_key_last($segments);

        foreach ($segments as $index => $segment) {
            if ($segment['op'] !== self::EQUAL) {
                continue;
            }

            preg_match('/^(\s*)(.*?)(\s*)$/su', $segment['text'], $match);
            $words = preg_split('/\s+/u', $match[2] ?? '', -1, PREG_SPLIT_NO_EMPTY) ?: [];
            $keepBefore = $index > 0 ? $context : 0;
            $keepAfter = $index < $last ? $context : 0;

            if (count($words) <= $keepBefore + $keepAfter + 4) {
                continue;
            }

            $head = implode(' ', array_slice($words, 0, $keepBefore));
            $tail = $keepAfter > 0 ? implode(' ', array_slice($words, -$keepAfter)) : '';

            $segments[$index]['text'] = ($match[1] ?? '').match (true) {
                $head === '' => '… '.$tail,
                $tail === '' => $head.' …',
                default => $head.' … '.$tail,
            }.($match[3] ?? '');
        }

        return $segments;
    }

    /**
     * @return list<array{op: string, text: string}>
     */
    private static function replacement(string $before, string $after): array
    {
        return array_values(array_filter([
            $before !== '' ? ['op' => self::DELETE, 'text' => $before] : null,
            $after !== '' ? ['op' => self::INSERT, 'text' => $after] : null,
        ]));
    }

    /**
     * @param  list<array{op: string, text: string}>  $segments
     */
    private static function append(array &$segments, string $op, string $text): void
    {
        $last = array_key_last($segments);

        if ($last !== null && $segments[$last]['op'] === $op) {
            $segments[$last]['text'] .= $text;

            return;
        }

        $segments[] = ['op' => $op, 'text' => $text];
    }
}
