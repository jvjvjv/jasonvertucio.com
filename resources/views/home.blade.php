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
    <meta name="twitter:image" value="https://beepbeepritchiellc.com/HDTV.png">

    <title>Jason Vertucio</title>

    <!-- Custom styles for this template -->
    <link href="{{asset('css/splash.css') }}" rel="stylesheet">

    <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-0429292532295045"
        crossorigin="anonymous"></script>

</head>

<body id="page-top">

    <x-navigation :config="$config" />

    <div class="container-fluid p-0">

        <section class="resume-section p-3 p-lg-5 d-flex align-items-center" id="about">
            <div class="w-100">
                <h1 class="mb-0">{{ $config['about_me']['name']['given'] }}
                    <span class="text-primary">{{ $config['about_me']['name']['sur'] }}</span>
                </h1>
                <div class="subheading mb-5">
                    {{ $config['about_me']['address']['city'] }},
                    {{ $config['about_me']['address']['state'] }}
                    {{ $config['about_me']['address']['zip'] }}
                    ·
                    {{ $config['about_me']['phone'] }}
                    ·
                    <a
                        href="mailto:{{ $config['about_me']['email']['email_address'] }}?subject={{ $config['about_me']['email']['subject'] }}&body={{ $config['about_me']['email']['body'] }}">
                        {{ $config['about_me']['email']['email_address'] }}
                    </a>
                </div>
                @foreach ($config['about_me']['sections'] as $section)
                    <p class="lead mb-5">
                        {!! $section !!}
                    </p>
                @endforeach

                <x-currently-watching />

                <div class="social-icons">
                    @foreach ($config['about_me']['social'] as $item)
                        <a href="{{ $item['link'] }}" target="_blank">
                            <i class="fa-brands {{ $item['fa_icon'] }}"></i>
                        </a>
                    @endforeach
                </div>
            </div>
        </section>

        <hr class="m-0">

        @if ($blog)
        <section class="resume-section p-3 p-lg-5 d-flex align-items-center" id="blog">
            <div class="w-100">
                <h2 class="mb-5">Latest Blog</h2>
                <h4>{{ $blog['title'] }}</h4>
                @if ($blog['featured_image'])
                    <div class="image">
                        <img src="{{ $blog['featured_image'] }}" style="width: 100%;">
                        @if ($blog['featured_image_caption'])
                            <p class="text-center">{!! $blog['featured_image_caption'] !!}</p>
                        @endif
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
                    <a class="btn btn-outline-secondary" href="/blog/{{ $blog['slug'] }}">Read</a>
                    @ifcanvasauthenticated
                    <a class="btn btn-outline-primary"
                        href="/{{ config('canvas.path') }}/posts/{{ $blog['id'] }}/edit">Edit</a>
                    @endifcanvasauthenticated
                </div>
        </section>

        <hr class="m-0">
        @endif

        <section class="resume-section p-3 p-lg-5 d-flex align-items-center" id="skills">
            <div class="w-100">
                <h2 class="mb-5">Skills</h2>

                <div class="subheading mb-3">Programming Languages &amp; Tools</div>
                <ul class="list-inline dev-icons">
                    @foreach ($config['icons']['lang'] as $icon)
                        <x-tech-skill type="Language" :icon="$icon" />
                    @endforeach
                </ul>

                <ul class="list-inline dev-icons">
                    @foreach ($config['icons']['framework'] as $icon)
                        <x-tech-skill type="Framework" :icon="$icon" />
                    @endforeach
                </ul>
                {{--
                <ul class="list-inline dev-icons">
                    @foreach ($config['icons']['browser'] as $icon)
                    <li class="list-inline-item"><i class="fa-regular fa-{{$icon['icon'] }}" data-toggle="tooltip"
                            data-placement="right" title="{{ $icon['label'] }}"></i></li>
                    @endforeach
                </ul>
                --}}
                <ul class="list-inline dev-icons">
                    @foreach ($config['icons']['api'] as $icon)
                        <x-tech-skill type="API" :icon="$icon" />
                    @endforeach
                </ul>

                <ul class="list-inline dev-icons">
                    @foreach ($config['icons']['tech'] as $icon)
                        <x-tech-skill type="Tech" :icon="$icon" />
                    @endforeach
                </ul>

                <ul class="list-inline dev-icons">
                    @foreach ($config['icons']['source_control'] as $icon)
                        <x-tech-skill type="SCM/CI" :icon="$icon" />
                    @endforeach
                </ul>

                <ul class="list-inline dev-icons">
                    @foreach ($config['icons']['workflow'] as $icon)
                        <x-tech-skill type="Workflow" :icon="$icon" />
                    @endforeach
                </ul>

                <div class="subheading mb-3">Workflow</div>
                <ul class="fa-ul mb-0">
                    @foreach ($config['workflow'] as $line)
                        <li><i class="fa-li fa fa-check"></i> {!! $line !!}</li>
                    @endforeach
                </ul>
            </div>
        </section>

        <hr class="m-0">

        <section class="resume-section p-3 p-lg-5 d-flex align-items-center" id="interests">
            <div class="w-100">
                <h2 class="mb-5">Interests</h2>
                @foreach($config['interests'] as $interest)
                    <p>{!! $interest !!}</p>
                @endforeach
                @if($btc)
                    <p>Oh. And as of {{ $btc->time->updateduk}}, the price of BTC is:</p>
                    <ul>
                        @foreach($btc->bpi as $item)
                            <li>
                                <span style="font-family: ui-monospace;"><strong>{{ $item->code }}:</strong></span>
                                {{-- @currency($item->symbol, $item->rate_float) --}}
                                <span style="font-family: monospace;">{!! $item->symbol !!}{{ $item->rate_float }}</span>
                            </li>
                        @endforeach
                    </ul>
                    <p><small>Powered by <a href="https://www.coindesk.com/price/bitcoin" target="_blank">Coindesk</a>.
                            {{ $btc->disclaimer }}</small></p>
                    {{-- @json($btc, JSON_PRETTY_PRINT) --}}
                @endif
            </div>
        </section>

    </div>

    <!-- Custom scripts for this template -->
    @include('cookies')
    <script cookie-consent="functionality" src="{{ asset('js/app.js') }}"></script>
    @include('gtag')

</body>

</html>
