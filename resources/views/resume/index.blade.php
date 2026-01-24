@extends('layout')

@section('title', 'Resume')

@section('meta')
<meta name="robots" content="noindex, nofollow">
@endsection

@section('main')
<div class="resume-container max-w-4xl mx-auto px-4 py-8">
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

    {{-- Print-only message - shows when user tries to print --}}
    <div class="print-message">
        <p>Please visit https://www.jasonvertucio.com/resume to download the resume.</p>
    </div>
</div>

{{-- FAB Download Button (shown only if user has save-resume permission) --}}
@if($canSave)
<x-resume.download-fab />
@endif
@endsection

@push('styles')
@vite(['resources/css/resume.css'])
@endpush
