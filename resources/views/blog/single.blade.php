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
    By: {{ $post->author ['name'] }}
    | Published: {{ $post['publish_date']->format('m/d/Y g:ia') }} 
  <div>
@if (env('APP_DEBUG'))
  <pre>
  {{-- @json($post, JSON_PRETTY_PRINT) --}}
  {{ json_encode ($post, JSON_PRETTY_PRINT) }}
  </pre>
@endif

</div>

@endsection