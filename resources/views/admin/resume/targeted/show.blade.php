@extends('layout')

@section('title', $conversation->title ?? 'Targeted Resume')

@section('main')
    <div class="max-w-5xl mx-auto px-4 py-8">
        <a href="{{ route('admin.resume.targeted.index') }}" class="text-sm text-primary hover:underline">&larr; Back to Targeted Resumes</a>

        <div class="flex items-center justify-between mt-2 mb-6">
            <div>
                <h1 class="text-3xl font-heading font-bold text-primary">{{ $conversation->title ?? 'Targeted Resume' }}</h1>
                <p class="text-sm text-gray-500 mt-1">
                    Using {{ $conversation->aiSystem->name }} &middot;
                    {{ $conversation->status === 'active' ? 'In progress' : ucfirst($conversation->status) }}
                </p>
            </div>
        </div>

        {{-- Finalized resume info --}}
        @if($targetedResume)
            <div class="mb-6 px-4 py-3 bg-green-50 border border-green-200 text-green-800 rounded-lg text-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="font-medium">Resume finalized for {{ $targetedResume->company_name }} &mdash; {{ $targetedResume->position }}</p>
                        @if($targetedResume->fit_score)
                            <p class="text-xs mt-1">Fit score: {{ $targetedResume->fit_score }}%</p>
                        @endif
                    </div>
                    <div class="flex items-center gap-2">
                        @if($targetedResume->docxExists())
                            <a href="{{ route('admin.resume.targeted.download', [$targetedResume, 'docx']) }}"
                                class="inline-flex items-center gap-1 px-3 py-1.5 bg-white border border-green-300 text-green-700 text-xs font-medium rounded-lg hover:bg-green-50 transition-colors">
                                <i class="fa-classic fa-file-word"></i> DOCX
                            </a>
                        @endif
                        @if($targetedResume->pdfExists())
                            <a href="{{ route('admin.resume.targeted.download', [$targetedResume, 'pdf']) }}"
                                class="inline-flex items-center gap-1 px-3 py-1.5 bg-white border border-green-300 text-green-700 text-xs font-medium rounded-lg hover:bg-green-50 transition-colors">
                                <i class="fa-classic fa-file-pdf"></i> PDF
                            </a>
                        @endif
                    </div>
                </div>
            </div>

            @php
                $tailoredHtml = data_get($targetedResume->tailored_data, 'html');
            @endphp

            @if($tailoredHtml)
                <section class="mb-8 border border-gray-200 rounded-lg overflow-hidden bg-white">
                    <div class="px-4 py-3 border-b border-gray-200 bg-gray-50">
                        <h2 class="text-lg font-heading font-semibold text-primary">Finalized Resume Preview</h2>
                        <p class="text-xs text-gray-500 mt-1">Saved HTML preview of the targeted resume.</p>
                    </div>
                    <div class="px-6 py-5 prose prose-sm max-w-none">
                        {!! $tailoredHtml !!}
                    </div>
                </section>
            @endif
        @endif

        {{-- Chat interface --}}
        @include('admin.ai._chat', [
            'chatUrl' => route('admin.resume.targeted.chat', $conversation),
            'conversationId' => $conversation->id,
            'messages' => $messages,
            'actions' => $conversation->status === 'active' ? view('admin.resume.targeted._actions', ['conversation' => $conversation]) : '',
        ])
    </div>
@endsection
