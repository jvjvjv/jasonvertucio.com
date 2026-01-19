@if (isset($icon['icon']))
    <li class="inline-block text-5xl w-14" aria-label="{{ $icon['label'] }}">
        <i class="text-dark {{ $icon['iconType'] }} fa-{{$icon['icon'] }} {{ isset($icon['animation']) ? "fa-hover-${icon['animation']}" : "" }}"
            title="{{ $icon['label'] }}"
            aria-hidden="true"></i>
    </li>
@elseif (isset($icon['text']))
    <li class="inline-block text-5xl w-14">
        <span>{{ $icon['text'] }}</span>
    </li>
@endif
