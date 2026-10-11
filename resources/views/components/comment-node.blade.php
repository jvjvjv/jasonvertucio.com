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

            <div class="comment-body whitespace-pre-line">{{ $comment->message }}</div>

            @if ($comment->acceptsReplies() || $canManage() || $canModerate)
                {{-- Native disclosures that share a name, so only one is open
                     at a time. blog.css draws each summary as a button and the
                     open one as a panel that takes the row's place; the summary
                     then reads as that panel's title and its Cancel. --}}
                <div class="comment-actions">
                    @if ($comment->acceptsReplies())
                        <details name="comment-{{ $comment->id }}" class="comment-action comment-action--reply">
                            <summary data-panel-title="Reply to {{ $comment->name }}">Reply</summary>
                            <x-comment-form :slug="$slug" :parent-id="$comment->id" compact />
                        </details>
                    @endif

                    @if ($canManage())
                        <details name="comment-{{ $comment->id }}" class="comment-action comment-action--edit" @if ($isEditTarget()) open @endif>
                            <summary data-panel-title="Edit comment">Edit</summary>
                            <form method="POST"
                                  action="{{ route('comments.update', [$slug, $comment]) }}"
                                  class="mt-2">
                                @csrf
                                @method('PUT')
                                <input type="hidden" name="editing" value="{{ $comment->id }}">

                                <label for="edit-message-{{ $comment->id }}" class="sr-only">
                                    Edit comment
                                </label>
                                <textarea id="edit-message-{{ $comment->id }}"
                                          name="message"
                                          rows="4"
                                          required
                                          maxlength="5000"
                                          class="w-full rounded border border-gray-300 px-3 py-2 focus-visible:outline-2 focus-visible:outline-primary">{{ $isEditTarget() ? old('message') : $comment->message }}</textarea>
                                @if ($isEditTarget())
                                    @error('message', 'commentEdit')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                                @endif

                                <button type="submit"
                                        class="mt-2 rounded bg-primary px-3 py-1.5 text-sm font-semibold text-white hover:bg-secondary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary">
                                    Save changes
                                </button>
                            </form>
                        </details>
                    @endif

                    @if ($canModerate || $canManage())
                        <span class="comment-actions__gap" aria-hidden="true"></span>
                    @endif

                    @if ($canModerate)
                        <details name="comment-{{ $comment->id }}" class="comment-action comment-action--spam comment-action--confirm">
                            <summary data-panel-title="Mark this comment as spam?">Mark as spam</summary>
                            <form method="POST"
                                  action="{{ route('comments.spam', [$slug, $comment]) }}"
                                  class="mt-1 flex flex-wrap items-center justify-between gap-3">
                                @csrf
                                <p class="text-sm text-gray-700">It is hidden from this post. You can restore it from the comments queue.</p>
                                <button type="submit"
                                        class="rounded border border-red-300 bg-white px-3 py-1.5 text-sm font-semibold text-red-800 hover:bg-red-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-700">
                                    Mark as spam
                                </button>
                            </form>
                        </details>
                    @endif

                    @if ($canManage())
                        {{-- Opening the disclosure is the confirmation step, so
                             deleting needs no script. --}}
                        <details name="comment-{{ $comment->id }}" class="comment-action comment-action--delete comment-action--confirm">
                            <summary data-panel-title="Delete this comment?">Delete</summary>
                            <form method="POST"
                                  action="{{ route('comments.destroy', [$slug, $comment]) }}"
                                  class="mt-1 flex flex-wrap items-center justify-between gap-3">
                                @csrf
                                @method('DELETE')

                                <p class="text-sm text-gray-700">It cannot be brought back. Replies to it stay where they are.</p>

                                <button type="submit"
                                        class="rounded bg-red-700 px-3 py-1.5 text-sm font-semibold text-white hover:bg-red-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-700">
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
                                :viewer-is-admin="$viewerIsAdmin"
                                :can-moderate="$canModerate" />
            @endforeach
        </ol>
    @endif
</li>
