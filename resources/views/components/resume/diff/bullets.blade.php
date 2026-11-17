@props(['entry', 'listClass'])
@if($entry->bullets !== [])
<ul class="{{ $listClass }}">
    @foreach($entry->bullets as $bullet)
    <li class="text-sm">
        @if($bullet['status'] === 'added')
        <ins class="rd-ins">{{ $bullet['text'] }}</ins>
        @elseif($bullet['status'] === 'removed')
        <del class="rd-del">{{ $bullet['text'] }}</del>
        @elseif($bullet['status'] === 'modified' && $bullet['tokens'] !== null)
        <x-resume.diff.tokens :tokens="$bullet['tokens']" />
        @else
        {{ $bullet['text'] }}
        @endif
    </li>
    @endforeach
</ul>
@endif
