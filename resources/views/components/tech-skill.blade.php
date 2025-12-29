<li class="inline-block text-5xl w-14">
    @if (isset($icon['icon']))
        <i class="{{ $icon['iconType'] }} fa-{{$icon['icon'] }}" title="{{ $type }}: {{ $icon['label'] }}"></i>
    @elseif (isset($icon['text']))
        <span title="{{ $type }}">{{ $icon['text'] }}</span>
    @endif
</li>
