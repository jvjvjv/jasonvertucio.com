@extends('layout')
@section('title', 'Blog')
@section('navhome', 'slash')

@section('main')

  @if (sizeof($list) > 0)
  <div class="blog-list card-columns my-5">
    @foreach ($list as $list_item)
    <div class="card">
      @if ($list_item['featured_image'])
        <a href="/blog/{{ $list_item['slug'] }}" class="card-link">
          <img class="card-img-top" alt="{{ $list_item['featured_image_caption'] }}" src="{{ $list_item['featured_image'] }}">
        </a>
        @if ($list_item['featured_image_caption'])
          <p class="featured-caption text-center text-lowercase">{!! $list_item['featured_image_caption'] !!}</p>
        @endif
      @else
      <div class="no-img-provided">
        <a href="/blog/{{ $list_item['slug'] }}" class="card-link">
          {!! $list_item['content'] !!}
        </a>
      </div>
      @endif
      <div class="card-body">
        <h5 class="card-title">
          <a href="/blog/{{ $list_item['slug'] }}">{{ $list_item['title'] }}</a>
        </h5>
        @if ($list_item['excerpt'])
        <p class="excerpt">{!! $list_item['excerpt'] !!}</p>
        @else
        @endif
        <p class="text-small">
          <small>
            {{ $list_item['published_at']->diffForHumans() }} | {{ $list_item->readTime }}
          </small>
        </p>
        {{-- @if (env('APP_DEBUG'))
        <pre>{{ json_encode ($list_item, JSON_PRETTY_PRINT) }}</pre>
        @endif --}}
      </div>
      <div class="card-footer text-muted">
        <a href="/blog/{{ $list_item['slug'] }}" class="card-link">Read</a>
        @ifcanvasauthenticated
        <a href="/canvas/posts/{{ $list_item['id'] }}/stats" class="card-link">Stats</a>
        <a href="/canvas/posts/{{ $list_item['id'] }}/edit" class="card-link">Edit</a>
        @endifcanvasauthenticated
      </div>
    </div>
    @endforeach
  </div>
  @else
  <h1>Oh. There aren't any posts.</h1>
@endif

@endsection
