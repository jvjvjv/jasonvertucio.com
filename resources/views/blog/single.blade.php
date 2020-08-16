@extends('layout')
@section('title', $post['title'] . ' | Blog')
@section('navhome', 'slash')

@section('main')

<div class="blog-item single">
  <h2 class="title">
    {{ $post['title'] }}
  </h2>
  <div class="content">
    {!! $post['content'] !!}
  </div>
  <div class="list-meta">
    @if ($post->author)
      By: {{ $post->author['name'] }}
      |
    @endif
    Published: {{ $post['publish_date']->format('m/d/Y g:ia') }} 
  <div>
@if (env('APP_DEBUG'))
  <pre>
  {{-- @json($post, JSON_PRETTY_PRINT) --}}
  {{ json_encode ($post, JSON_PRETTY_PRINT) }}
  </pre>
@endif

  <div class="share">
    <span class="share-header">Share:</span>
    <div class="social-icons">
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

</div>

@endsection