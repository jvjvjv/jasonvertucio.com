@if ($post && $shouldDisplay())
<section class="site-section" id="blog">
    <div class="w-full">
        <h2 class="3xl font-heading text-3xl uppercase mb-5 font-bold">Latest Blog</h2>
        <h3 class="font-heading text-xl mb-2 font-bold">{{ $post->title }}</h3>
        @if ($post->featured_image)
            <div>
                <img src="{{ $post->featured_image }}" class="w-full" alt="{{ $post->featured_image_caption }}">
                @if ($post->featured_image_caption)
                    <p class="text-center">{!! $post->featured_image_caption !!}</p>
                @endif
            </div>
        @endif
        @if ($post->summary)
            <p>{{ $post->summary }}</p>
        @else
            <p>(I am supposed to enter a sort of flavor text on these things but I didn't for this one. Oh
                well.)</p>
        @endif
        <p>
            {{ $post->published_at->diffForHumans() }}
        </p>
        <a class="my-2 inline-block font-semibold text-center whitespace-nowrap align-middle select-none border bg-white border-primary px-4 py-3 text-lg leading-6 rounded transition-color text-primary hover:bg-primary hover:text-white focus:bg-primary focus:text-white transition-all duration-300"
            href="/blog/{{ $post->slug }}">Read</a>
        @ifcanvasauthenticated
        <a class="my-2 inline-block font-semibold text-center whitespace-nowrap align-middle select-none border bg-white border-primary px-4 py-3 text-lg leading-6 rounded transition-color text-primary focus:bg-primary focus:text-white hover:bg-primary hover:text-white ml-2 transition-all duration-300"
            href="/{{ config('canvas.path') }}/posts/{{ $post->id }}/edit">Edit</a>
        @endifcanvasauthenticated
    </div>
</section>

<hr class="m-0 border-0 border-t border-gray-200">
@endif
