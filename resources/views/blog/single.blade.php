@extends('layout')
@section('title', $post['title'] . ' | Blog')
@section('navhome', 'slash')

@section('main')
<div id="fb-root"></div>
<script async defer cookie-consent="functionality" crossorigin="anonymous" src="https://connect.facebook.net/en_US/sdk.js#xfbml=1&version=v8.0" nonce="oiR10rcH"></script>

<div class="container">
  <div class="row">
    <div class="col">
      <h2 class="title mt-3 mb-5">
        {{ $post['title'] }}
      </h2>
    </div>
  </div>

  <div class="row">
    <div class="col-sm-8">

      <div class="blog-item single">
        <div class="content">
          {!! $post['content'] !!}
        </div>
        <div class="list-meta">
          {{-- @if ($post->author)
            <strong>By:</strong> {{ $post->author['name'] }}
            |
          @endif --}}
          <strong>Published:</strong> {{ $post['publish_date']->format('m/d/Y g:ia') }}
          @ifwinkauthenticated
          | <a href="/wink/posts/{{ $post['id'] }}" class="card-link">Edit</a>
          @endifwinkauthenticated
        </div>
        <div class="share">
          <span class="share-header">Share:</span>
          <div class="social-icons px-2">
            <a href="https://twitter.com/share?ref_src=twsrc%5Etfw" target="_blank" class="twitter-share-button" data-show-count="false">
              <i class="fab fa-twitter"></i>
            </a>
          </div>
          <div class="social-icons px-2">
            <div
              class="fb-share-button"
              data-href="{{ Request::url() }}"
              data-lazy="true"
              data-order-by="social"
              data-layout="button_count"></div>
          </div>
        </div>
        <div class="comments">
          <h3>Comments</h3>
          <div
            class="fb-comments"
            data-href="{{ Request::url() }}"
            data-lazy="true"
            data-numposts="10"
            data-width="100%"></div>
        </div>
        @if (env('APP_DEBUG'))
          <pre>
          {{-- @json($post, JSON_PRETTY_PRINT) --}}
          {{ json_encode ($post, JSON_PRETTY_PRINT) }}
          </pre>
        @endif
      </div>
    </div>
    <div class="col-sm-4">
      <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js"></script>
      <!-- JV_Laravel_Sidebar -->
      <ins class="adsbygoogle"
           style="display:block"
           data-ad-client="ca-pub-0429292532295045"
           data-ad-slot="8839999192"
           data-ad-format="auto"
           data-full-width-responsive="true"></ins>
      <script>
           (adsbygoogle = window.adsbygoogle || []).push({});
      </script>
    </div>
  </div>
</div>

@endsection
