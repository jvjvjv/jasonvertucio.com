@extends('layout')

@section('title', 'Resume')

@section('meta')
<meta name="robots" content="noindex, nofollow">
@endsection

@section('main')
<div class="resume-container max-w-4xl mx-auto px-4 py-8" @if(! empty($diff)) data-revision-review @endif>

    {{-- Outcome of an approve/reject posted from the revision banner below --}}
    @if(session('error'))
    <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg text-red-900">
        {{ session('error') }}
    </div>
    @endif
    @if(session('success'))
    <div class="mb-6 p-4 bg-green-50 border border-green-200 rounded-lg text-green-900">
        {{ session('success') }}
    </div>
    @endif

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
        <div class="mt-3 flex flex-wrap gap-2 items-center">
            <form id="approve-candidate" method="POST" action="{{ route('admin.resume.candidates.approve', $candidate['id']) }}" class="flex gap-2 items-center">
                @csrf
                <input type="hidden" name="redirect_to" value="preview">
                <label for="approve-version" class="text-sm text-blue-900">Publish as version</label>
                <input type="text" id="approve-version" name="version" value="{{ $candidate['suggested_version'] }}"
                       pattern="\d{4}\.\d+\.\d+" required
                       class="px-2 py-1 border border-blue-300 rounded text-sm w-32">
            </form>
            {{-- The two actions stay together when the row wraps on a narrow screen --}}
            <div class="flex gap-2 items-center">
                <button type="submit" form="approve-candidate" class="px-4 py-2 bg-primary text-white rounded-lg text-sm font-medium cursor-pointer">
                    Approve
                </button>
                <form method="POST" action="{{ route('admin.resume.candidates.reject', $candidate['id']) }}"
                      onsubmit="return confirm('Reject and permanently delete this draft revision? This cannot be undone.');">
                    @csrf
                    <input type="hidden" name="redirect_to" value="preview">
                    <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-lg text-sm font-medium cursor-pointer">
                        Reject
                    </button>
                </form>
            </div>
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

        // The live resume always carries every section, but a candidate
        // revision is an unvalidated snapshot a persona proposed — the whole
        // point of this page is to look at one, so a section that is missing or
        // the wrong shape renders as far as it can rather than 500ing.
        $personal = is_array($data['personal'] ?? null) ? $data['personal'] : [];
    @endphp

    @if(! empty($diff))
    <x-resume.diff.changes-panel :diff="$diff" :baseline="$baseline" :revision-id="$candidate['id']" />

    <div x-data="resumeDiffToggle">
        <div class="rd-toggle">
            <label class="rd-switch">
                <input type="checkbox" role="switch" class="rd-switch__input" x-model="show" checked>
                <span class="rd-switch__track" aria-hidden="true"></span>
                <span class="rd-switch__label">Show changes</span>
            </label>
            <p class="rd-toggle__hint" x-show="show">Insertions are underlined and deletions struck through.</p>
            <p class="rd-toggle__hint" x-show="!show" x-cloak>Showing the revision as it would be published.</p>
        </div>

        {{-- The revision with its differences from the baseline marked in place --}}
        <div class="rd-view" data-rd-view="marked" x-show="show">
            <x-resume.diff.header :section="$diff->section('personal')" :revision-label="$revisionLabel" />
            <x-resume.diff.skills :section="$diff->section('skills')" />
            <x-resume.diff.experience :section="$diff->section('experience')" />
            @if($diff->section('education')->entries !== [])
            <x-resume.diff.education :section="$diff->section('education')" />
            @endif
            <x-resume.diff.projects :section="$diff->section('projects')" />
        </div>

        {{-- The same revision rendered exactly as before, without marks --}}
        <div data-rd-view="clean" x-show="!show" x-cloak>
            @include('resume.sections')
        </div>
    </div>
    @else
    @include('resume.sections')
    @endif

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
