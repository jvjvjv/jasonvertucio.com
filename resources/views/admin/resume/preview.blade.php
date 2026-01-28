@extends('layout')

@section('title', 'Resume Preview')

@section('meta')
<meta name="robots" content="noindex, nofollow">
@endsection

@section('main')
<div class="max-w-4xl mx-auto px-4 py-8">
    {{-- Admin Header --}}
    <div class="flex items-center justify-between mb-6 print:hidden">
        <div>
            <a href="{{ route('admin.resume.editor') }}" class="text-sm text-primary hover:underline">&larr; Back to Editor</a>
            <h1 class="text-2xl font-heading font-bold text-primary mt-2">Resume Preview</h1>
        </div>
        <div class="flex items-center gap-4">
            <span class="px-3 py-1 text-sm font-medium bg-gray-100 text-gray-700 rounded-full">
                Version: {{ $version }}
            </span>
        </div>
    </div>

    @if(session('success'))
        <div class="mb-6 p-4 bg-green-100 border border-green-400 text-green-700 rounded-lg print:hidden">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="mb-6 p-4 bg-red-100 border border-red-400 text-red-700 rounded-lg print:hidden">
            {{ session('error') }}
        </div>
    @endif

    {{-- DOCX Status --}}
    <div class="mb-6 p-4 rounded-lg border print:hidden {{ $docxExists ? 'bg-green-50 border-green-200' : 'bg-yellow-50 border-yellow-200' }}">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                @if($docxExists)
                    <i class="fa-solid fa-check-circle text-green-600 text-xl"></i>
                    <div>
                        <p class="font-medium text-green-800">DOCX Ready</p>
                        <p class="text-sm text-green-600">Version {{ $version }} is available for download.</p>
                    </div>
                @else
                    <i class="fa-solid fa-exclamation-triangle text-yellow-600 text-xl"></i>
                    <div>
                        <p class="font-medium text-yellow-800">DOCX Not Generated</p>
                        <p class="text-sm text-yellow-600">Generate a DOCX to make version {{ $version }} downloadable.</p>
                    </div>
                @endif
            </div>
            <form action="{{ route('admin.resume.generate') }}" method="POST" x-data="{ generating: false }" @submit="generating = true">
                @csrf
                <button type="submit"
                        :disabled="generating"
                        class="px-4 py-2 bg-primary text-white font-medium rounded-md hover:bg-primary/90 transition-colors disabled:opacity-50 flex items-center gap-2">
                    <i class="fa-solid" :class="generating ? 'fa-spinner fa-spin' : 'fa-file-word'"></i>
                    <span x-text="generating ? 'Generating...' : '{{ $docxExists ? 'Regenerate Documents' : 'Generate Documents' }}'"></span>
                </button>
            </form>
        </div>
    </div>

    {{-- Resume Content --}}
    <div class="resume-container bg-white rounded-lg shadow-md border border-gray-200 p-8 print:shadow-none print:border-none print:p-0">
        {{-- Header with name, title, and summary --}}
        <x-resume.header
            :name="$data['personal']['name']"
            :title="$data['personal']['title']"
            :summary="$data['personal']['summary']"
        />

        {{-- Technical Skills --}}
        <x-resume.skills :skills="$data['skills']" />

        {{-- Experience --}}
        <x-resume.experience :experience="$data['experience']" />

        {{-- Selected Projects --}}
        <x-resume.projects :projects="$data['projects']" />
    </div>
</div>
@endsection

@push('styles')
@vite(['resources/css/resume.css'])
@endpush
