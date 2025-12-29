<nav class="navbar navbar-expand-lg navbar-dark bg-primary fixed-top" id="sideNav">
    <a class="navbar-brand js-scroll-trigger" href="#page-top">
        <span class="d-block d-lg-none">Jason Vertucio</span>
        <span class="d-none d-lg-block">
            <img class="img-fluid img-profile rounded-circle mx-auto mb-2" src="{{ asset('img/jv.png') }}" alt="">
        </span>
    </a>
    <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarSupportedContent"
        aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarSupportedContent">
        <ul class="navbar-nav">
            @ifcanvasauthenticated
            <li class="nav-item">
                <a class="nav-link" href="/{{ config('canvas.path') }}">Go to Canvas Blog</a>
            </li>
            @endifcanvasauthenticated
            @foreach($config['links'] as $link)
                <li class="nav-item">
                    @if (isset($link['target']))
                        <a class="nav-link js-scroll-trigger" href="{{ $link['href'] }}"
                            target="{{ $link['target'] }}">{{ $link['label'] }}</a>
                    @else
                        <a class="nav-link js-scroll-trigger" href="{{ $link['href'] }}">{{ $link['label'] }}</a>
                    @endif
                </li>
            @endforeach
        </ul>
    </div>
</nav>
