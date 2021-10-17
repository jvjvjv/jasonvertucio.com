@extends('layout')
@section('title', 'Blog Topics')
@section('navhome', 'slash')

@section('main')

  @if (sizeof($list) > 0)
  <div class="blog-list card-columns my-5">
    @foreach ($list as $list_item)
    <div class="card">
      <div class="card-body">
        <a href="/blog/topics/{{ $list_item['slug'] }}" class="card-link">{{ $list_item->name }}</a>
        <span class="card-link">
          {{ $list_item->posts->count() }} posts
        </span>
        {{-- {{ $list_item }} --}}
      </div>
    </div>
    @endforeach
  </div>
  @else
  <h1>Oh. There aren't any posts.</h1>
@endif

@endsection
