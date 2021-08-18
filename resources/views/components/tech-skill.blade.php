<li class="list-inline-item">
    @if (isset($icon['icon']))
        <i class="fab fa-{{$icon['icon'] }}" data-toggle="tooltip" data-placement="right" title="{{ $type }}: {{ $icon['label'] }}"></i>
    @elseif (isset($icon['text']))
        ({{ $icon['text'] }})
    @endif
</li>