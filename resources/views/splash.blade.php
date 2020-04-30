<?php
  $links = [
    [
      'href' => '#about',
      'label' => 'About'
    ],
    [
      'href' => '#skills',
      'label' => 'Skills'
    ],
    [
      'href' => '#interests',
      'label' => 'Interests'
    ],
    [
      'href' => route('paper'),
      'label' => 'Paper'
    ],
  ];

  $lang_icons = [
    ['icon' => 'js-square', 'label' => 'ECMAScript/JavaScript'],
    ['icon' => 'html5', 'label' => 'HTML'],
    ['icon' => 'css3-alt', 'label' => 'CSS'],
    ['icon' => 'sass', 'label' => 'Sass'],
    ['icon' => 'node', 'label' => 'NodeJS'],
    ['icon' => 'php', 'label' => 'PHP'],
    ['icon' => 'google-play', 'label' => 'Andrdoid'],
    ['icon' => 'app-store', 'label' => 'iOS, iPadOS, Apple TV'],
  ];

  $browser_icons = [
    ['icon' => 'chrome', 'label' => 'Google Chrome'],
    ['icon' => 'firefox-browser', 'label' => 'Mozilla Firefox'],
    ['icon' => 'safari', 'label' => 'Safari'],
  ];

  $tech_icons = [
    ['icon' => 'linode', 'label' => 'linode'],
    ['icon' => 'windows', 'label' => 'windows'],
    ['icon' => 'linux', 'label' => 'Linux'],
    ['icon' => 'centos', 'label' => 'CentOS'],
    ['icon' => 'ubuntu', 'label' => 'Ubuntu'],
    ['icon' => 'raspberry-pi', 'label' => 'Pi'],
    ['icon' => 'chromecast', 'label' => 'Chromecast'],
    ['icon' => 'bluetooth-b', 'label' => 'Bluetooth'],
  ];

  $framework_icons = [
    ['icon' => 'vuejs', 'label' => 'VueJS'],
    ['icon' => 'laravel', 'label' => 'Laravel'],
    ['icon' => 'bootstrap', 'label' => 'Bootstrap'],
    ['icon' => 'angular', 'label' => 'Angular'],
    ['icon' => 'react', 'label' => 'ReactJS, ReactNative'],
    // ['icon' => 'wordpress', 'label' => 'Wordpress'],
    // ['icon' => 'empire', 'label' => 'Galactic Empire'],
  ];

  $workflow_icons = [
    ['icon' => 'slack', 'label' => 'Slack'],
    ['icon' => 'sketch', 'label' => 'Sketch'],
    ['icon' => 'git-alt', 'label' => 'Git'],
    ['icon' => 'sourcetree', 'label' => 'sourcetree'],
    ['icon' => 'github', 'label' => 'Github'],
    ['icon' => 'bitbucket', 'label' => 'Bitbucket'],
    ['icon' => 'jira', 'label' => 'Jira'],
    ['icon' => 'confluence', 'label' => 'Confluence'],
    ['icon' => 'jenkins', 'label' => 'Jenkins'],
  ];

  $workflow = [
    'Mobile-First, Responsive Design',
    'App development using Ionic, VueJS, React, NativeScript',
    'Lightweight API development using ExpressJS, Lumen, Laravel',
    'Cross Browser Testing &amp; Debugging',
    'Agile Development &amp; Scrum',
  ];
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

  <title>Jason Vertucio</title>

  <!-- Custom styles for this template -->
  <link href="{{asset('css/app.css') }}" rel="stylesheet">

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
        @foreach($links as $link)
        <li class="nav-item">
          <a class="nav-link js-scroll-trigger" href="{{ $link['href'] }}">{{ $link['label'] }}</a>
        </li>
        @endforeach
      </ul>
    </div>
  </nav>

  <div class="container-fluid p-0">

    <section class="resume-section p-3 p-lg-5 d-flex align-items-center" id="about">
      <div class="w-100">
        <h1 class="mb-0">Jason
          <span class="text-primary">Vertucio</span>
        </h1>
        <div class="subheading mb-5">Lancaster, PA 17601 · (267) 702-5298 ·
          <a href="mailto:me@jasonvertucio.com?subject=Better%20change%20this%20or%20I'll%20think%20you're%20spamming%20me&body=Hi%0c%0a">me@jasonvertucio.com</a>
        </div>
        <p class="lead mb-5">
          I specialize in mobile app front-end development and responsive web development both for small companies and for enterprise-level businesses.
          I am also experienced in Laravel development in a LAMP stack. I operate sites and web applications on CentOS and Ubuntu platforms.
        </p>
        <!-- <p class="mb-5">
          Check out daily, curated news content on tech and stuff. <a href="/paper">I Did a Thing!</a>
        </p> -->
        <div class="social-icons">
          <a href="https://www.linkedin.com/in/jasonvertucio/">
            <i class="fab fa-linkedin-in"></i>
          </a>
          <a href="https://twitter.com/jasondidathing">
            <i class="fab fa-twitter"></i>
          </a>
        </div>
      </div>
    </section>

    <hr class="m-0">

    <section class="resume-section p-3 p-lg-5 d-flex align-items-center" id="skills">
      <div class="w-100">
        <h2 class="mb-5">Skills</h2>

        <div class="subheading mb-3">Programming Languages &amp; Tools</div>
        <ul class="list-inline dev-icons">
          @foreach ($lang_icons as $tech)
          <li class="list-inline-item"><i class="fab fa-{{$tech['icon'] }}" title="{{ $tech['label'] }}"></i></li>
          @endforeach
        </ul>

        <ul class="list-inline dev-icons">
          @foreach ($framework_icons as $icon)
          <li class="list-inline-item"><i class="fab fa-{{$icon['icon'] }}" title="{{ $icon['label'] }}"></i></li>
          @endforeach
        </ul>

        <ul class="list-inline dev-icons">
          @foreach ($browser_icons as $icon)
          <li class="list-inline-item"><i class="fab fa-{{$icon['icon'] }}" title="{{ $icon['label'] }}"></i></li>
          @endforeach
        </ul>

        <ul class="list-inline dev-icons">
          @foreach ($tech_icons as $icon)
          <li class="list-inline-item"><i class="fab fa-{{$icon['icon'] }}" title="{{ $icon['label'] }}"></i></li>
          @endforeach
        </ul>

        <ul class="list-inline dev-icons">
          @foreach ($workflow_icons as $icon)
          <li class="list-inline-item"><i class="fab fa-{{$icon['icon'] }}" title="{{ $icon['label'] }}"></i></li>
          @endforeach
        </ul>

        <div class="subheading mb-3">Workflow</div>
        <ul class="fa-ul mb-0">
          @foreach ($workflow as $line)
          <li><i class="fa-li fa fa-check"></i> {!! $line !!}</li>
          @endforeach
        </ul>
      </div>
    </section>

    <hr class="m-0">

    <section class="resume-section p-3 p-lg-5 d-flex align-items-center" id="interests">
      <div class="w-100">
        <h2 class="mb-5">Interests</h2>
        <p>I love driving. I play guitar and a couple of other instruments.</p>
        <p class="mb-0">I'm also on the lookout for a ship that can travel through all of Time And Relative Dimension In Space.</p>
      </div>
    </section>

  </div>

  <!-- Custom scripts for this template -->
  <script src="{{ asset('js/app.js') }}"></script>

@include('gtag')

</body>

</html>
