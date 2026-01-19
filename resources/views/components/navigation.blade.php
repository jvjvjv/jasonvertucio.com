<nav class="fixed top-0 left-0 w-full md:w-52 lg:w-64 md:h-screen bg-primary text-white z-50 flex flex-col md:justify-center md:items-center"
    id="sideNav"
    role="navigation">
    <a class="me m-2 p-2 md:flex md:justify-center md:items-center md:mb-0 rounded-2xl" href="#page-top">
        <span class="block md:hidden text-xl">Jason Vertucio</span>
        <span class="hidden md:block">
            <img class="w-40 h-40 rounded-full border-4 border-white/20 mx-auto" src="{{ asset('img/jv.png') }}"
                alt="Jason Vertucio">
        </span>
    </a>
    <button class="md:hidden absolute top-4 right-4 p-2 text-white" type="button"
        onclick="document.getElementById('navbarSupportedContent').classList.toggle('hidden')"
        aria-label="Toggle navigation">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
        </svg>
    </button>
    <div class="hidden md:flex md:flex-col md:items-center" id="navbarSupportedContent">
        <ul class="flex flex-col gap-y-2">
            @ifcanvasauthenticated
            <li>
                <a class="block px-4 py-1 text-white hover:text-white hover:underline uppercase tracking-wider font-bold transition-all rounded-md"
                    href="/{{ config('canvas.path') }}">Go to Canvas Blog</a>
            </li>
            @endifcanvasauthenticated
            @foreach($config['links'] as $link)
                <li>
                    @if (isset($link['target']))
                        <a class="block px-4 py-2 text-white hover:text-white hover:underline uppercase tracking-wider font-bold transition-all rounded-md"
                            href="{{ $link['href'] }}" target="{{ $link['target'] }}"
                            role="link" title="{{ $link_label($link) }}">{{ $link['label'] }}</a>
                    @else
                        <a class="block px-4 py-1 text-white hover:text-white hover:underline uppercase tracking-wider font-bold transition-all rounded-md"
                            href="{{ $link['href'] }}" role="link" title="{{ $link_label($link) }}">{{ $link['label'] }}</a>
                    @endif
                </li>
            @endforeach
        </ul>
    </div>
</nav>
