@if($shouldDisplay())
    <div class="mb-4">
        <h2 class="text-3xl uppercase mb-2 font-bold">{{ $header() }}</h2>
        <span
            class="inline-block px-2 py-1 text-base font-bold leading-none text-center whitespace-nowrap align-baseline rounded bg-primary text-white mb-2"
            style="font-size: 1rem;">{{ $mediaType() }}</span>
        <div class="text-lg mb-2">{{ $title() }}</div>
        @if($subtitle())
            <p class="mb-2"><small class="text-gray-600"><em>{!! $subtitle() !!}</em></small></p>
        @endif
        @if($description())
            <p class="mb-2">{{ $description() }}</p>
        @endif
        <p class="text-gray-600 mb-0">
            <small>{!! $timestampLabel() !!} {{ $timestamp() }}</small>
        </p>
    </div>
@endif
