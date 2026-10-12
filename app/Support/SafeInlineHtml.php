<?php

namespace App\Support;

class SafeInlineHtml
{
    private const ALLOWED_TAGS = '<b><strong><i><em><br>';

    /**
     * Text that may be printed unescaped: only bare `b`, `strong`, `i`, `em`
     * and `br` tags survive.
     *
     * strip_tags() alone is not enough — it keeps every attribute on a tag it
     * allows, so `<b onclick=…>` would pass. Attributes are removed from the
     * allowed tags, then anything still unrecognisable is escaped, so a
     * malformed tag degrades to visible text instead of reaching the browser
     * as markup. Existing entities such as `&amp;` are preserved.
     */
    public static function clean(mixed $text): string
    {
        if (! is_string($text) || $text === '') {
            return '';
        }

        $stripped = strip_tags($text, self::ALLOWED_TAGS);
        $bare = preg_replace('/<(\/?)(b|strong|i|em)\b[^>]*>/i', '<$1$2>', $stripped) ?? '';
        $bare = preg_replace('/<br\b[^>]*>/i', '<br>', $bare) ?? '';
        $escaped = htmlspecialchars($bare, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false);

        return preg_replace_callback(
            '/&lt;(\/?)(b|strong|i|em)&gt;|&lt;br&gt;/i',
            static fn (array $match): string => strtolower($match[0] === '&lt;br&gt;' ? '<br>' : '<'.$match[1].$match[2].'>'),
            $escaped,
        ) ?? '';
    }

    /**
     * A skill category's list as one line, each item cleaned.
     *
     * @param  array<mixed>  $items
     */
    public static function joinList(array $items, string $separator = ' &middot; '): string
    {
        return implode($separator, array_map(self::clean(...), $items));
    }
}
