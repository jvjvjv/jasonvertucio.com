@props(['entry', 'field', 'html' => false])
{{--
    One field of a compared entry: its word changes when it was edited,
    otherwise its value as the résumé shows it. `html` fields are the ones the
    résumé prints through SafeInlineHtml.
--}}
@php
    $change = $entry->status === 'modified' ? $entry->field($field) : null;
@endphp
@if($change?->tokens !== null)
<x-resume.diff.tokens :tokens="$change->tokens" />
@elseif($html)
{!! \App\Support\SafeInlineHtml::clean($entry->value($field)) !!}
@else
{{ $entry->value($field) }}
@endif
