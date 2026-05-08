@php
    $config = json_decode(file_get_contents(resource_path() . "/config/config.json"), true);
    $navLinks = $config["links"] ?? [];
@endphp
<!DOCTYPE html>
<html lang="en" class="h-full">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="author" content="Jason Vertucio">
    @inertiaHead
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Josefin+Sans:wght@400;500;700&family=Montserrat:wght@400;500;700&display=swap" rel="stylesheet">
    @vite(["resources/css/blog.css"])
    @viteReactRefresh
    <noscript>
        <style>
            .fonts-loading {
                opacity: 1 !important;
            }
        </style>
    </noscript>
</head>

<body id="page-top" class="flex min-h-screen flex-col bg-gray-50 font-body">
    <nav id="navbarTop" class="sticky top-0 z-50 bg-primary shadow-md">
        <div class="mx-auto max-w-7xl px-4">
            <div class="flex h-16 items-center justify-between">
                <div class="flex items-center">
                    <a class="font-heading text-xl text-white" href="{{ route("home") }}">Jason Vertucio</a>
                </div>
                <div class="flex items-center">
                    <div class="relative" x-data="{ open: false }">
                        <button class="rounded-md py-2 text-white hover:bg-primary/80 focus-visible:outline-2 focus-visible:outline-white focus-visible:outline-offset-2"
                                type="button"
                                aria-haspopup="menu"
                                :aria-expanded="open"
                                @click="open = !open">
                            Places
                            <svg class="ml-2 inline-block h-4 w-4"
                                 fill="none"
                                 stroke="currentColor"
                                 viewBox="0 0 24 24"
                                 aria-hidden="true">
                                <path stroke-linecap="round"
                                      stroke-linejoin="round"
                                      stroke-width="2"
                                      d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </button>
                        <div x-show="open"
                             role="menu"
                             aria-label="Places"
                             class="absolute right-0 z-50 mt-2 w-48 rounded-md bg-white py-1 shadow-lg"
                             style="display: none;"
                             @click.away="open = false">
                            <a class="block px-4 py-2 text-dark hover:bg-gray-100"
                               role="menuitem"
                               href="{{ route("home") }}">
                                Home
                            </a>
                            @foreach ($navLinks as $link)
                                @if (!empty($link["divider"]))
                                    <hr aria-hidden="true" class="my-1 border-gray-200">
                                @elseif (empty($link["can"]) || ($link["can"] === "authenticated" ? auth()->check() : Gate::allows($link["can"])))
                                    <a class="block px-4 py-2 text-dark hover:bg-gray-100"
                                       role="menuitem"
                                       href="{{ $link["href"] }}"
                                       @if (!empty($link["target"])) target="{{ $link["target"] }}" rel="noopener noreferrer" @endif>
                                        {{ $link["label"] }}
                                    </a>
                                @endif
                            @endforeach
                        </div>
                    </div>
                    <button class="ml-4 text-white md:hidden focus-visible:outline-2 focus-visible:outline-white focus-visible:outline-offset-2"
                            aria-label="Toggle navigation menu"
                            aria-controls="mobileMenu"
                            onclick="this.setAttribute('aria-expanded', document.getElementById('mobileMenu').classList.toggle('hidden') ? 'false' : 'true')">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                        </svg>
                    </button>
                </div>
            </div>
            <nav id="mobileMenu" aria-label="Mobile navigation" class="hidden md:hidden" style="margin: auto -16px;">
                @foreach ($navLinks as $link)
                    @if (!empty($link["divider"]))
                        <hr aria-hidden="true" class="my-1 border-white/20">
                    @elseif (empty($link["can"]) || ($link["can"] === "authenticated" ? auth()->check() : Gate::allows($link["can"])))
                        <a href="{{ $link["href"] }}"
                           class="block px-4 py-2 text-white hover:text-white/75 focus-visible:outline-2 focus-visible:outline-white focus-visible:outline-offset-2"
                           @if (!empty($link["target"])) target="{{ $link["target"] }}" rel="noopener noreferrer" @endif>
                            {{ $link["label"] }}
                        </a>
                    @endif
                @endforeach
            </nav>
        </div>
    </nav>

    <div id="main" class="fonts-loading grow">
        <div class="mx-0 my-4 p-0">
            @inertia
        </div>
    </div>

    <footer class="mt-auto bg-secondary py-3 text-white">
        <div class="mx-auto max-w-7xl px-4">
            <div class="text-right">
                <span>Copyright &copy; {{ date("Y") }}, Jason Vertucio.</span>
            </div>
        </div>
    </footer>

    @vite(["resources/js/font-loader.js", "resources/js/app.js", "resources/js/chat/app.tsx"])
    <script>
        if (typeof initFontLoader === 'function') {
            initFontLoader('#main');
        }
    </script>
    @include("gtag")
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</body>

</html>
