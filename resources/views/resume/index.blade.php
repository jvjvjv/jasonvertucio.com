@extends('layout')

@section('title', 'Resume')

@section('meta')
<meta name="robots" content="noindex, nofollow">
@endsection

@section('main')
<div class="resume-container max-w-4xl mx-auto px-4 py-8">

    @if($candidate)
    <div class="mb-6 p-4 bg-blue-50 border border-blue-200 rounded-lg">
        <p class="font-medium text-blue-900">
            Viewing AI-drafted revision #{{ $candidate['revision_number'] }} ({{ $candidate['status'] }})
        </p>
        @if($candidate['is_stale'])
        <p class="text-sm text-blue-800 mt-1">
            The live resume has changed since this revision was branched. Review carefully before approving.
        </p>
        @endif
        @if($candidate['status'] === 'pending')
        <div class="mt-3 flex gap-2 items-center">
            <form method="POST" action="{{ route('admin.resume.candidates.approve', $candidate['id']) }}" class="flex gap-2 items-center">
                @csrf
                <input type="hidden" name="redirect_to" value="preview">
                <label for="approve-version" class="text-sm text-blue-900">Publish as version</label>
                <input type="text" id="approve-version" name="version" value="{{ $candidate['suggested_version'] }}"
                       pattern="\d{4}\.\d+\.\d+" required
                       class="px-2 py-1 border border-blue-300 rounded text-sm w-32">
                <button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg text-sm font-medium cursor-pointer">
                    Approve
                </button>
            </form>
            <form method="POST" action="{{ route('admin.resume.candidates.reject', $candidate['id']) }}"
                  onsubmit="return confirm('Reject and permanently delete this draft revision? This cannot be undone.');">
                @csrf
                <input type="hidden" name="redirect_to" value="preview">
                <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-lg text-sm font-medium cursor-pointer">
                    Reject
                </button>
            </form>
        </div>
        @endif
    </div>
    @elseif($pendingCandidates->isNotEmpty())
    <div class="mb-6 p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
        <p class="font-medium text-yellow-900">
            {{ $pendingCandidates->count() }} AI-drafted revision{{ $pendingCandidates->count() > 1 ? 's are' : ' is' }} pending review.
        </p>
        <ul class="mt-2 text-sm">
            @foreach($pendingCandidates as $pending)
            <li>
                <a href="{{ route('resume.index', ['revision' => $pending->id]) }}" class="text-primary underline">
                    Review revision #{{ $pending->revision_number }}
                </a>
            </li>
            @endforeach
        </ul>
    </div>
    @endif

    @php
        $revisionLabel = $candidate
            ? 'Revision #'.$candidate['revision_number'].' ('.$candidate['status'].')'
            : (isset($version) && $version ? 'v'.$version : null);
    @endphp

    {{-- Header with name, title, and summary --}}
    <x-resume.header
        :name="$data['personal']['name']"
        :title="$data['personal']['title']"
        :summary="$data['personal']['summary']"
        :revision-label="$revisionLabel"
    />

    {{-- Technical Skills --}}
    <x-resume.skills :skills="$data['skills']" />

    {{-- Experience --}}
    <x-resume.experience :experience="$data['experience']" />

    {{-- Education (only present for authorized viewers or a candidate revision) --}}
    @if(!empty($data['education']))
    <x-resume.education :education="$data['education']" />
    @endif

    {{-- Selected Projects --}}
    <x-resume.projects :projects="$data['projects']" />

    {{-- Print-only message - shows when user tries to print --}}
    <div class="print-message">
        <p>Please visit https://www.jasonvertucio.com/resume to download the resume.</p>
    </div>
</div>

{{-- FAB Download Button --}}
<x-resume.download-fab :docxExists="$docxExists" :pdfExists="$pdfExists" />
@endsection

@push('styles')
@vite(['resources/css/resume.css'])
@endpush
