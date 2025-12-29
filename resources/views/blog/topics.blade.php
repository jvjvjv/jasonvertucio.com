@extends('layout')
@section('title', 'Blog Topics')
@section('navhome', 'slash')

@section('main')

  @if (sizeof($list) > 0)
  <div class="max-w-7xl mx-auto px-4 my-8">
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
      @foreach ($list as $list_item)
      <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="p-4">
          <a href="/blog/topics/{{ $list_item['slug'] }}" class="text-xl font-semibold text-link hover:underline">{{ $list_item->name }}</a>
          <span class="block text-gray-600 text-sm mt-2">
            {{ $list_item->posts->count() }} posts
          </span>
          {{-- {{ $list_item }} --}}
        </div>
      </div>
      @endforeach
    </div>
  </div>
  @else
  <div class="max-w-7xl mx-auto px-4 my-8">
    <h1 class="text-4xl font-bold">Oh. There aren't any posts.</h1>
  </div>
@endif

@endsection
