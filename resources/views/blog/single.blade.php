@extends('layout')
@section('title', $post['title'] . ' | Blog')
@section('navhome', 'slash')

@section('main')

<div class="container">
  <div class="row">
    <div class="col-sm-8">

      <div class="blog-item single">
        <h2 class="title mt-3 mb-5">
          {{ $post['title'] }}
        </h2>

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
              data-colorscheme="dark"
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
            data-colorscheme="dark"
            data-numposts="10"
            data-width="100%"></div>
        </div>
        @if (env('APP_DEBUG'))
          <pre>
          {{-- @json($post, JSON_PRETTY_PRINT) --}}
          {{ json_encode ($post, JSON_PRETTY_PRINT) }}
          </pre>
        @endif
        <div class="xc449bad4854773ff" data-zone="f219238022054560a077e2da484e0cb3" style="width:468px;height:60px;display: inline-block;margin: 0 auto">
          <div id="amzn-assoc-ad-edb23f49-7cb1-4556-ad28-fbb495f029ea"></div><script async src="//z-na.amazon-adsystem.com/widgets/onejs?MarketPlace=US&adInstanceId=edb23f49-7cb1-4556-ad28-fbb495f029ea"></script>
        </div>
      </div>
    </div>
    <div class="col-sm-4">
      <h2 class="mt-3 mb-5">
        <span style="opacity:0;">
          I'm just here for spacing.
        </span>
      </h2>
      <a class="twitter-timeline"
        data-dnt="true"
        data-theme="light"
        data-tweet-limit="5"
        href="https://twitter.com/jasondidathing?ref_src=twsrc%5Etfw">
        Tweets by jasondidathing
      </a>
      <script async src="https://platform.twitter.com/widgets.js" charset="utf-8">
      </script>
    </div>
  </div>
</div>

@endsection
