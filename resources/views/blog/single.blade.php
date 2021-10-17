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
          {!! $post['body'] !!}
        </div>
        <div class="list-meta">
          {{-- @if ($post->author)
            <strong>By:</strong> {{ $post->author['name'] }}
            |
          @endif --}}
          <span class="card-link">
            <strong>Published:</strong> {{ $post['published_at']->format('m/d/Y g:ia') }}
          </span>
          @ifcanvasauthenticated
          <a href="/{{ config('canvas.path') }}/posts/{{ $post['id'] }}/stats" class="card-link">Stats</a>
          <a href="/{{ config('canvas.path') }}/posts/{{ $post['id'] }}/edit" class="card-link">Edit</a>
          @endifcanvasauthenticated
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
            data-order-by="social"
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
        {{-- <div class="xc449bad4854773ff" data-zone="f219238022054560a077e2da484e0cb3" style="width:468px;height:60px;display: inline-block;margin: 0 auto"><div id="amzn-assoc-ad-edb23f49-7cb1-4556-ad28-fbb495f029ea"></div><script async src="//z-na.amazon-adsystem.com/widgets/onejs?MarketPlace=US&adInstanceId=edb23f49-7cb1-4556-ad28-fbb495f029ea"></script></div> --}}
        <div class="alignleft"><script type="text/javascript">amzn_assoc_ad_type = "banner";amzn_assoc_marketplace = "amazon";amzn_assoc_region = "US";amzn_assoc_placement = "assoc_banner_placement_default";amzn_assoc_banner_type = "ez";amzn_assoc_p = "13";amzn_assoc_width = "468";amzn_assoc_height = "60";amzn_assoc_tracking_id = "pk00m-20";amzn_assoc_linkid = "d7baa57a05d736fce70b74278fcab525";</script><script src="//z-na.amazon-adsystem.com/widgets/q?ServiceVersion=20070822&Operation=GetScript&ID=OneJS&WS=1"></script></div>
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
