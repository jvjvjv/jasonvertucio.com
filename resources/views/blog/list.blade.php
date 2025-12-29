@extends('layout')
@section('title', 'Blog')
@section('navhome', 'slash')

@section('main')

  @if (sizeof($list) > 0)
  <div class="max-w-7xl mx-auto px-4 my-5">
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
    @foreach ($list as $list_item)
    <div class="bg-white rounded-lg shadow-md overflow-hidden flex flex-col">
      @if ($list_item['featured_image'])
        <a href="/blog/{{ $list_item['slug'] }}" class="block">
          <div class="h-48 bg-cover bg-center" style="background-image: url({{ $list_item['featured_image'] }})">
          </div>
        </a>
      @else
      <div class="h-48 bg-gray-200 p-4 overflow-hidden">
        <a href="/blog/{{ $list_item['slug'] }}" class="block h-full">
          <div class="line-clamp-6 text-sm text-gray-600">
            {!! $list_item['content'] !!}
          </div>
        </a>
      </div>
      @endif
      <div class="p-4 grow flex flex-col">
        <h5 class="text-xl font-bold mb-2">
          <a href="/blog/{{ $list_item['slug'] }}" class="text-dark hover:text-primary">{{ $list_item['title'] }}</a>
        </h5>
        @if ($list_item['excerpt'])
        <p class="text-gray-600 mb-4 grow">{!! $list_item['excerpt'] !!}</p>
        @endif
        <p class="text-sm text-gray-500">
          <small>
            {{ $list_item['published_at']->diffForHumans() }} | {{ $list_item->readTime }}
          </small>
        </p>
      </div>
      <div class="px-4 py-3 bg-gray-50 border-t border-gray-200 text-sm">
        <a href="/blog/{{ $list_item['slug'] }}" class="text-primary hover:text-secondary">Read</a>
        @ifcanvasauthenticated
        <a href="/{{ config('canvas.path') }}/posts/{{ $list_item['id'] }}/stats" class="ml-3 text-primary hover:text-secondary">Stats</a>
        <a href="/{{ config('canvas.path') }}/posts/{{ $list_item['id'] }}/edit" class="ml-3 text-primary hover:text-secondary">Edit</a>
        @endifcanvasauthenticated
      </div>
    </div>
    @endforeach
    </div>
  </div>
  @else
  <div class="max-w-7xl mx-auto px-4 my-5">
    <h1 class="text-4xl font-bold">Oh. There aren't any posts.</h1>
  </div>
@endif

@endsection
