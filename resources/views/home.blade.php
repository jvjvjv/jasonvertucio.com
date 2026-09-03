<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    @include("partials.favicon")
    <meta name="description" content="Jason Vertucio does mobile application development.">
    <meta name="author" content="Jason Vertucio">

    <meta name="twitter:card" value="summary">
    <meta name="twitter:title" value="Jason, who did a thing">
    <meta name="twitter:creator" value="@jasondidathing">
    <meta name="twitter:site" value="@jasondidathing">
    <meta name="twitter:image" value="https://bspdx.com/images/bspdx.png">

    <title>{{ env('APP_ENV', '') == 'dev' ? 'DEV:' : ''}}{{ $config['html_title'] }}</title>
    {{-- Preconnect to font CDNs for faster loading --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    {{-- Fonts --}}
    <link href="https://fonts.googleapis.com/css2?family=Josefin+Sans:wght@400;500;700&family=Montserrat:wght@400;500;700&display=swap" rel="stylesheet">
    {{-- Custom styles for this template --}}
    @vite(['resources/css/app.css'])
    {{--
    <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-0429292532295045"
        crossorigin="anonymous"></script>
    --}}
    <noscript>
        <style>.fonts-loading { opacity: 1 !important; }</style>
    </noscript>
</head>
<body id="page-top" class="fonts-loading font-body text-dark bg-gray-50">

    <button
        type="button"
        class="sr-skip-to-content"
        aria-controls="main-content"
        onclick="document.getElementById('main-content')?.focus()"
    >Skip to content</button>

    <header>
        <h1 class="sr-only">{{ $config['aria_title'] }}</h1>
    </header>

    <x-navigation :links="$config['links']" />

    <main id="main-content" tabindex="-1" class="p-0 md:ml-52 lg:ml-64">

        <x-about :about-me="$config['about_me']" :title="$resumeData['personal']['title'] ?? ''" :summary="$resumeData['personal']['summary'] ?? ''" />

        <x-latest-blog :post="$blog" />

        <hr class="m-0 border-0 border-t border-dark/50">

        <x-projects :projects="$resumeData['projects']" />

        <x-skills :skills="$resumeData['skills']" />

        {{-- <x-experience :experience="$config['experience']" /> --}}

        <hr class="m-0 border-0 border-t border-dark/50">

        <x-interests :interests="$config['interests']" :btc="$btc" />

        <section class="site-section md:hidden!" id="image">
            <img class="my-8 w-full h-full rounded-full border-4 border-white/20 mx-auto" src="{{ asset('img/jv.png') }}"
                alt="Jason Vertucio">
        </section>

    </main>

    {{-- Vite assets --}}
    @vite(['resources/js/font-loader.js', 'resources/js/app.js', 'resources/js/home.js'])

    {{-- Font loading progress --}}
    <script>
        if (typeof initFontLoader === 'function') {
            initFontLoader();
        }
    </script>

    <button id="back-to-top"
        type="button"
        aria-label="Back to top"
        onclick="window.scrollTo({top: 0, behavior: 'smooth'})"
        class="fixed bottom-4 left-1/2 -translate-x-1/2 z-50 flex h-6 w-6 items-center justify-center rounded-full bg-primary text-white opacity-0 transition-opacity duration-300 pointer-events-none">
        <i class="fa-solid fa-chevron-up text-xs"></i>
    </button>

    @include('cookies')
    @include('gtag')

</body>

</html>
