@props(['slug', 'parentId' => null, 'compact' => false])

@php
    $isTarget = (string) old('parent_id') === (string) $parentId;
@endphp

<form method="POST"
      action="{{ route('comments.store', $slug) }}"
      class="{{ $compact ? 'mt-3' : 'mt-6 border-t border-gray-200 pt-6' }}">
    @csrf

    @if ($parentId)
        <input type="hidden" name="parent_id" value="{{ $parentId }}">
    @endif

    {{-- Honeypot. Hidden from people, irresistible to bots. A filled value is
         discarded silently rather than rejected, so a bot learns nothing. --}}
    <div aria-hidden="true" class="absolute left-[-9999px] h-0 w-0 overflow-hidden">
        <label for="website-{{ $parentId ?? 'root' }}">Leave this field empty</label>
        <input type="text"
               id="website-{{ $parentId ?? 'root' }}"
               name="website"
               tabindex="-1"
               autocomplete="off"
               value="">
    </div>

    @unless ($compact)
        <h4 class="text-xl font-bold mb-3">Leave a comment</h4>
    @endunless

    @auth
        <p class="text-sm text-gray-600 mb-3">
            Commenting as <strong>{{ auth()->user()->name }}</strong>.
        </p>
    @else
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-3">
            <div>
                <label for="name-{{ $parentId ?? 'root' }}" class="block text-sm font-medium mb-1">Name</label>
                <input type="text"
                       id="name-{{ $parentId ?? 'root' }}"
                       name="name"
                       value="{{ $isTarget ? old('name') : '' }}"
                       required
                       maxlength="128"
                       class="w-full rounded border border-gray-300 px-3 py-2 focus-visible:outline-2 focus-visible:outline-primary">
                @if ($isTarget)
                    @error('name')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                @endif
            </div>
            <div>
                <label for="email-{{ $parentId ?? 'root' }}" class="block text-sm font-medium mb-1">Email</label>
                <input type="email"
                       id="email-{{ $parentId ?? 'root' }}"
                       name="email"
                       value="{{ $isTarget ? old('email') : '' }}"
                       required
                       maxlength="128"
                       class="w-full rounded border border-gray-300 px-3 py-2 focus-visible:outline-2 focus-visible:outline-primary">
                <p class="mt-1 text-xs text-gray-500">Never published.</p>
                @if ($isTarget)
                    @error('email')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                @endif
            </div>
        </div>
    @endauth

    <div>
        <label for="message-{{ $parentId ?? 'root' }}" class="block text-sm font-medium mb-1">
            {{ $compact ? 'Your reply' : 'Comment' }}
        </label>
        <textarea id="message-{{ $parentId ?? 'root' }}"
                  name="message"
                  rows="{{ $compact ? 3 : 5 }}"
                  required
                  maxlength="5000"
                  class="w-full rounded border border-gray-300 px-3 py-2 focus-visible:outline-2 focus-visible:outline-primary">{{ $isTarget ? old('message') : '' }}</textarea>
        @if ($isTarget)
            @error('message')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
            @error('parent_id')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
        @endif
    </div>

    <button type="submit"
            class="mt-3 rounded bg-primary px-4 py-2 font-semibold text-white hover:bg-secondary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary">
        {{ $compact ? 'Post reply' : 'Post comment' }}
    </button>
</form>
