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
                        {{ $comment->parent->isVisible() ? $comment->parent->name : 'a removed comment' }}
                    </a>
                </p>
            @endif

            <header class="mb-2 flex flex-wrap items-baseline gap-x-2">
                <span class="font-semibold">{{ $comment->name }}</span>
                <time datetime="{{ $comment->created_at->toIso8601String() }}" class="text-sm text-gray-600">
                    {{ $comment->created_at->format('M j, Y g:ia') }}
                </time>
                @if ($comment->isEdited())
                    <time datetime="{{ $comment->edited_at->toIso8601String() }}"
                          title="Edited {{ $comment->edited_at->format('M j, Y g:ia') }}"
                          class="text-sm text-gray-600">(edited)</time>
                @endif
            </header>

            <div class="whitespace-pre-line">{{ $comment->message }}</div>

            @if ($comment->acceptsReplies() || $canManage())
                {{-- An open control takes the whole row, so its form is not
                     squeezed beside the other summaries. --}}
                <div class="mt-2 flex flex-wrap items-start gap-x-4 gap-y-2">
                    @if ($comment->acceptsReplies())
                        <details class="open:w-full">
                            <summary class="cursor-pointer text-sm text-primary hover:text-secondary">Reply</summary>
                            <x-comment-form :slug="$slug" :parent-id="$comment->id" compact />
                        </details>
                    @endif

                    @if ($canManage())
                        <details class="open:w-full" @if ($isEditTarget()) open @endif>
                            <summary class="cursor-pointer text-sm text-primary hover:text-secondary focus-visible:outline-2 focus-visible:outline-primary">Edit</summary>
                            <form method="POST"
                                  action="{{ route('comments.update', [$slug, $comment]) }}"
                                  class="mt-3">
                                @csrf
                                @method('PUT')
                                <input type="hidden" name="editing" value="{{ $comment->id }}">

                                <label for="edit-message-{{ $comment->id }}" class="block text-sm font-medium mb-1">
                                    Edit comment
                                </label>
                                <textarea id="edit-message-{{ $comment->id }}"
                                          name="message"
                                          rows="3"
                                          required
                                          maxlength="5000"
                                          class="w-full rounded border border-gray-300 px-3 py-2 focus-visible:outline-2 focus-visible:outline-primary">{{ $isEditTarget() ? old('message') : $comment->message }}</textarea>
                                @if ($isEditTarget())
                                    @error('message', 'commentEdit')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                                @endif

                                <button type="submit"
                                        class="mt-3 rounded bg-primary px-4 py-2 font-semibold text-white hover:bg-secondary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary">
                                    Save
                                </button>
                            </form>
                        </details>

                        {{-- Opening the disclosure is the confirmation step, so
                             deleting needs no script. --}}
                        <details class="open:w-full">
                            <summary class="cursor-pointer text-sm text-red-700 hover:text-red-900 focus-visible:outline-2 focus-visible:outline-red-700">Delete</summary>
                            <form method="POST"
                                  action="{{ route('comments.destroy', [$slug, $comment]) }}"
                                  class="mt-3">
                                @csrf
                                @method('DELETE')

                                <p class="text-sm text-gray-600">
                                    This removes the comment for good. Replies to it stay where they are.
                                </p>

                                <button type="submit"
                                        class="mt-3 rounded bg-red-700 px-4 py-2 font-semibold text-white hover:bg-red-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-700">
                                    Delete comment
                                </button>
                            </form>
                        </details>
                    @endif
                </div>
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
                                :slug="$slug"
                                :viewer="$viewer"
                                :viewer-is-admin="$viewerIsAdmin" />
            @endforeach
        </ol>
    @endif
</li>
