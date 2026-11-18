@extends('layout')
@section('title', 'Comment emails')

@php
    $buttonBase = 'rounded px-4 py-2 font-semibold focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary';
@endphp

@section('main')
    <div class="mx-auto max-w-xl px-4 py-6 sm:py-12">
        <section class="rounded-lg border border-gray-200 bg-white p-6 sm:p-8" aria-labelledby="comment-emails-heading">
            <h1 id="comment-emails-heading" class="font-heading text-2xl font-bold leading-tight text-primary sm:text-3xl">
                @switch($done)
                    @case('comment') Emails stopped for this comment @break
                    @case('address') All comment emails stopped @break
                    @default Stop comment emails
                @endswitch
            </h1>

            @if ($postTitle)
                <p class="mt-4 border-l-[3px] border-secondary pl-4 font-prose text-lg italic leading-snug text-gray-800">
                    {{ $postTitle }}
                </p>
            @endif

            @if ($done === 'address')
                <p class="mt-4 text-gray-700" role="status">
                    {{ $maskedAddress }} won't get any more comment emails. To turn them back on, tick &ldquo;Email me when someone replies&rdquo; on a future comment and confirm it.
                </p>
            @else
                @if ($done === 'comment')
                    <p class="mt-4 text-gray-700" role="status">
                        You won't be emailed about new replies to this comment.
                    </p>
                @else
                    <p class="mt-4 text-gray-700">
                        Emails about this comment go to {{ $maskedAddress }}.
                    </p>

                    <form method="POST" action="{{ $action }}" class="mt-6">
                        @csrf
                        <input type="hidden" name="scope" value="comment">
                        <button type="submit" class="{{ $buttonBase }} bg-primary text-white hover:bg-secondary">
                            Stop emails for this comment
                        </button>
                        <p class="mt-2 text-sm text-gray-600">You'll still hear about replies to other comments you asked about.</p>
                    </form>
                @endif

                <form method="POST" action="{{ $action }}" class="mt-6 border-t border-gray-200 pt-6">
                    @csrf
                    <input type="hidden" name="scope" value="address">
                    <button type="submit" class="{{ $buttonBase }} border border-primary text-primary hover:border-secondary hover:text-secondary">
                        Stop all comment emails
                    </button>
                    <p class="mt-2 text-sm text-gray-600">
                        {{ $maskedAddress }} won't get any comment emails until you ask for them again on a future comment.
                    </p>
                </form>
            @endif

            @if ($postUrl)
                <p class="mt-6 border-t border-gray-200 pt-4 text-sm">
                    <a href="{{ $postUrl }}" class="font-semibold text-primary underline-offset-2 hover:text-secondary hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary">Back to the comment</a>
                </p>
            @endif
        </section>
    </div>
@endsection
