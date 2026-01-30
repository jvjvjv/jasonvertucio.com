@extends('layout')

@section('title', 'Resume Access')

@section('meta')
<meta name="robots" content="noindex, nofollow">
@endsection

@section('main')
<div class="max-w-md mx-auto px-4 py-16">
    <div class="bg-white rounded-lg shadow-md border border-gray-200 p-8">
        <h1 class="text-2xl font-heading font-bold text-primary mb-2">Resume Access</h1>
        <p class="text-sm text-gray-600 mb-6">
            Enter your 6-character access code to view the resume.
        </p>

        @if(session('error'))
            <div class="mb-6 p-4 bg-red-100 border border-red-400 text-red-700 rounded-lg text-sm">
                {{ session('error') }}
            </div>
        @endif

        <form action="{{ route('resume.index') }}" method="GET" class="space-y-4">
            <div>
                <label for="code" class="block text-sm font-medium text-gray-700 mb-2">
                    Access Code
                </label>
                <input type="text"
                       name="code"
                       id="code"
                       required
                       maxlength="6"
                       minlength="6"
                       pattern="[A-Za-z0-9]{6}"
                       placeholder="ABC123"
                       autocomplete="off"
                       class="w-full px-4 py-3 border border-gray-300 rounded-md text-center text-lg font-mono uppercase tracking-wider focus:outline-none focus:ring-2 focus:ring-primary/50 focus:border-primary"
                       style="text-transform: uppercase;">
                @error('code')
                    <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <button type="submit"
                    class="w-full px-6 py-3 bg-primary text-white font-medium rounded-md hover:bg-primary/90 transition-colors">
                Access Resume
            </button>
        </form>

        <div class="mt-6 pt-6 border-t border-gray-200 text-center">
            <p class="text-sm text-gray-500">
                Already have an account?
                <a href="{{ route('login') }}" class="text-primary hover:underline">Sign in</a>
            </p>
        </div>
    </div>
</div>

<script>
    // Auto-uppercase as user types
    document.getElementById('code').addEventListener('input', function(e) {
        e.target.value = e.target.value.toUpperCase();
    });
</script>
@endsection
