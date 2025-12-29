@if($shouldDisplay())
    <div class="mb-4">
        <h2 class="mb-2">{{ $header() }}</h2>
        <span class="badge badge-primary mb-2" style="font-size: 1rem;">{{ $mediaType() }}</span>
        <h5 class="mb-2">{{ $title() }}</h5>
        @if($subtitle())
            <p class="mb-2"><small class="text-muted">{!! $subtitle() !!}</small></p>
        @endif
        <p class="text-muted mb-0">
            <small>{!! $timestampLabel() !!} {{ $timestamp() }}</small>
        </p>
    </div>
@endif
