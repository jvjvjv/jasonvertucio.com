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

  <title>Jason Vertucio</title>

  <!-- Custom styles for this template -->
  <link href="{{asset('css/splash.css') }}" rel="stylesheet">

</head>

<body id="page-top">

  <nav class="navbar navbar-expand-lg navbar-dark bg-primary fixed-top" id="sideNav">
    <a class="navbar-brand js-scroll-trigger" href="#page-top">
      <span class="d-block d-lg-none">Jason Vertucio</span>
      <span class="d-none d-lg-block">
        <img class="img-fluid img-profile rounded-circle mx-auto mb-2" src="{{ asset('img/jv.png') }}" alt="">
      </span>
    </a>
    <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarSupportedContent">
      <ul class="navbar-nav">
        @ifwinkauthenticated
        <li class="nav-item">
          <a class="nav-link" href="/wink">Go to Wink Blog</a>
        </li>
        @endifwinkauthenticated
        @ifcanvasauthenticated
        <li class="nav-item">
          <a class="nav-link" href="/canvas">Go to Canvas Blog</a>
        </li>
        @endifcanvasauthenticated
        @foreach($config['links'] as $link)
        <li class="nav-item">
          @if (isset($link['target']))
            <a class="nav-link js-scroll-trigger" href="{{ $link['href'] }}" target="{{ $link['target'] }}">{{ $link['label'] }}</a>
          @else
          <a class="nav-link js-scroll-trigger" href="{{ $link['href'] }}">{{ $link['label'] }}</a>
          @endif
        </li>
        @endforeach
      </ul>
    </div>
  </nav>

  <div class="container-fluid p-0">

    <section class="resume-section p-3 p-lg-5 d-flex align-items-center" id="about">
      <div class="w-100">
        <h1 class="mb-0">{{ $config['about_me']['name']['given'] }}
          <span class="text-primary">{{ $config['about_me']['name']['sur'] }}</span>
        </h1>
        <div class="subheading mb-5">
          {{ $config['about_me']['address']['city'] }},
          {{ $config['about_me']['address']['state'] }}
          {{ $config['about_me']['address']['zip'] }}
          ·
          {{ $config['about_me']['phone'] }}
          ·
          <a href="mailto:{{ $config['about_me']['email']['email_address'] }}?subject={{ $config['about_me']['email']['subject'] }}&body={{ $config['about_me']['email']['body'] }}">
            {{ $config['about_me']['email']['email_address'] }}
          </a>
        </div>
        @foreach ($config['about_me']['sections'] as $section)
        <p class="lead mb-5">
          {!! $section !!}
        </p>
      @endforeach
        <!-- <p class="mb-5">
          Check out daily, curated news content on tech and stuff. <a href="/paper">I Did a Thing!</a>
        </p> -->
        <div class="social-icons">
          @foreach ($config['about_me']['social'] as $item)
          <a href="{{ $item['link'] }}" target="_blank">
            <i class="fab {{ $item['fa_icon'] }}"></i>
          </a>
          @endforeach
        </div>
      </div>
    </section>

    <hr class="m-0">

@if ($blog)
    <section class="resume-section p-3 p-lg-5 d-flex align-items-center" id="blog">
      <div class="w-100">
        <h2 class="mb-5">Latest Blog</h2>
        <h4>{{ $blog['title'] }}</h4>
        @if ($blog['featured_image'])
        <div class="image">
          <img src="{{ $blog['featured_image'] }}" style="width: 100%;">
          @if ($blog['featured_image_caption'])
            <p class="text-center">{!! $blog['featured_image_caption'] !!}</p>
          @endif
        @endif
        @if ($blog['excerpt'])
        <p>{{ $blog['excerpt'] }}</p>
        @else
        <p>(I am supposed to enter a sort of flavor text on these things but I didn't for this one. Oh well.)</p>
        @endif
        <p>
          {{ $blog['published_at']->diffForHumans() }}
        </p>
        <a class="btn btn-outline-secondary" href="/blog/{{ $blog['slug'] }}">Read</a>
        @ifcanvasauthenticated
          <a class="btn btn-outline-primary" href="/canvas/posts/{{ $blog['id'] }}/edit">Edit</a>
        @endifcanvasauthenticated
      </div>
    </section>

    <hr class="m-0">
@endif

    <section class="resume-section p-3 p-lg-5 d-flex align-items-center" id="skills">
      <div class="w-100">
        <h2 class="mb-5">Skills</h2>

        <div class="subheading mb-3">Programming Languages &amp; Tools</div>
        <ul class="list-inline dev-icons">
          @foreach ($config['icons']['lang'] as $tech)
          <li class="list-inline-item"><i class="fab fa-{{$tech['icon'] }}" title="{{ $tech['label'] }}"></i></li>
          @endforeach
        </ul>

        <ul class="list-inline dev-icons">
          @foreach ($config['icons']['framework'] as $icon)
          <li class="list-inline-item"><i class="fab fa-{{$icon['icon'] }}" title="{{ $icon['label'] }}"></i></li>
          @endforeach
        </ul>

        <ul class="list-inline dev-icons">
          @foreach ($config['icons']['browser'] as $icon)
          <li class="list-inline-item"><i class="fab fa-{{$icon['icon'] }}" title="{{ $icon['label'] }}"></i></li>
          @endforeach
        </ul>

        <ul class="list-inline dev-icons">
          @foreach ($config['icons']['tech'] as $icon)
          <li class="list-inline-item"><i class="fab fa-{{$icon['icon'] }}" title="{{ $icon['label'] }}"></i></li>
          @endforeach
        </ul>

        <ul class="list-inline dev-icons">
          @foreach ($config['icons']['workflow'] as $icon)
          <li class="list-inline-item"><i class="fab fa-{{$icon['icon'] }}" title="{{ $icon['label'] }}"></i></li>
          @endforeach
        </ul>

        <div class="subheading mb-3">Workflow</div>
        <ul class="fa-ul mb-0">
          @foreach ($config['workflow'] as $line)
          <li><i class="fa-li fa fa-check"></i> {!! $line !!}</li>
          @endforeach
        </ul>
      </div>
    </section>

    <hr class="m-0">

    <section class="resume-section p-3 p-lg-5 d-flex align-items-center" id="interests">
      <div class="w-100">
        <h2 class="mb-5">Interests</h2>
        @foreach($config['interests'] as $interest)
        <p>{!! $interest !!}</p>
        @endforeach
      </div>
    </section>

  </div>

  <!-- Custom scripts for this template -->
  @include('cookies')
  <script cookie-consent="functionality" src="{{ asset('js/app.js') }}"></script>
  @include('gtag')

</body>

</html>
