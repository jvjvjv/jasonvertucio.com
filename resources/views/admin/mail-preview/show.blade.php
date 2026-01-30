@extends("layout")

@section("title", "Mail Preview")

@section("main")
    <div class="mx-auto max-w-4xl p-6">
        <a href="{{ route("admin.mail-preview.index") }}" class="mb-4 block text-blue-600 hover:underline">← Back</a>

        <h1 class="mb-2 text-2xl font-bold">{{ $mailable["name"] }}</h1>
        <p class="mb-6 text-gray-600">Subject: {{ $subject ?? "N/A" }}</p>

        @if (isset($error))
            <div class="mb-6 rounded border border-red-200 bg-red-50 p-4">
                <p class="text-red-800"><strong>Error:</strong> {{ $error }}</p>
            </div>
        @else
            <div class="rounded border bg-white p-6">
                @if ($isMarkdown)
                    <div class="prose prose-sm max-w-none">
                        {!! Str::markdown($preview) !!}
                    </div>
                @else
                    {!! $preview !!}
                @endif
            </div>
        @endif
    </div>
@endsection

@push("styles")
<style>
    h1 { font-weight: bold; font-size: 2.5rem; }
    h2 { font-weight: bold; font-size: 2rem; }
    h3 { font-weight: bold; font-size: 1.75rem; }
    h4 { font-weight: bold; font-size: 1.5rem; }
    h5 { font-weight: bold; font-size: 1.25rem; }
    h6 { font-weight: bold; font-size: 1rem; }

    a { text-decoration: underline; color: var(--color-primary);}
    p { margin-block-start: 1rem; margin-block-end: 1rem; }
</style>
@endpush
