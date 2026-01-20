@extends('layout')

@section('main')
<div class="min-h-screen flex items-center justify-center px-4 py-12">
    <div class="max-w-md w-full">
        <div class="text-center mb-8">
            <h1 class="text-4xl font-heading text-secondary mb-2">Welcome Back</h1>
            <p class="text-dark/70">Sign in to manage your blog</p>
        </div>

        <div class="bg-white rounded-lg shadow-lg p-8">
            @if ($errors->any())
                <div class="mb-6 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded" role="alert">
                    <ul class="list-disc list-inside text-sm">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('login') }}">
                @csrf

                <div class="mb-6">
                    <label for="email" class="block text-sm font-medium text-dark mb-2">
                        Email Address
                    </label>
                    <input
                        id="email"
                        type="email"
                        name="email"
                        value="{{ old('email') }}"
                        required
                        autofocus
                        autocomplete="email"
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-link focus:border-transparent transition"
                    >
                </div>

                <div class="mb-6">
                    <label for="password" class="block text-sm font-medium text-dark mb-2">
                        Password
                    </label>
                    <input
                        id="password"
                        type="password"
                        name="password"
                        required
                        autocomplete="current-password"
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-link focus:border-transparent transition"
                    >
                </div>

                <div class="mb-6 flex items-center justify-between">
                    <label class="flex items-center">
                        <input
                            type="checkbox"
                            name="remember"
                            class="rounded border-gray-300 text-primary focus:ring-link"
                        >
                        <span class="ml-2 text-sm text-dark">Remember me</span>
                    </label>

                    @if (Route::has('password.request'))
                        <a href="{{ route('password.request') }}" class="text-sm text-link hover:underline">
                            Forgot password?
                        </a>
                    @endif
                </div>

                <button
                    type="submit"
                    class="w-full bg-primary text-white py-3 rounded-lg font-medium hover:bg-primary/90 transition focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary"
                >
                    Sign In
                </button>
            </form>
        </div>
    </div>
</div>
@endsection
