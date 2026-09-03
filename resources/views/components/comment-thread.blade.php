@php
    $tree = $tree();
    $count = $visibleCount();
@endphp

<section id="comments" class="comments mt-6 border-t border-gray-200 pt-6">
    <h3 class="text-2xl font-bold mb-4">
        Comments
        @if ($count > 0)
            <span class="text-base font-normal text-gray-600">({{ $count }})</span>
        @endif
    </h3>

    @if (session('comment_posted'))
        <p class="mb-4 rounded border border-green-300 bg-green-50 px-4 py-3 text-green-800">
            Thanks — your comment is posted.
        </p>
    @endif

    @error('post')
        <p class="mb-4 rounded border border-red-300 bg-red-50 px-4 py-3 text-red-800">{{ $message }}</p>
    @enderror

    @if ($tree->isEmpty())
        <p class="text-gray-600">No comments yet. Be the first to say something.</p>
    @else
        <ol class="space-y-4 list-none p-0">
            @foreach ($tree as $node)
                <x-comment-node :comment="$node['comment']"
                                :children="$node['children']"
                                :slug="$post->slug" />
            @endforeach
        </ol>
    @endif

    <x-comment-form :slug="$post->slug" />
</section>
