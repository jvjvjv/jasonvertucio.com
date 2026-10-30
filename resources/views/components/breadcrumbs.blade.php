@props(['items'])
<nav aria-label="Breadcrumb" {{ $attributes->merge(['class' => 'mx-auto mb-4 max-w-7xl px-4 text-sm']) }}>
    <ol class="flex flex-wrap items-center gap-x-1.5 gap-y-1">
        @foreach ($items as $item)
            <li class="flex min-w-0 items-start gap-x-1.5">
                @unless ($loop->first)
                    <svg class="mt-1 h-3 w-3 shrink-0 text-gray-400"
                         fill="none"
                         stroke="currentColor"
                         viewBox="0 0 24 24"
                         aria-hidden="true">
                        <path stroke-linecap="round"
                              stroke-linejoin="round"
                              stroke-width="2"
                              d="M9 5l7 7-7 7"></path>
                    </svg>
                @endunless
                @if ($loop->last)
                    <span class="min-w-0 wrap-anywhere text-gray-600" aria-current="page">{{ $item['label'] }}</span>
                @else
                    <a class="rounded-sm text-primary hover:text-secondary hover:underline focus-visible:outline-2 focus-visible:outline-primary focus-visible:outline-offset-2"
                       href="{{ $item['href'] }}">{{ $item['label'] }}</a>
                @endif
            </li>
        @endforeach
    </ol>
</nav>
