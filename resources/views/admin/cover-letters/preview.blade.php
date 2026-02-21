@extends('layout')

@section('title', 'Preview — ' . $coverLetter->company_name)

@section('main')
    <div class="max-w-3xl mx-auto px-4 py-8">
        <div class="flex items-center gap-4 mb-6">
            <a href="{{ route('admin.cover-letters.index') }}" class="text-sm text-primary hover:underline">
                &larr; Cover Letters
            </a>
            <span class="text-gray-300">/</span>
            <a href="{{ route('admin.cover-letters.edit', $coverLetter) }}" class="text-sm text-primary hover:underline">
                Edit
            </a>
        </div>

        {{-- Cover Letter Preview --}}
        <div class="bg-primary text-white rounded-top-lg px-6 py-4">
            <h1 class="text-3xl font-heading">
                Jason Vertucio
            </h1>
        </div>
        <div class="bg-white border border-gray-200 p-10 rounded-lgcover-letter-body">
            {{-- Date --}}
            <p>{{ $coverLetter->date->format('F j, Y') }}</p>

            {{-- Company Address --}}
            @if($coverLetter->company_address)
                <p class="whitespace-pre-line">{{ $coverLetter->company_address }}</p>
            @endif

            {{-- Greeting --}}
            <p>{{ $coverLetter->greeting }}</p>

            {{-- Message Body --}}
            {!! $messageBodyHtml !!}

            {{-- Closing --}}
            @if($coverLetter->closing)
                <p>{{ $coverLetter->closing }}</p>
            @endif

            {{-- Signature --}}
            @if($coverLetter->signature)
                <p><strong>{{ $coverLetter->signature }}</strong></p>
            @endif
        </div>
    </div>

    {{-- FAB Download --}}
    @if($coverLetter->docxExists() || $coverLetter->pdfExists())
        <div x-data="{ open: false }" class="fixed bottom-6 right-6 flex flex-col-reverse items-end gap-2">
            {{-- Options grow upward above the FAB --}}
            <div x-show="open" x-transition class="flex flex-col items-end gap-2">
                @if($coverLetter->docxExists())
                    <a href="{{ route('admin.cover-letters.download.docx', $coverLetter) }}"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-white border border-gray-300 text-gray-800 text-sm font-medium rounded-full hover:bg-gray-50 transition-colors"
                        title="Download DOCX">
                        <i class="fa-classic fa-file-word text-blue-600"></i>
                        Word Document
                    </a>
                @endif

                @if($coverLetter->pdfExists())
                    <a href="{{ route('admin.cover-letters.download.pdf', $coverLetter) }}"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-white border border-gray-300 text-gray-800 text-sm font-medium rounded-full hover:bg-gray-50 transition-colors"
                        title="Download PDF">
                        <i class="fa-classic fa-file-pdf text-red-600"></i>
                        PDF
                    </a>
                @endif
            </div>

            {{-- FAB toggle button --}}
            <button
                @click="open = !open"
                class="fab-download"
                :title="open ? 'Close' : 'Download'"
                :aria-label="open ? 'Close download menu' : 'Download cover letter'">
                <i class="fa-solid text-xl transition-transform duration-200"
                    :class="open ? 'fa-xmark' : 'fa-download'"></i>
            </button>
        </div>
    @endif
@endsection

@push('styles')
@vite(['resources/css/cover-letter.css'])
@endpush
