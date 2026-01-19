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
        crossorigin="anonymous"></script>
    --}}
</head>

<body id="page-top" class="font-body text-dark bg-gray-50">
    <header>
        <h1 class="sr-only">{{ $config['aria_title'] }}</h1>

    </header>

    <x-navigation :links="$config['links']" />

    <main class="p-0 md:ml-52 lg:ml-64">

        <x-about :about-me="$config['about_me']" />

        <x-latest-blog :post="$blog" />

        <hr class="m-0 border-0 border-t border-dark/50">

        <x-projects :projects="$config['projects']" />

        <x-skills :icons="$config['icons']" :workflow="$config['workflow']" />

        {{-- <x-experience :experience="$config['experience']" /> --}}

        <hr class="m-0 border-0 border-t border-dark/50">

        <x-interests :interests="$config['interests']" :btc="$btc" />

        <section class="site-section md:hidden!" id="image">
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
