<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Reduces Markdown to the words a speech engine should say. Rendering the
 * Markdown first and dropping the tags removes every markup character the
 * same way a reader never sees them, rather than guessing at the syntax.
 */
class SpeakableText
{
    public static function fromMarkdown(string $markdown): string
    {
        $html = Str::markdown($markdown, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);

        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }
}
