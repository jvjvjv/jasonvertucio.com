@extends('layout')

@section('title', 'Sign in')

@section('main')
    <div class="flex min-h-[70vh] items-center justify-center px-4 py-12">
        <div class="w-full max-w-md">
            <div class="mb-8 text-center">
                <h1 class="mb-2 font-heading text-4xl text-secondary">Welcome Back</h1>
                <p class="text-dark/70">Sign in to jasonvertucio.com</p>
            </div>

            <div class="rounded-lg border border-gray-200 bg-white p-8">
                @if ($errors->any())
                    <div class="mb-6 rounded border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">
                        @foreach ($errors->all() as $error)
                            <p>{{ $error }}</p>
                        @endforeach
                    </div>
                @endif

                @if ($bspdxAvailable)
                    <a
                        href="{{ route('auth.bspdx.redirect') }}"
                        class="flex w-full items-center justify-center rounded-lg bg-primary px-6 py-4 font-heading text-xl text-white transition-colors hover:bg-primary/90 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2"
                    >
                        Continue to bspdx.com
                    </a>

                    <p class="mt-6 text-center text-sm text-dark/60">
                        You sign in on bspdx.com, where your password and two-factor settings live, and come straight back here.
                    </p>
                @else
                    <p class="text-center text-dark/80">
                        Signing in is turned off right now. Try again later.
                    </p>
                @endif
            </div>
        </div>
    </div>
@endsection
