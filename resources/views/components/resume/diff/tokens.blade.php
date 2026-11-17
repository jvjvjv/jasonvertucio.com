@props(['tokens', 'elide' => false])
{{--
    Word segments of one changed field, joined with no whitespace between them.
    Every segment is escaped, keeping existing entities as SafeInlineHtml does. `elide` shortens long unchanged stretches.
--}}
@php
    $marked = '';

    foreach ($elide ? \App\Services\Resume\Diff\WordDiff::elide($tokens) : $tokens as $segment) {
        $text = e($segment['text'], false);
        $marked .= match ($segment['op']) {
            'insert' => '<ins class="rd-ins">'.$text.'</ins>',
            'delete' => '<del class="rd-del">'.$text.'</del>',
            default => $text,
        };
    }
@endphp
{!! $marked !!}
