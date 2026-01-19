<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="Jason Vertucio does mobile application development.">
    <meta name="author" content="Jason Vertucio">

    <meta name="twitter:card" value="summary">
    <meta name="twitter:title" value="Jason, who did a thing">
    <meta name="twitter:creator" value="@jasondidathing">
    <meta name="twitter:site" value="@jasondidathing">
    <meta name="twitter:image" value="https://bspdx.com/images/bspdx.png">

    <title>{{ env('APP_ENV', '') == 'dev' ? 'DEV:' : ''}}{{ $config['html_title'] }}</title>
    {{-- Fonts --}}
    <link href="https://fonts.googleapis.com/css?family=Saira+Extra+Condensed:500,700&display=swap" rel="stylesheet">
    <link href="https://db.onlinewebfonts.com/c/29dc27977e417a98e56556776f41607c?family=Corbel" rel="stylesheet">
    <link href="https://fonts.cdnfonts.com/css/convection" rel="stylesheet">
    {{-- Custom styles for this template --}}
    <link href="{{asset('css/app.css') }}" rel="stylesheet">
    {{--
    <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-0429292532295045"
        crossorigin="anonymous"></script> --}}
</head>

<body id="page-top" class="font-body text-dark bg-gray-50">
    <header>
        <h1 class="sr-only">{{ $config['aria_title'] }}</h1>

    </header>

    <x-navigation :config="$config" />

    <main class="p-0 md:ml-52 lg:ml-64">

        <section class="site-section" id="about">
            <div class="w-full">
                <h1 class="text-4xl sm:text-8xl uppercase tracking-tight mt-4 sm:mt-4 mb-0 font-bold" aria-hidden="true">
                    {{ $config['about_me']['name']['given'] }}
                    <span class="text-primary">{{ $config['about_me']['name']['sur'] }}</span>
                </h1>
                <div class="subheading font-family-heading text-base uppercase font-medium tracking-wide mb-6">
                    {{ $config['about_me']['address']['city'] }},
                    {{ $config['about_me']['address']['state'] }}
                    {{ $config['about_me']['address']['zip'] }}
                    &middot;
                    {{--
                        {{ $config['about_me']['phone'] }}
                        &middot;
                    --}}
                    @if($config['about_me']['telegram'])
                        <a class="text-primary underline hover:text-secondary transition-all duration-500"
                            href="{{ $config['about_me']['telegram']['url'] }}" target="_blank">
                            {{ $config['about_me']['telegram']['label'] }}
                        </a>
                    @else
                    <a class="text-primary hover:text-secondary"
                        href="mailto:{{ $config['about_me']['email']['email_address'] }}?subject={{ $config['about_me']['email']['subject'] }}&body={{ $config['about_me']['email']['body'] }}">
                        {{ $config['about_me']['email']['email_address'] }}
                    </a>
                    @endif
                </div>
                @foreach ($config['about_me']['sections'] as $section)
                    <p class="text-lg font-light mb-5">
                        {!! $section !!}
                    </p>
                @endforeach

                <div class="flex gap-4">
                    @foreach ($config['about_me']['social'] as $item)
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

        <hr class="m-0 border-0 border-t border-gray-200">

        @if ($blog && (env('APP_DEBUG') || $blog['published_at']->diffInDays() < 90))
        <section class="site-section" id="blog">
            <div class="w-full">
                <h2 class="3xl font-heading text-3xl uppercase mb-5 font-bold">Latest Blog</h2>
                <h3 class="font-heading text-xl mb-2 font-bold">{{ $blog['title'] }}</h3>
                @if ($blog['featured_image'])
                    <div>
                        <img src="{{ $blog['featured_image'] }}" class="w-full" alt="{{ $blog['featured_image_caption'] }}">
                        @if ($blog['featured_image_caption'])
                            <p class="text-center">{!! $blog['featured_image_caption'] !!}</p>
                        @endif
                    </div>
                @endif
                @if ($blog['summary'])
                    <p>{{ $blog['summary'] }}</p>
                @else
                    <p>(I am supposed to enter a sort of flavor text on these things but I didn't for this one. Oh
                        well.)</p>
                @endif
                <p>
                    {{ $blog['published_at']->diffForHumans() }}
                </p>
                <a class="my-2 inline-block font-semibold text-center whitespace-nowrap align-middle select-none border bg-white border-primary px-4 py-3 text-lg leading-6 rounded transition-color text-primary hover:bg-primary hover:text-white focus:bg-primary focus:text-white transition-all duration-300"
                    href="/blog/{{ $blog['slug'] }}">Read</a>
                @ifcanvasauthenticated
                <a class="my-2 inline-block font-semibold text-center whitespace-nowrap align-middle select-none border bg-white border-primary px-4 py-3 text-lg leading-6 rounded transition-color text-primary focus:bg-primary focus:text-white hover:bg-primary hover:text-white ml-2 transition-all duration-300"
                    href="/{{ config('canvas.path') }}/posts/{{ $blog['id'] }}/edit">Edit</a>
                @endifcanvasauthenticated
            </div>
        </section>

        <hr class="m-0 border-0 border-t border-gray-200">
        @endif

        <section class="site-section" id="projects">
            <div class="w-full">
                <h2 class="3xl text-3xl uppercase mb-5 font-bold">Projects</h2>
                <p>What you see on the left are a few personal projects I've made for work purposes, or for personal
                    purposes.
                    I have a few more projects that are not listed here, but if you want to see them, please reach out
                    to me.
                    I am always open to new projects, and I am always looking for new opportunities to learn and grow.
                </p>
                @foreach ($config['projects'] as $project)
                    <x-project :project="$project" />
                @endforeach
            </div>
        </section>

        <x-skills :icons="$config['icons']" :workflow="$config['workflow']" />

        <hr class="m-0 border-0 border-t border-gray-200">

        {{-- <section class="site-section" id="experience">
            <div class="w-full">
                <h2 class="3xl font-heading text-3xl uppercase mb-5 font-bold">Experience</h2>
                <p>
                    A selected portion of experience is shown below. For more information, you can reach out to me
                    directly.
                </p>
                @foreach ($config['experience'] as $job)
                    <x-job :job="$job" />
                @endforeach
            </div>
        </section> --}}

        <hr class="m-0 border-0 border-t border-gray-200">

        <section class="site-section" id="interests">
            <div class="w-full">
                <h2 class="3xl font-heading text-3xl uppercase mb-5 font-bold">Interests</h2>
                @foreach($config['interests'] as $interest)
                    <p>{!! $interest !!}</p>
                @endforeach
                @if($btc)
                    <p>Oh. And as of {{ $btc->time->updateduk}}, the price of BTC is:</p>
                    <ul>
                        @foreach($btc->bpi as $item)
                            <li>
                                <span class="font-mono"><strong>{{ $item->code }}:</strong></span>
                                <span class="font-mono">{!! $item->symbol !!}{{ $item->rate_float }}</span>
                            </li>
                        @endforeach
                    </ul>
                    <p><small>Powered by <a href="https://www.coindesk.com/price/bitcoin" target="_blank">Coindesk</a>.
                            {{ $btc->disclaimer }}</small></p>
                @endif
            </div>
        </section>

        <section class="site-section md:!hidden" id="image">
            <img class="my-8 w-full h-full rounded-full border-4 border-white/20 mx-auto" src="{{ asset('img/jv.png') }}"
                alt="Jason Vertucio">
        </section>

    </main>

    <!-- Custom scripts for this template -->
    @include('cookies')
    <script cookie-consent="functionality" src="{{ asset('js/app.js') }}"></script>
    @include('gtag')

</body>

</html>
