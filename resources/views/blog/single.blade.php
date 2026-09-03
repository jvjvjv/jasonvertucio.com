@extends('layout')
@section('title', $post['title'] . ' | Blog')
@section('navhome', 'slash')

@section('main')

<div class="max-w-7xl mx-auto px-4">
  <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    <div class="blog lg:col-span-2">

      <div class="bg-white rounded-lg shadow-md p-6">
        <h2 class="blog__title text-4xl font-bold mt-3 mb-5">
          {{ $post['title'] }}
        </h2>

        <div class="blog__body prose max-w-none mb-6">
          {!! $post['body'] !!}
        </div>

        <div class="text-sm text-gray-600 border-t border-gray-200 pt-4 mb-6">
          <span class="inline-block mr-4">
            <strong>Published:</strong> {{ $post['published_at']->format('m/d/Y g:ia') }}
          </span>
          @ifcanvasauthenticated
          <a href="/{{ config('canvas.path') }}/posts/{{ $post['id'] }}/stats" class="text-primary hover:text-secondary mr-3">Stats</a>
          <a href="/{{ config('canvas.path') }}/posts/{{ $post['id'] }}/edit" class="text-primary hover:text-secondary">Edit</a>
          @endifcanvasauthenticated
        </div>

        <x-comment-thread :post="$post" />

        @if (env('APP_DEBUG'))
          <pre class="bg-gray-100 p-4 rounded mt-4 overflow-x-auto">
          {{ json_encode ($post, JSON_PRETTY_PRINT) }}
          </pre>
        @endif

        <div class="mt-4">
          <script type="text/javascript">amzn_assoc_ad_type = "banner";amzn_assoc_marketplace = "amazon";amzn_assoc_region = "US";amzn_assoc_placement = "assoc_banner_placement_default";amzn_assoc_banner_type = "ez";amzn_assoc_p = "13";amzn_assoc_width = "468";amzn_assoc_height = "60";amzn_assoc_tracking_id = "pk00m-20";amzn_assoc_linkid = "d7baa57a05d736fce70b74278fcab525";</script><script src="//z-na.amazon-adsystem.com/widgets/q?ServiceVersion=20070822&Operation=GetScript&ID=OneJS&WS=1"></script>
        </div>
      </div>
    </div>
  </div>
</div>

@endsection
