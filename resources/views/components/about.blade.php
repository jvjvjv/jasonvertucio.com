@props(['aboutMe', 'summary' => null])

<section class="site-section" id="about">
    <div class="w-full">
        <h1 class="text-4xl sm:text-8xl uppercase tracking-tight mt-4 sm:mt-4 mb-0 font-bold" aria-hidden="true">
            {{ $aboutMe['name']['given'] }}
            <span class="text-primary">{{ $aboutMe['name']['sur'] }}</span>
        </h1>
        <div class="subheading font-family-heading text-base uppercase font-medium tracking-wide mb-6">
            {{ $aboutMe['address']['city'] }},
            {{ $aboutMe['address']['state'] }}
            {{ $aboutMe['address']['zip'] }}
            &middot;
            @if($aboutMe['telegram'])
                <a class="text-primary underline hover:text-secondary transition-all duration-500"
                    href="{{ $aboutMe['telegram']['url'] }}" target="_blank">
                    {{ $aboutMe['telegram']['label'] }}
                </a>
            @else
            <a class="text-primary hover:text-secondary"
                href="mailto:{{ $aboutMe['email']['email_address'] }}?subject={{ $aboutMe['email']['subject'] }}&body={{ $aboutMe['email']['body'] }}">
                {{ $aboutMe['email']['email_address'] }}
            </a>
            @endif
        </div>
        @if($summary)
            <p class="text-lg font-light mb-5">{!! $summary !!}</p>
        @else
            @foreach ($aboutMe['sections'] as $section)
                <p class="text-lg font-light mb-5">
                    {!! $section !!}
                </p>
            @endforeach
        @endif

        <div class="flex gap-4">
            @foreach ($aboutMe['social'] as $item)
                <a href="{{ $item['link'] }}" target="_blank" role="link" rel="noopener noreferrer"
                    class="3xl inline-flex items-center justify-center w-14 h-14 text-3xl text-white bg-dark rounded-full hover:rounded-md hover:bg-gray-50 hover:border-2 hover:border-primary hover:text-primary focus:bg-gray-50 focus:border-2 focus:border-primary focus:text-primary transition-colors"
                    title="{{ $item['label'] }}">
                    <i class="fa-brands {{ $item['fa_icon'] }}"></i>
                </a>
            @endforeach
        </div>
        <div class="self-start mt-8">
            <x-currently-watching />
        </div>
    </div>
</section>
