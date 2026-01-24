@extends('layout')

@section('title', 'Downloading Resume')

@section('meta')
<meta name="robots" content="noindex, nofollow">
@endsection

@section('main')
    <div class="min-h-[60vh] flex items-center justify-center">
        <div class="text-center max-w-lg px-4">
            <h1 class="text-4xl font-heading font-semibold text-secondary mb-4">Jason's resume will download shortly.</h1>
            <p id="status-message" class="text-dark text-lg mb-4">Preparing your document...</p>
            <p class="text-gray-500">If the file cannot be downloaded, please contact your administrator.</p>
        </div>
    </div>

    {{-- Embedded data for JavaScript --}}
    <script id="template-data" type="application/json">@json(['template' => $templateBase64])</script>
    <script id="resume-data" type="application/json">@json($resumeData)</script>
    <script id="app-config" type="application/json">@json(['debug' => config('app.debug')])</script>

    {{-- Error display container (hidden by default) --}}
    <div id="error-details" class="hidden mt-6 mx-auto max-w-2xl text-left bg-red-50 border border-red-200 rounded-lg p-4">
        <h3 class="text-red-800 font-bold mb-2">Error Details</h3>
        <pre id="error-content" class="text-red-700 text-sm whitespace-pre-wrap overflow-x-auto"></pre>
    </div>
@endsection

@push('styles')
@vite(['resources/css/resume.css'])
@endpush

@push('scripts')
    @vite(['resources/js/resume.js'])
    <script type="module">
        document.addEventListener('DOMContentLoaded', () => {
            const templateData = JSON.parse(document.getElementById('template-data').textContent);
            const resumeData = JSON.parse(document.getElementById('resume-data').textContent);
            const appConfig = JSON.parse(document.getElementById('app-config').textContent);

            // Call the global function exposed by resume.js
            if (typeof window.generateAndDownloadResume === 'function') {
                window.generateAndDownloadResume(templateData.template, resumeData, appConfig.debug);
            } else {
                document.getElementById('status-message').textContent = 'Error: Resume generator not loaded.';
            }
        });
    </script>
@endpush
