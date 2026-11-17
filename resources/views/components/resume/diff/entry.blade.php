@props(['entry'])
{{-- Wraps an entry of the marked-up résumé and says, in words, what happened to it. --}}
@php
    $tags = array_values(array_filter([
        $entry->isAdded() ? 'Added' : null,
        $entry->isRemoved() ? 'Removed' : null,
        $entry->moved ? 'Moved' : null,
    ]));
@endphp
<div {{ $attributes->class([
    'rd-entry',
    'rd-added' => $entry->isAdded(),
    'rd-removed' => $entry->isRemoved(),
    'rd-moved' => $entry->moved,
]) }}>
    @if($tags !== [])
    <p class="rd-tags">
        @foreach($tags as $tag)
        <span class="rd-tag rd-tag--{{ strtolower($tag) }}">{{ $tag }}</span>
        @endforeach
    </p>
    @endif
    {{ $slot }}
</div>
