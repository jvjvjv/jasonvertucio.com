@php
if (!isset($links)) {
  $links = [];
}

$meta = [
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
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
@foreach ($meta as $k => $v)
@if ($k && $v)
  <meta name="{{ $k }}" value="{{ $v }}" />
@endif
@endforeach

  <title>@yield('title', 'Home') | Jason Vertucio</title>
  {{-- Custom styles for this template --}}
  <link href="{{asset('css/blog.css') }}" rel="stylesheet">
  <script data-ad-client="ca-pub-0429292532295045" async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js"></script>
</head>

<body id="page-top">

  <nav class="navbar navbar-default navbar-expand-lg sticky-top navbar-light bg-primary" id="navbarTop">
    <div class="navbar-header mr-2">
      <a class="navbar-brand" href="{{ route('home') }} ">Jason Vertucio</a>
    </div>
    <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#headerNav" aria-controls="headerNav" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="headerNav">
      <ul class="navbar-nav mr-auto">
      @foreach($links as $link)
        <li class="nav-item">
          <a href="{{ $link['href'] }}">{{ $link['label'] }}</a>
        </li>
      @endforeach
      </ul>
      <ul class="navbar-nav">
        <li class="nav-item dropdown">
          <button class="btn btn-primary dropdown-toggle" id="navbarDropdown" type="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
            Places
          </button>
          <div class="dropdown-menu dropdown-menu-right" aria-labelledby="navbarDropdown">
            <a class="dropdown-item" href="{{ route('home') }}">
              Home
            </a>
            <a class="dropdown-item" href="{{ route('blog') }}">
              Blog
            </a>
            <a class="dropdown-item" href="{{ route('paper') }}">
              Paper
            </a>
            @if (!env('APP_DEBUG'))
            <div class="dropdown-divider"></div>
            <a class="dropdown-item" href="/btoatob">
              B(to)a(to)B
            </a>
            <a class="dropdown-item" href="/todo">
              To Do
            </a>
            @endif
            @ifwinkauthenticated
            <div class="dropdown-divider"></div>
            <a class="dropdown-item" href="/wink">
              Wink Blog
            </a>
            {{-- <a class="dropdown-item" href="#">Something else here</a> --}}
            @endifwinkauthenticated
          </div>
        </li>
      </ul>
    </div><!-- /.navbar-collapse -->
  </nav>

  <div class="container-fluid mx-0 my-1 p-0">

  @yield('main')

  </div>

  <footer class="footer bg-secondary py-3 text-white position-sticky fixed-bottom">
    <div class="container-fluid">
      <div class="row">
        <div class="col-sm text-right">
          <span class="text-dark">Copyright &copy; {{ date('Y') }}, Jason Vertucio.</span>
        </div>
      </div>
    </div>
  </footer>


  @include('gtag')
  {{--Custom scripts for this template --}}
  <script src="{{ asset('js/app.js') }}"></script>
</body>
</html>
