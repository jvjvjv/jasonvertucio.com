@extends('layout')

@section('title', 'Download Resume')

@section('meta')
<meta name="robots" content="noindex, nofollow">
@endsection

@section('main')
<div class="max-w-2xl mx-auto px-4 py-16 text-center">
    @if(!$docx_exists && !$pdf_exists)
        <p class="text-lg">No resume files are currently available for download.</p>
    @else
        <div class="flex flex-col gap-4 items-center">
            @if($docx_exists)
                <a href="{{ route('resume.download.docx') }}"
                   class="inline-block bg-primary text-white px-8 py-4 text-lg font-semibold rounded hover:opacity-90 transition-opacity w-full max-w-md">
                    Download Word Document
                </a>
            @endif

            @if($pdf_exists)
                <a href="{{ route('resume.download.pdf') }}"
                   class="inline-block bg-primary text-white px-8 py-4 text-lg font-semibold rounded hover:opacity-90 transition-opacity w-full max-w-md">
                    Download PDF
                </a>
            @endif
        </div>
    @endif
</div>
@endsection
