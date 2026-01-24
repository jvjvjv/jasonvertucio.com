@php
if (!isset($links)) {
    $links = [];
}

$config = json_decode(file_get_contents(resource_path() . "/config/config.json"), true);
$places = collect([]);
collect($config['links'])->each(function ($p) use ($places) {
    if (count($places) > 0 && $p['href'] == '/blog') {
        $places->push('<div class="border-t border-gray-200"></div>');
        return;
    }
    if ($p['href'] != '/blog') {
        $places->push($p);
        return;
    }
});

$meta = [
    'viewport' => 'width=device-width, initial-scale=1, shrink-to-fit=no',
    'csrf-token' => csrf_token(),
    'author' => 'Jason Vertucio',
    'description' => 'Jason Vertucio does mobile application development.',
    'og:title' => 'Jason, who did a thing',
    'og:url' => Request::url(),
    'og:image' => 'https://beepbeepritchiellc.com/HDTV.png',
    'twitter:card' => 'summary',
    'twitter:title' => 'Jason, who did a thing',
    'twitter:image' => 'https://beepbeepritchiellc.com/HDTV.png',
    'twitter:creator' => '@jasondidathing',
    'twitter:site' => '@jasondidathing',
];
if (isset($post) && isset($post['meta'])) {
    $twitter_image = $post['meta']['twitter_image'] ?? ($post['featured_image'] ? url($post['featured_image']) : null);
    $opengraph_image = $post['meta']['opengraph_image'] ?? ($post['featured_image'] ? url($post['featured_image']) : null);
    $meta['description'] = $post['meta']['meta_description'] ?? $meta['description'];
    $meta['og:title'] = $post['meta']['opengraph_title'] ?? $post['title'];
    $meta['og:image'] = $opengraph_image;
    $meta['og:description'] = $post['meta']['opengraph_description'] ?? $post['excerpt'] ?? $meta['description'];
    $meta['twitter:title'] = $post['meta']['twitter_title'] ?? $post['title'];
    $meta['twitter:image'] = $twitter_image;
    $meta['twitter:description'] = $post['meta']['twitter_description'] ?? $post['excerpt'] ?? $meta['description'];
}
@endphp
<!DOCTYPE html>
<html lang="en" class="h-full">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    @foreach ($meta as $k => $v)
        @if ($k && $v)
            <meta name="{{ $k }}" content="{{ $v }}" />
        @endif
    @endforeach
    @yield('meta')

    <title>@yield('title', 'Home') | Jason Vertucio</title>
    {{-- Preconnect to font CDNs for faster loading --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preconnect" href="https://db.onlinewebfonts.com">
    <link rel="preconnect" href="https://fonts.cdnfonts.com">
    {{-- Fonts --}}
    <link href="https://fonts.googleapis.com/css?family=Saira+Extra+Condensed:500,700" rel="stylesheet">
    <link href="https://db.onlinewebfonts.com/c/29dc27977e417a98e56556776f41607c?family=Corbel" rel="stylesheet">
    <link href="https://fonts.cdnfonts.com/css/convection" rel="stylesheet">
    {{-- Custom styles for this template --}}
    @vite(['resources/css/blog.css'])
    @stack('styles')
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

        <body id="page-top" class="font-body flex flex-col min-h-screen bg-gray-50">
            <div id="fb-root"></div>
            <script async defer crossorigin="anonymous"
                src="https://connect.facebook.net/en_GB/sdk.js#xfbml=1&version=v9.0&appId=695473097788503&autoLogAppEvents=1"
                nonce="{{ Str::random(8) }}"></script>

            <nav class="sticky top-0 bg-primary shadow-md z-50" id="navbarTop">
                <div class="max-w-7xl mx-auto px-4">
                    <div class="flex justify-between items-center h-16">
                        <div class="flex items-center">
                            <a class="text-white text-xl" href="{{ route('home') }}">Jason Vertucio</a>
                            <ul class="hidden md:flex ml-10 space-x-4">
                                @foreach($links as $link)
                                    <li>
                                        <a href="{{ $link['href'] }}"
                                            class="text-white/75 hover:text-white px-3 py-2 rounded-md">{{ $link['label'] }}</a>
                                    </li>
                                @endforeach

                            </ul>
                        </div>
                        <div class="flex items-center">
                            <div class="relative" x-data="{ open: false }">
                                <button @click="open = !open"
                                    class="text-white bg-primarypx-4 py-2 rounded-md hover:bg-primary/80 focus:outline-none"
                                    type="button">
                                    Places
                                    <svg class="inline-block w-4 h-4 ml-2" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M19 9l-7 7-7-7"></path>
                                    </svg>
                                </button>
                                <div x-show="open" @click.away="open = false"
                                    class="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg py-1 z-50"
                                    style="display: none;">
                                    <a class="block px-4 py-2 text-dark hover:bg-gray-100" href="{{ route('home') }}"
                                        title="Go back home">
                                        Home
                                    </a>
                                    @foreach($places as $link)
                                        @if (is_string($link))
                                            {!! $link !!}
                                        @else
                                            <a class="block px-4 py-2 text-dark hover:bg-gray-100" href="{{ $link['href'] }}"
                                                title="{{ $link['label'] }}" {{ isset($link['target']) ? ' target="' . $link['target'] . '"' : '' }}>
                                                {{ $link['label'] }}
                                            </a>
                                        @endif
                                    @endforeach
                                    @ifcanvasauthenticated
                                    <div class="border-t border-gray-200"></div>
                                    <a class="block px-4 py-2 text-dark hover:bg-gray-100" href="/{{ config('canvas.path') }}">
                                        Canvas Blog
                                    </a>
                                    @endifcanvasauthenticated
                                    @can('manage-unauthenticated-viewers')
                                    <div class="border-t border-gray-200"></div>
                                    <a class="block px-4 py-2 text-dark hover:bg-gray-100" href="{{ route('admin.index') }}">
                                        Admin
                                    </a>
                                    @endcan
                                    @ifauthenticated
                                    <div class="border-t border-gray-200"></div>
                                    <a class="block px-4 py-2 text-dark hover:bg-gray-100" href="{{ route('authkit.profile.show') }}">
                                My Profile
                            </a>
                            <a class="block px-4 py-2 text-dark hover:bg-gray-100" href="/logout">
                                Log out
                            </a>
                            @endifauthenticated
                        </div>
                    </div>
                    <button class="md:hidden ml-4 text-white"
                        onclick="document.getElementById('mobileMenu').classList.toggle('hidden')">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M4 6h16M4 12h16M4 18h16"></path>
                        </svg>
                    </button>
                </div>
            </div>
            <div id="mobileMenu" class="hidden md:hidden pb-4">
                <ul class="space-y-2">
                    @foreach($links as $link)
                        <li>
                            <a href="{{ $link['href'] }}"
                                class="block text-white/75 hover:text-white px-3 py-2">{{ $link['label'] }}</a>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </nav>

    <div id="main" class="fonts-loading grow">
        <div class="mx-0 my-4 p-0">
            @yield('main')
        </div>
    </div>

    <footer class="bg-secondary mt-auto py-3 text-white">
        <div class="max-w-7xl mx-auto px-4">
            <div class="text-right">
                <span>Copyright &copy; {{ date('Y') }}, Jason Vertucio.</span>
            </div>
        </div>
    </footer>

    {{-- Vite assets --}}
    @vite(['resources/js/font-loader.js', 'resources/js/app.js'])

    {{-- Font loading progress --}}
    <script>
        if (typeof initFontLoader === 'function') {
            initFontLoader('#main');
        }
    </script>

    @include('gtag')
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    @stack('scripts')
</body>

</html>
