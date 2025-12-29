<nav class="fixed top-0 left-0 w-full lg:w-64 lg:h-screen bg-primary text-white z-50 flex flex-col" id="sideNav">
    <a class="p-4 lg:flex lg:justify-center lg:items-center lg:mb-0" href="#page-top">
        <span class="block lg:hidden text-xl">Jason Vertucio</span>
        <span class="hidden lg:block">
            <img class="w-40 h-40 rounded-full border-4 border-white/20 mx-auto" src="{{ asset('img/jv.png') }}"
                alt="Jason Vertucio">
        </span>
    </a>
    <button class="lg:hidden absolute top-4 right-4 p-2 text-white" type="button"
        onclick="document.getElementById('navbarSupportedContent').classList.toggle('hidden')"
        aria-label="Toggle navigation">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
        </svg>
    </button>
    <div class="hidden lg:flex lg:flex-col lg:grow lg:items-center" id="navbarSupportedContent">
        <ul class="flex flex-col gap-y-2">
            @ifcanvasauthenticated
            <li>
                <a class="block px-4 py-1 text-white/75 hover:text-white uppercase tracking-wider font-bold transition-colors"
                    href="/{{ config('canvas.path') }}">Go to Canvas Blog</a>
            </li>
            @endifcanvasauthenticated
            @foreach($config['links'] as $link)
                <li>
                    @if (isset($link['target']))
                        <a class="block px-4 py-2 text-white/75 hover:text-white uppercase tracking-wider font-bold transition-colors"
                            href="{{ $link['href'] }}" target="{{ $link['target'] }}">{{ $link['label'] }}</a>
                    @else
                        <a class="block px-4 py-1 text-white/75 hover:text-white uppercase tracking-wider font-bold transition-colors"
                            href="{{ $link['href'] }}">{{ $link['label'] }}</a>
                    @endif
                </li>
            @endforeach
        </ul>
    </div>
</nav>
