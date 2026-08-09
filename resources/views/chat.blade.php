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
    <x-top-navbar />

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
