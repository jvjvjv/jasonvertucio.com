@extends('layout')
@section('title', 'Blog')
@section('navhome', 'slash')

@section('main')

  @if (sizeof($list) > 0)
  <div class="blog-list card-columns my-5">
    @foreach ($list as $post) 
    <div class="card">
      @if ($post['featured_image'])
        <a href="/blog/{{ $post['slug'] }}" class="card-link">
          <img class="card-img-top" alt="{{ $post['featured_image_caption'] }}" src="{{ $post['featured_image'] }}">
        </a>
        @if ($post['featured_image_caption'])
          <p class="featured-caption text-center text-lowercase">{!! $post['featured_image_caption'] !!}</p>
        @endif
      @else
      <div class="no-img-provided">
        <a href="/blog/{{ $post['slug'] }}" class="card-link">
          {!! $post['content'] !!}
        </a>
      </div>
      @endif
      <div class="card-body">
        <h5 class="card-title">
          <a href="/blog/{{ $post['slug'] }}">{{ $post['title'] }}</a>
        </h5>
        @if ($post['excerpt'])
        <p class="excerpt">{!! $post['excerpt'] !!}</p>
        @else
        @endif
        {{-- @if (env('APP_DEBUG'))
        <pre>{{ json_encode ($post, JSON_PRETTY_PRINT) }}</pre>
        @endif --}}
      </div>
      <div class="card-footer text-muted">
        <a href="/blog/{{ $post['slug'] }}" class="card-link">Read</a>
      </div>
    </div>
    @endforeach
  </div>
  @else
  <h1>Oh. There aren't any posts.</h1>
@endif

@endsection