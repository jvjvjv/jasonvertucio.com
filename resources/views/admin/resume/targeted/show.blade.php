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
                    <span class="inline-block px-2 py-0.5 rounded text-xs font-medium
                        @if($conversation->status === \App\Enums\AiConversationStatus::Completed)
                            bg-green-50 text-green-700
                        @elseif($conversation->status === \App\Enums\AiConversationStatus::Pass)
                            bg-red-50 text-red-700
                        @elseif($conversation->status === \App\Enums\AiConversationStatus::Active)
                            bg-yellow-50 text-yellow-700
                        @else
                            bg-gray-100 text-gray-600
                        @endif">
                        {{ $conversation->status === \App\Enums\AiConversationStatus::Active ? 'In progress' : ucfirst($conversation->status->value) }}
                    </span>
                </p>
            </div>
            @if($conversation->status !== \App\Enums\AiConversationStatus::Pass)
                <form action="{{ route('admin.resume.targeted.pass', $conversation) }}" method="POST"
                    onsubmit="return confirm('Mark this opportunity as passed?')">
                    @csrf
                    <button type="submit"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-red-50 text-red-700 text-sm font-medium rounded-lg border border-red-200 hover:bg-red-100 transition-colors cursor-pointer">
                        <i class="fa-classic fa-hand"></i>
                        Pass
                    </button>
                </form>
            @endif
        </div>

        @if(session('success'))
            <div class="mb-6 px-4 py-3 bg-green-50 border border-green-200 text-green-800 rounded-lg text-sm">
                {{ session('success') }}
            </div>
        @endif

        <section class="mb-6 border border-gray-200 rounded-lg bg-white overflow-hidden">
            <div class="px-4 py-3 border-b border-gray-200 bg-gray-50">
                <h2 class="text-lg font-heading font-semibold text-primary">Conversation Details</h2>
                <p class="text-xs text-gray-500 mt-1">Edit the chat title and any inferred company or role details.</p>
            </div>
            <form method="POST" action="{{ route('admin.resume.targeted.update-metadata', $conversation) }}" class="p-4 space-y-4">
                @csrf
                @method('PUT')
                <div class="grid gap-4 md:grid-cols-3">
                    <div>
                        <label for="title" class="block text-sm font-medium text-gray-700 mb-1">Chat Title</label>
                        <input id="title" name="title" type="text" value="{{ old('title', $conversation->title) }}"
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary"
                            placeholder="Targeted Resume">
                    </div>
                    <div>
                        <label for="company_name" class="block text-sm font-medium text-gray-700 mb-1">Company</label>
                        <input id="company_name" name="company_name" type="text" value="{{ old('company_name', data_get($conversation->context, 'company_name')) }}"
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary"
                            placeholder="Inferred or manual company name">
                    </div>
                    <div>
                        <label for="job_title" class="block text-sm font-medium text-gray-700 mb-1">Job Title</label>
                        <input id="job_title" name="job_title" type="text" value="{{ old('job_title', data_get($conversation->context, 'job_title')) }}"
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary"
                            placeholder="Inferred or manual job title">
                    </div>
                </div>
                <div class="flex items-center justify-between gap-4">
                    <div class="text-xs text-gray-500">
                        @if(data_get($conversation->context, 'fit_score'))
                            Current fit score: {{ data_get($conversation->context, 'fit_score') }}%
                        @else
                            Fit score will appear here once the assistant provides it.
                        @endif
                    </div>
                    <button type="submit"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-primary text-white text-sm font-medium rounded-lg hover:bg-primary/90 transition-colors">
                        <i class="fa-classic fa-floppy-disk"></i>
                        Save Details
                    </button>
                </div>
            </form>
        </section>

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

        @endif

        {{-- Finalized cover letter info --}}
        @if($coverLetter)
            <div class="mb-6 px-4 py-3 bg-blue-50 border border-blue-200 text-blue-800 rounded-lg text-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="font-medium">Cover letter finalized for {{ $coverLetter->company_name }} &mdash; {{ $coverLetter->position }}</p>
                        <p class="text-xs mt-1">{{ $coverLetter->date->format('M j, Y') }}</p>
                    </div>
                    <div class="flex items-center gap-2">
                        @if($coverLetter->docxExists())
                            <a href="{{ route('admin.cover-letters.download.docx', $coverLetter) }}"
                                class="inline-flex items-center gap-1 px-3 py-1.5 bg-white border border-blue-300 text-blue-700 text-xs font-medium rounded-lg hover:bg-blue-50 transition-colors">
                                <i class="fa-classic fa-file-word"></i> DOCX
                            </a>
                        @endif
                        @if($coverLetter->pdfExists())
                            <a href="{{ route('admin.cover-letters.download.pdf', $coverLetter) }}"
                                class="inline-flex items-center gap-1 px-3 py-1.5 bg-white border border-blue-300 text-blue-700 text-xs font-medium rounded-lg hover:bg-blue-50 transition-colors">
                                <i class="fa-classic fa-file-pdf"></i> PDF
                            </a>
                        @endif
                    </div>
                </div>
            </div>
        @endif

        {{-- Chat interface --}}
        @include('admin.ai._chat', [
            'chatUrl' => route('admin.resume.targeted.chat', $conversation),
            'conversationId' => $conversation->id,
            'messages' => $messages,
            'autoStart' => $shouldAutoStart,
            'actions' => view('admin.resume.targeted._actions', [
                'conversation' => $conversation,
                'resumeFinalized' => $targetedResume !== null,
                'coverLetterFinalized' => $coverLetter !== null,
                'existingResumeTitle' => $targetedResume?->title,
                'existingResumeContent' => data_get($targetedResume?->tailored_data, 'markdown')
                    ?? data_get($targetedResume?->tailored_data, 'content'),
            ]),
        ])
    </div>
@endsection
