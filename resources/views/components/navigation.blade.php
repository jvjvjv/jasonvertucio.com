<nav id="sideNav"
     class="md:fixed left-0 top-0 z-50 flex w-full flex-col bg-primary text-white md:h-screen md:w-52 md:items-center md:justify-center lg:w-64"
     role="navigation">
    <a class="me m-2 rounded-2xl p-2 md:mb-0 md:flex md:items-center md:justify-center" href="#page-top">
        <span class="block text-xl md:hidden">Jason Vertucio</span>
        <span class="hidden md:block">
            <img class="mx-auto h-40 w-40 rounded-full border-4 border-white/20"
                 src="{{ asset("img/jv.png") }}"
                 alt="Jason Vertucio">
        </span>
    </a>
    <button class="absolute right-4 top-4 p-2 text-white md:hidden"
            type="button"
            onclick="document.getElementById('navbarSupportedContent').classList.toggle('hidden')"
            aria-label="Toggle navigation">
        <svg class="h-6 w-6"
             fill="none"
             stroke="currentColor"
             viewBox="0 0 24 24">
            <path stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M4 6h16M4 12h16M4 18h16"></path>
        </svg>
    </button>
    <div id="navbarSupportedContent" class="mt-8 hidden md:flex md:flex-col md:items-center">
        <ul class="flex flex-col items-center gap-y-2">
            @foreach ($links as $link)
                @if (!empty($link["can"]))
                    @can($link["can"])
                        <li>
                            @if (isset($link["target"]))
                                <a class="block rounded-md px-4 py-2 font-bold uppercase tracking-wider text-white transition-all hover:text-white hover:underline"
                                   href="{{ $link["href"] }}"
                                   target="{{ $link["target"] }}"
                                   role="link"
                                   title="{{ $link_label($link) }}">{{ $link["label"] }}</a>
                            @else
                                <a class="block rounded-md px-4 py-1 font-bold uppercase tracking-wider text-white transition-all hover:text-white hover:underline"
                                   href="{{ $link["href"] }}"
                                   role="link"
                                   title="{{ $link_label($link) }}">{{ $link["label"] }}</a>
                            @endif
                        </li>
                    @endcan
                @else
                    <li>
                        @if (isset($link["href"]))
                            @if (isset($link["target"]))
                                <a class="block rounded-md px-4 py-2 font-bold uppercase tracking-wider text-white transition-all hover:text-white hover:underline"
                                   href="{{ $link["href"] }}"
                                   target="{{ $link["target"] }}"
                                   role="link"
                                   title="{{ $link_label($link) }}">{{ $link["label"] }}</a>
                            @else
                                <a class="block rounded-md px-4 py-1 font-bold uppercase tracking-wider text-white transition-all hover:text-white hover:underline"
                                   href="{{ $link["href"] }}"
                                   role="link"
                                   title="{{ $link_label($link) }}">{{ $link["label"] }}</a>
                            @endif
                        @endif
                    </li>
                @endif
            @endforeach
        </ul>
    </div>
</nav>
