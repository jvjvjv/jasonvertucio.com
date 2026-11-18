@extends('layout')
@section('title', 'Reply emails')

@section('main')
    <div class="mx-auto max-w-xl px-4 py-6 sm:py-12">
        <section class="rounded-lg border border-gray-200 bg-white p-6 sm:p-8" aria-labelledby="reply-emails-link-heading">
            <h1 id="reply-emails-link-heading" class="font-heading text-2xl font-bold leading-tight text-primary sm:text-3xl">
                @switch($state)
                    @case('expired') This link has expired @break
                    @case('resent') New link sent @break
                    @case('capped') Too many links today @break
                    @case('already-confirmed') Reply emails are already on @break
                    @case('unavailable') This comment can't get reply emails @break
                    @default This link isn't valid
                @endswitch
            </h1>

            @if ($postTitle)
                <p class="mt-4 border-l-[3px] border-secondary pl-4 font-prose text-lg italic leading-snug text-gray-800">
                    {{ $postTitle }}
                </p>
            @endif

            @switch($state)
                @case('expired')
                    <p class="mt-4 text-gray-700">
                        Confirmation links work for {{ $expiresInDays }} {{ \Illuminate\Support\Str::plural('day', $expiresInDays) }}. We can email a new one to {{ $maskedAddress }}.
                    </p>
                    <form method="POST" action="{{ $resendUrl }}" class="mt-6">
                        @csrf
                        <button type="submit"
                                class="rounded bg-primary px-4 py-2 font-semibold text-white hover:bg-secondary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary">
                            Send me a new link
                        </button>
                    </form>
                    @break
                @case('resent')
                    <p class="mt-4 text-gray-700" role="status">
                        Check {{ $maskedAddress }} for a new confirmation link.
                    </p>
                    @break
                @case('capped')
                    <p class="mt-4 text-gray-700" role="status">
                        {{ $maskedAddress }} has already been sent as many confirmation links as we send in a day. Try again tomorrow.
                    </p>
                    @break
                @case('already-confirmed')
                    <p class="mt-4 text-gray-700">
                        You're already getting emails about replies to this comment, so there's nothing to confirm.
                    </p>
                    @break
                @case('unavailable')
                    <p class="mt-4 text-gray-700">
                        The comment has been removed, or reply emails were turned off for it.
                    </p>
                    @break
                @default
                    <p class="mt-4 text-gray-700">
                        It may have been copied incompletely. Open it again from the email, making sure you have the whole link.
                    </p>
            @endswitch

            @if ($postUrl)
                <p class="mt-6 border-t border-gray-200 pt-4 text-sm">
                    <a href="{{ $postUrl }}" class="font-semibold text-primary underline-offset-2 hover:text-secondary hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary">Back to your comment</a>
                </p>
            @endif
        </section>
    </div>
@endsection
