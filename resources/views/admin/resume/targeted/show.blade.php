@extends('layout')

@section('title', $conversation->title ?? 'Targeted Resume')

@section('main')
    @php
        $conversationStatusClasses = match ($conversation->status) {
            \App\Enums\AiConversationStatus::Completed => 'bg-green-50 text-green-700',
            \App\Enums\AiConversationStatus::Pass => 'bg-red-50 text-red-700',
            \App\Enums\AiConversationStatus::Active => 'bg-yellow-50 text-yellow-700',
            default => 'bg-gray-100 text-gray-600',
        };

        $conversationStatusLabel =
            $conversation->status === \App\Enums\AiConversationStatus::Active
                ? 'In progress'
                : ucfirst($conversation->status->value);
    @endphp

    <div class="mx-auto flex min-h-[calc(100vh-10rem)] max-w-[110rem] flex-col px-4 py-6 lg:px-6">
        <a href="{{ route('admin.resume.targeted.index') }}" class="text-sm text-primary hover:underline">&larr; Back to Targeted Resumes</a>

        <div class="mt-2 mb-6 flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <h1 class="text-3xl font-heading font-bold text-primary">{{ $conversation->title ?? 'Targeted Resume' }}</h1>
                <p class="text-sm text-gray-500 mt-1">
                    Using {{ $conversation->aiSystem->name }} &middot;
                    <span class="inline-block rounded px-2 py-0.5 text-xs font-medium {{ $conversationStatusClasses }}">
                        {{ $conversationStatusLabel }}
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

        <div x-data="{ activeTab: {{
                \Illuminate\Support\Js::from($errors->any() ? 'details' : 'chat')
            }} }"
            class="flex min-h-0 flex-1 flex-col gap-4">
            <div class="flex flex-col gap-4 rounded-2xl border border-gray-200 bg-white p-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="font-heading text-lg font-semibold text-primary">Workspace</h2>
                    <p class="text-sm text-gray-500">Switch between the live conversation and the metadata panel.</p>
                </div>
                <div role="tablist" aria-label="Targeted resume sections" class="inline-flex w-full rounded-xl border border-gray-200 bg-gray-50 p-1 sm:w-auto">
                    <button type="button"
                        role="tab"
                        @click="activeTab = 'chat'"
                        :aria-selected="activeTab === 'chat'"
                        :tabindex="activeTab === 'chat' ? 0 : -1"
                        :class="activeTab === 'chat'
                            ? 'bg-primary text-white'
                            : 'text-gray-600 hover:bg-white hover:text-primary'"
                        class="flex-1 rounded-lg px-4 py-2 text-sm font-medium transition-colors sm:flex-none">
                        Chat
                    </button>
                    <button type="button"
                        role="tab"
                        @click="activeTab = 'details'"
                        :aria-selected="activeTab === 'details'"
                        :tabindex="activeTab === 'details' ? 0 : -1"
                        :class="activeTab === 'details'
                            ? 'bg-primary text-white'
                            : 'text-gray-600 hover:bg-white hover:text-primary'"
                        class="flex-1 rounded-lg px-4 py-2 text-sm font-medium transition-colors sm:flex-none">
                        Conversation Details
                    </button>
                </div>
            </div>

            <div x-show="activeTab === 'chat'" role="tabpanel" class="flex min-h-0 flex-1 flex-col gap-4">
                <div class="grid gap-4 lg:grid-cols-2">
                    <section class="rounded-2xl border px-4 py-4 text-sm {{ $targetedResume ? 'border-green-200 bg-green-50 text-green-900' : 'border-gray-200 bg-white text-gray-700' }}">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-[0.2em] {{ $targetedResume ? 'text-green-700' : 'text-gray-500' }}">Resume</p>
                                <h3 class="mt-2 font-heading text-xl font-semibold">{{ $targetedResume ? 'Finalized and ready' : 'Not finalized yet' }}</h3>
                                <p class="mt-2 text-sm {{ $targetedResume ? 'text-green-800' : 'text-gray-500' }}">
                                    @if($targetedResume)
                                        {{ $targetedResume->company_name }} for {{ $targetedResume->position }}
                                    @else
                                        The tailored resume will appear here after you finalize it from the chat actions.
                                    @endif
                                </p>
                            </div>
                            <div class="h-4 w-16 rounded-full border {{ $targetedResume ? 'border-green-300 bg-green-500' : 'border-gray-300 bg-gray-200' }}"></div>
                        </div>
                        @if($targetedResume && $targetedResume->fit_score)
                            <p class="mt-4 text-xs font-medium text-green-700">Fit score: {{ $targetedResume->fit_score }}%</p>
                        @endif
                    </section>

                    <section class="rounded-2xl border px-4 py-4 text-sm {{ $coverLetter ? 'border-blue-200 bg-blue-50 text-blue-900' : 'border-gray-200 bg-white text-gray-700' }}">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-[0.2em] {{ $coverLetter ? 'text-blue-700' : 'text-gray-500' }}">Cover Letter</p>
                                <h3 class="mt-2 font-heading text-xl font-semibold">{{ $coverLetter ? 'Finalized and ready' : 'Not finalized yet' }}</h3>
                                <p class="mt-2 text-sm {{ $coverLetter ? 'text-blue-800' : 'text-gray-500' }}">
                                    @if($coverLetter)
                                        {{ $coverLetter->company_name }} for {{ $coverLetter->position }}
                                    @else
                                        The cover letter status updates here after you save one from the conversation.
                                    @endif
                                </p>
                            </div>
                            <div class="h-4 w-16 rounded-full border {{ $coverLetter ? 'border-blue-300 bg-blue-500' : 'border-gray-300 bg-gray-200' }}"></div>
                        </div>
                        @if($coverLetter)
                            <p class="mt-4 text-xs font-medium text-blue-700">Generated {{ $coverLetter->date->format('M j, Y') }}</p>
                        @endif
                    </section>
                </div>

                <div class="min-h-0 flex-1">
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
            </div>

            <div x-show="activeTab === 'details'" role="tabpanel" class="space-y-4">
                <div class="grid gap-4 xl:grid-cols-2">
                    <section class="rounded-2xl border border-green-200 bg-green-50 px-5 py-4 text-green-900">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-green-700">Resume Output</p>
                                @if($targetedResume)
                                    <h3 class="mt-2 font-heading text-xl font-semibold">Resume finalized</h3>
                                    <p class="mt-2 text-sm text-green-800">{{ $targetedResume->company_name }} for {{ $targetedResume->position }}</p>
                                    @if($targetedResume->fit_score)
                                        <p class="mt-2 text-xs font-medium text-green-700">Fit score: {{ $targetedResume->fit_score }}%</p>
                                    @endif
                                @else
                                    <h3 class="mt-2 font-heading text-xl font-semibold">No resume saved yet</h3>
                                    <p class="mt-2 text-sm text-green-800">Finalize a tailored resume from the chat tab to generate download links here.</p>
                                @endif
                            </div>
                            @if($targetedResume)
                                <div class="flex flex-wrap items-center gap-2">
                                    @if($targetedResume->docxExists())
                                        <a href="{{ route('admin.resume.targeted.download', [$targetedResume, 'docx']) }}"
                                            class="inline-flex items-center gap-1 rounded-lg border border-green-300 bg-white px-3 py-1.5 text-xs font-medium text-green-700 transition-colors hover:bg-green-100">
                                            <i class="fa-classic fa-file-word"></i> DOCX
                                        </a>
                                    @endif
                                    @if($targetedResume->pdfExists())
                                        <a href="{{ route('admin.resume.targeted.download', [$targetedResume, 'pdf']) }}"
                                            class="inline-flex items-center gap-1 rounded-lg border border-green-300 bg-white px-3 py-1.5 text-xs font-medium text-green-700 transition-colors hover:bg-green-100">
                                            <i class="fa-classic fa-file-pdf"></i> PDF
                                        </a>
                                    @endif
                                </div>
                            @endif
                        </div>
                    </section>

                    <section class="rounded-2xl border border-blue-200 bg-blue-50 px-5 py-4 text-blue-900">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-blue-700">Cover Letter Output</p>
                                @if($coverLetter)
                                    <h3 class="mt-2 font-heading text-xl font-semibold">Cover letter finalized</h3>
                                    <p class="mt-2 text-sm text-blue-800">{{ $coverLetter->company_name }} for {{ $coverLetter->position }}</p>
                                    <p class="mt-2 text-xs font-medium text-blue-700">Generated {{ $coverLetter->date->format('M j, Y') }}</p>
                                @else
                                    <h3 class="mt-2 font-heading text-xl font-semibold">No cover letter saved yet</h3>
                                    <p class="mt-2 text-sm text-blue-800">Finalize a cover letter from the chat tab to make downloads available here.</p>
                                @endif
                            </div>
                            @if($coverLetter)
                                <div class="flex flex-wrap items-center gap-2">
                                    @if($coverLetter->docxExists())
                                        <a href="{{ route('admin.cover-letters.download.docx', $coverLetter) }}"
                                            class="inline-flex items-center gap-1 rounded-lg border border-blue-300 bg-white px-3 py-1.5 text-xs font-medium text-blue-700 transition-colors hover:bg-blue-100">
                                            <i class="fa-classic fa-file-word"></i> DOCX
                                        </a>
                                    @endif
                                    @if($coverLetter->pdfExists())
                                        <a href="{{ route('admin.cover-letters.download.pdf', $coverLetter) }}"
                                            class="inline-flex items-center gap-1 rounded-lg border border-blue-300 bg-white px-3 py-1.5 text-xs font-medium text-blue-700 transition-colors hover:bg-blue-100">
                                            <i class="fa-classic fa-file-pdf"></i> PDF
                                        </a>
                                    @endif
                                </div>
                            @endif
                        </div>
                    </section>
                </div>

                <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white">
                    <div class="border-b border-gray-200 bg-gray-50 px-5 py-4">
                        <h2 class="text-lg font-heading font-semibold text-primary">Conversation Details</h2>
                        <p class="mt-1 text-sm text-gray-500">Edit the chat title and any inferred company or role details.</p>
                    </div>
                    <form method="POST" action="{{ route('admin.resume.targeted.update-metadata', $conversation) }}" class="space-y-5 p-5">
                        @csrf
                        @method('PUT')
                        <div class="grid gap-4 xl:grid-cols-3">
                            <div>
                                <label for="title" class="mb-1 block text-sm font-medium text-gray-700">Chat Title</label>
                                <input id="title" name="title" type="text" value="{{ old('title', $conversation->title) }}"
                                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary"
                                    placeholder="Targeted Resume">
                            </div>
                            <div>
                                <label for="company_name" class="mb-1 block text-sm font-medium text-gray-700">Company</label>
                                <input id="company_name" name="company_name" type="text" value="{{ old('company_name', data_get($conversation->context, 'company_name')) }}"
                                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary"
                                    placeholder="Inferred or manual company name">
                            </div>
                            <div>
                                <label for="job_title" class="mb-1 block text-sm font-medium text-gray-700">Job Title</label>
                                <input id="job_title" name="job_title" type="text" value="{{ old('job_title', data_get($conversation->context, 'job_title')) }}"
                                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary"
                                    placeholder="Inferred or manual job title">
                            </div>
                        </div>
                        <div class="flex flex-col gap-3 border-t border-gray-100 pt-4 sm:flex-row sm:items-center sm:justify-between">
                            <div class="text-xs text-gray-500">
                                @if(data_get($conversation->context, 'fit_score'))
                                    Current fit score: {{ data_get($conversation->context, 'fit_score') }}%
                                @else
                                    Fit score will appear here once the assistant provides it.
                                @endif
                            </div>
                            <button type="submit"
                                class="inline-flex items-center justify-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-primary/90">
                                <i class="fa-classic fa-floppy-disk"></i>
                                Save Details
                            </button>
                        </div>
                    </form>
                </section>
            </div>
        </div>
    </div>
@endsection
