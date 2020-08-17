@extends('layout')
@section('title', $post['title'] . ' | Blog')
@section('navhome', 'slash')

@section('main')
<div id="fb-root"></div>
<script async defer cookie-consent="functionality" crossorigin="anonymous" src="https://connect.facebook.net/en_US/sdk.js#xfbml=1&version=v8.0" nonce="oiR10rcH"></script>
<div class="blog-item single">
  <h2 class="title">
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
  </div>
  <div class="share">
    <span class="share-header">Share:</span>
    <div class="social-icons px-2">
      {{-- <a href="https://www.facebook.com/dialog/share?display=popup&href=https%3A%2F%2Fdevelopers.facebook.com%2Fdocs%2F&redirect_uri=https%3A%2F%2Fdevelopers.facebook.com%2Ftools%2Fexplore">
        <i class="fab fa-facebook"></i>
      </a> --}}
      <a href="https://twitter.com/share?ref_src=twsrc%5Etfw" target="_blank" class="twitter-share-button" data-show-count="false">
        <i class="fab fa-twitter"></i>
      </a>
        {{-- <script async src="https://platform.twitter.com/widgets.js" charset="utf-8"> --}}
        {{-- </script> --}}
      {{-- <a href="https://twitter.com/jasondidathing"> --}}
      {{-- </a> --}}
    </div>
  </div>
  <div class="comments">
    <h3>Comments</h3>
    <div
      class="fb-comments"
      data-href="{{ Request::url() }}"
      data-lazy="true"
      data-numposts="10"
      data-width="100%"
    />
  </div>
@if (env('APP_DEBUG'))
  <pre>
  {{-- @json($post, JSON_PRETTY_PRINT) --}}
  {{ json_encode ($post, JSON_PRETTY_PRINT) }}
  </pre>
@endif

</div>

@endsection
