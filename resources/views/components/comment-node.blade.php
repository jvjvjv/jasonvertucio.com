@php
    $indent = ['ml-0', 'ml-4 sm:ml-8', 'ml-8 sm:ml-16'][$visualDepth()] ?? 'ml-8 sm:ml-16';
@endphp

<li id="comment-{{ $comment->id }}" class="{{ $comment->depth > 0 ? $indent : '' }}">
    @if ($comment->isVisible())
        <article class="rounded border border-gray-200 p-4">
            @if ($needsParentBacklink())
                <p class="mb-2 text-xs text-gray-500">
                    In reply to
                    <a href="#comment-{{ $comment->parent->id }}" class="text-primary hover:text-secondary">
                        {{ $comment->parent->name }}
                    </a>
                </p>
            @endif

            <header class="mb-2 flex flex-wrap items-baseline gap-x-2">
                <span class="font-semibold">{{ $comment->name }}</span>
                <time datetime="{{ $comment->created_at->toIso8601String() }}" class="text-sm text-gray-600">
                    {{ $comment->created_at->format('M j, Y g:ia') }}
                </time>
            </header>

            <div class="whitespace-pre-line">{{ $comment->message }}</div>

            @if ($comment->acceptsReplies())
                <details class="mt-2">
                    <summary class="cursor-pointer text-sm text-primary hover:text-secondary">Reply</summary>
                    <x-comment-form :slug="$slug" :parent-id="$comment->id" compact />
                </details>
            @endif
        </article>
    @else
        <article class="rounded border border-dashed border-gray-300 p-4 text-sm text-gray-500">
            [comment removed]
        </article>
    @endif

    @if ($children->isNotEmpty())
        <ol class="mt-4 space-y-4 list-none p-0">
            @foreach ($children as $child)
                <x-comment-node :comment="$child['comment']"
                                :children="$child['children']"
                                :slug="$slug" />
            @endforeach
        </ol>
    @endif
</li>
