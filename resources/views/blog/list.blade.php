@extends('layout')
@section('title', 'Blog')
@section('navhome', 'slash')

@section('main')

@if (sizeof($list) > 0)
<ul class="blog-list">
@foreach ($list as $post) 
<li class="blog-item mb-5">
  <h2 class="title">
    <a href="/blog/{{ $post['slug'] }}">{{ $post['title'] }}</a>
  </h2>
  @if ($post['excerpt'])
  <p class="excerpt">{!! $post['excerpt'] !!}</p>
  @else
  <div class="content">
    {!! $post['content'] !!}
  </div>
  <div class="meta">
  </div>
  @endif
</li>
@endforeach
</ul>
@else
<h1>Oh. There aren't any posts.</h1>
@endif

@endsection