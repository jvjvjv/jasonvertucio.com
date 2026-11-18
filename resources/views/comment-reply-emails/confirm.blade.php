@extends('layout')
@section('title', 'Reply emails')

@section('main')
    <div class="mx-auto max-w-xl px-4 py-6 sm:py-12">
        <section class="rounded-lg border border-gray-200 bg-white p-6 sm:p-8" aria-labelledby="reply-emails-heading">
            <h1 id="reply-emails-heading" class="font-heading text-2xl font-bold leading-tight text-primary sm:text-3xl">
                @switch($state)
                    @case('confirmed') Reply emails confirmed @break
                    @case('unavailable') This comment can't get reply emails @break
                    @default Confirm reply emails
                @endswitch
            </h1>

            @if ($postTitle)
                <p class="mt-4 border-l-[3px] border-secondary pl-4 font-prose text-lg italic leading-snug text-gray-800">
                    {{ $postTitle }}
                </p>
            @endif

            @switch($state)
                @case('confirmed')
                    <p class="mt-4 text-gray-700" role="status">
                        We'll email you when someone replies to your comment. Every email has a link to stop them.
                    </p>
                    @break
                @case('unavailable')
                    <p class="mt-4 text-gray-700">
                        The comment has been removed, or reply emails were turned off for it.
                    </p>
                    @break
                @default
                    <p class="mt-4 text-gray-700">
                        You asked to be emailed when someone replies to your comment on this post. Confirm and we'll start sending them.
                    </p>
                    <form method="POST" action="{{ $action }}" class="mt-6">
                        @csrf
                        <button type="submit"
                                class="rounded bg-primary px-4 py-2 font-semibold text-white hover:bg-secondary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary">
                            Confirm reply emails
                        </button>
                    </form>
            @endswitch

            @if ($postUrl && $state !== 'unavailable')
                <p class="mt-6 border-t border-gray-200 pt-4 text-sm">
                    <a href="{{ $postUrl }}" class="font-semibold text-primary underline-offset-2 hover:text-secondary hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary">Back to your comment</a>
                </p>
            @endif
        </section>
    </div>
@endsection
