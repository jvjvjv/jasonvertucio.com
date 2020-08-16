<?php
if (!isset($links)) {
  $links = [];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>

  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <meta name="description" content="Jason Vertucio does mobile application development.">
  <meta name="author" content="Jason Vertucio">

  <meta name="twitter:card" value="summary">
  <meta name="twitter:title" value="Jason, who did a thing">
  <meta name="twitter:creator" value="@jasondidathing">
  <meta name="twitter:site" value="@jasondidathing">
  <meta name="twitter:image" value="https://beepbeepritchiellc.com/HDTV.png">

  <title>@yield('title', 'Home') | Jason Vertucio</title>

  {{-- Custom styles for this template --}}
  <link href="{{asset('css/blog.css') }}" rel="stylesheet">
</head>

<body id="page-top">

  <nav class="navbar navbar-default navbar-expand-lg sticky-top" id="navbarTop">
    {{-- <div class="container-fluid"> --}}
      <div class="navbar-header mr-2">
        <a class="navbar-brand" href="{{ route('home') }} ">Jason Vertucio</a>
          <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#headerNav" aria-controls="headerNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
      </div>

      <div class="collapse navbar-collapse" id="headerNav">
        <ul class="navbar-nav mr-auto">
        @foreach($links as $link)
          <li class="nav-item">
            <a href="{{ $link['href'] }}">{{ $link['label'] }}</a>
          </li>
        @endforeach
          {{-- <li class="nav-item">
            <a href="#" class="nav-link">Posts</a>
          </li>
          <li class="nav-item">
            <a href="#" class="nav-link">Tags</a>
          </li> --}}
        </ul>
        <ul class="navbar-nav">
          <li class="nav-item dropdown">
            <button class="btn btn-link dropdown-toggle" id="navbarDropdown" type="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
              Places
            </button>
            <div class="dropdown-menu" aria-labelledby="navbarDropdown">
              <a class="dropdown-item" href="{{ route('home') }}">
                Home
              </a>
              <a class="dropdown-item" href="{{ route('blog') }}">
                Blog
              </a>
              <a class="dropdown-item" href="{{ route('paper') }}">
                Paper
              </a>
              {{-- <div class="dropdown-divider"></div>
              <a class="dropdown-item" href="#">Something else here</a> --}}
            </div>
          </li>
        </ul>
      </div><!-- /.navbar-collapse -->
    {{-- </div><!-- /.container-fluid --> --}}
  </nav>
  
  <div class="container-fluid m-0 p-0">

  @yield('main')

  </div>
  @include('gtag')
  {{--Custom scripts for this template --}}
  <script src="{{ asset('js/app.js') }}"></script>
</body>
</html>