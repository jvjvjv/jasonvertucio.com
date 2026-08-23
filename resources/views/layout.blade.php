@php
    if (!isset($links)) {
        $links = [];
    }

    $meta = [
        "viewport" => "width=device-width, initial-scale=1, shrink-to-fit=no",
        "csrf-token" => csrf_token(),
        "author" => "Jason Vertucio",
        "description" => "Jason Vertucio does mobile application development.",
        "og:title" => "Jason, who did a thing",
        "og:url" => Request::url(),
        "og:image" => "https://beepbeepritchiellc.com/HDTV.png",
        "twitter:card" => "summary",
        "twitter:title" => "Jason, who did a thing",
        "twitter:image" => "https://beepbeepritchiellc.com/HDTV.png",
        "twitter:creator" => "@jasondidathing",
        "twitter:site" => "@jasondidathing",
    ];
    if (isset($post) && isset($post["meta"])) {
        $twitter_image =
            $post["meta"]["twitter_image"] ?? ($post["featured_image"] ? url($post["featured_image"]) : null);
        $opengraph_image =
            $post["meta"]["opengraph_image"] ?? ($post["featured_image"] ? url($post["featured_image"]) : null);
        $meta["description"] = $post["meta"]["meta_description"] ?? $meta["description"];
        $meta["og:title"] = $post["meta"]["opengraph_title"] ?? $post["title"];
        $meta["og:image"] = $opengraph_image;
        $meta["og:description"] = $post["meta"]["opengraph_description"] ?? ($post["excerpt"] ?? $meta["description"]);
        $meta["twitter:title"] = $post["meta"]["twitter_title"] ?? $post["title"];
        $meta["twitter:image"] = $twitter_image;
        $meta["twitter:description"] =
            $post["meta"]["twitter_description"] ?? ($post["excerpt"] ?? $meta["description"]);
    }
@endphp
<!DOCTYPE html>
<html lang="en" class="h-full">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    @include("partials.favicon")
    @foreach ($meta as $k => $v)
        @if ($k && $v)
            <meta name="{{ $k }}" content="{{ $v }}" />
        @endif
    @endforeach
    @yield("meta")

    @php
        $siteTitle = "Jason Vertucio";
        $pageTitle = trim($__env->yieldContent("title"));

        if ($pageTitle === "") {
            $routeName = request()->route()?->getName();
            $pageTitle = $routeName ? Str::headline(str_replace([".", "-"], " ", $routeName)) : "Home";
        }

        $fullTitle = $pageTitle === $siteTitle ? $siteTitle : "{$pageTitle} | {$siteTitle}";
    @endphp

    <title>{{ $fullTitle }}</title>
    {{-- Preconnect to font CDNs for faster loading --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect"
          href="https://fonts.gstatic.com"
          crossorigin>
    {{-- Fonts --}}
    <link href="https://fonts.googleapis.com/css2?family=Josefin+Sans:wght@400;500;700&family=Montserrat:wght@400;500;700&display=swap" rel="stylesheet">
    {{-- Custom styles for this template --}}
    @vite(["resources/css/blog.css"])
    @stack("styles")
    {{--
    <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-0429292532295045"
        crossorigin="anonymous"></script> --}}
    <noscript>
        <style>
            .fonts-loading {
                opacity: 1 !important;
            }
        </style>
    </noscript>
</head>

<body id="page-top" class="flex min-h-screen flex-col bg-gray-50 font-body">
    <div id="fb-root"></div>
    <script async
            defer
            crossorigin="anonymous"
            src="https://connect.facebook.net/en_GB/sdk.js#xfbml=1&version=v9.0&appId=695473097788503&autoLogAppEvents=1"
            nonce="{{ Str::random(8) }}"></script>

    <x-top-navbar class="sticky top-0 z-50" />

    <div id="main" class="fonts-loading grow">
        <div class="mx-0 my-4 p-0">
            @yield("main")
        </div>
    </div>

    <footer class="mt-auto bg-secondary py-3 text-white">
        <div class="mx-auto max-w-7xl px-4">
            <div class="text-right">
                <span>Copyright &copy; {{ date("Y") }}, Jason Vertucio.</span>
            </div>
        </div>
    </footer>

    {{-- Vite assets --}}
    @vite(["resources/js/font-loader.js", "resources/js/app.js"])

    {{-- Font loading progress --}}
    <script>
        if (typeof initFontLoader === 'function') {
            initFontLoader('#main');
        }
    </script>

    @include("gtag")
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    @stack("scripts")
</body>

</html>
