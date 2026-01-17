@if (isset($icon['icon']))
    <li class="inline-block text-5xl w-14" aria-label="{{ $type }}: {{ $icon['label'] }}">
        <i class="{{ $icon['iconType'] }} fa-{{$icon['icon'] }}" title="{{ $type }}: {{ $icon['label'] }}"
            aria-hidden="true"></i>
        <span class="sr-only">{{ $type }}: {{ $icon['label'] }}</span>
    </li>
@elseif (isset($icon['text']))
    <li class="inline-block text-5xl w-14">
        <span title="{{ $type }}">{{ $icon['text'] }}</span>
    </li>
@endif
