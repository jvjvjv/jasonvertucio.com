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
            <div class="rounded border bg-white">
                <iframe
                    src="{{ route('admin.mail-preview.preview', $mailableClass) }}"
                    style="width: 100%; border: none; display: block; min-height: 600px;"
                    title="Email Preview"
                ></iframe>
            </div>
        @endif
    </div>
@endsection
