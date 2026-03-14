@extends('layout')

@section('title', 'Targeted Resumes')

@section('main')
    <div class="max-w-5xl mx-auto px-4 py-8">
        <a href="{{ route('admin.resume.index') }}" class="text-sm text-primary hover:underline">&larr; Back to Resume Management</a>

        <div class="flex items-center justify-between mt-2 mb-8">
            <h1 class="text-3xl font-heading font-bold text-primary">Targeted Resumes</h1>
            <a href="{{ route('admin.resume.targeted.create') }}"
                class="inline-flex items-center gap-2 px-4 py-2 bg-primary text-white text-sm font-medium rounded-lg hover:bg-primary/90 transition-colors">
                <i class="fa-classic fa-plus"></i>
                New Targeted Resume
            </a>
        </div>

        @if(session('success'))
            <div class="mb-6 px-4 py-3 bg-green-50 border border-green-200 text-green-800 rounded-lg text-sm">
                {{ session('success') }}
            </div>
        @endif

        @if(session('error'))
            <div class="mb-6 px-4 py-3 bg-red-50 border border-red-200 text-red-800 rounded-lg text-sm">
                {{ session('error') }}
            </div>
        @endif

        @if($conversations->isEmpty())
            <div class="text-center py-16 text-gray-500">
                <i class="fa-classic fa-bullseye text-4xl mb-4 block text-gray-300"></i>
                <p class="text-lg">No targeted resume chats yet.</p>
                <p class="text-sm mt-1">
                    <a href="{{ route('admin.resume.targeted.create') }}" class="text-primary hover:underline">Create your first one</a>
                    by providing a job description.
                </p>
            </div>
        @else
            <div class="border border-gray-200 rounded-lg overflow-hidden">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-gray-50 border-b border-gray-200">
                            <th class="text-left px-4 py-3 font-semibold text-gray-700">Conversation</th>
                            <th class="text-left px-4 py-3 font-semibold text-gray-700">Position</th>
                            <th class="text-left px-4 py-3 font-semibold text-gray-700">Fit</th>
                            <th class="text-left px-4 py-3 font-semibold text-gray-700">Status</th>
                            <th class="text-left px-4 py-3 font-semibold text-gray-700">Base Version</th>
                            <th class="text-left px-4 py-3 font-semibold text-gray-700">Last Activity</th>
                            <th class="text-right px-4 py-3 font-semibold text-gray-700">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($conversations as $conversation)
                            @php
                                $resume = $conversation->targetedResume;
                                $jobTitle = $resume?->position ?? data_get($conversation->context, 'job_title');
                                $baseVersion = $resume?->resumeVersion?->version ?? data_get($conversation->context, 'resume_version_id');
                                $companyName = $resume?->company_name ?? data_get($conversation->context, 'company_name');
                                $fitScore = $resume?->fit_score ?? data_get($conversation->context, 'fit_score');
                            @endphp
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="px-4 py-3">
                                    <a href="{{ route('admin.resume.targeted.show', $conversation) }}"
                                        class="font-medium text-primary hover:underline">
                                        {{ $companyName ?: ($conversation->title ?: 'Untitled conversation') }}
                                    </a>
                                    <div class="mt-1 text-xs text-gray-500">
                                        {{ $conversation->messages_count }} messages
                                        @if($conversation->aiSystem)
                                            &middot; {{ $conversation->aiSystem->name }}
                                        @endif
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-gray-600">{{ $jobTitle ?: '—' }}</td>
                                <td class="px-4 py-3">
                                    @if($fitScore)
                                        <span class="inline-flex items-center gap-1 text-xs font-medium
                                            {{ $fitScore >= 70 ? 'text-green-700' : ($fitScore >= 40 ? 'text-yellow-700' : 'text-red-700') }}">
                                            {{ $fitScore }}%
                                        </span>
                                    @else
                                        <span class="text-gray-400">&mdash;</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-block px-2 py-0.5 rounded text-xs font-medium
                                        {{ $resume?->status === 'finalized' || $conversation->status === 'completed' ? 'bg-green-50 text-green-700' : ($conversation->status === 'active' ? 'bg-yellow-50 text-yellow-700' : 'bg-gray-100 text-gray-600') }}">
                                        {{ ucfirst($resume?->status ?? $conversation->status) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-gray-600 font-mono text-xs">{{ $baseVersion ?: '—' }}</td>
                                <td class="px-4 py-3 text-gray-600 text-xs">{{ $conversation->updated_at->format('M j, Y g:i A') }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-end gap-2">
                                        <a href="{{ route('admin.resume.targeted.show', $conversation) }}"
                                            class="p-2 text-gray-500 hover:text-secondary transition-colors"
                                            title="View conversation">
                                            <i class="fa-classic fa-comments"></i>
                                        </a>
                                        @if($resume)
                                            <form action="{{ route('admin.resume.targeted.regenerate', $resume) }}" method="POST" class="inline">
                                                @csrf
                                                <button type="submit"
                                                    class="p-2 text-gray-500 hover:text-secondary transition-colors cursor-pointer"
                                                    title="Regenerate DOCX/PDF">
                                                    <i class="fa-classic fa-arrows-rotate"></i>
                                                </button>
                                            </form>
                                        @endif
                                        @if($resume && $resume->docxExists())
                                            <a href="{{ route('admin.resume.targeted.download', [$resume, 'docx']) }}"
                                                class="p-2 text-gray-500 hover:text-secondary transition-colors"
                                                title="Download DOCX">
                                                <i class="fa-classic fa-file-word"></i>
                                            </a>
                                        @endif
                                        @if($resume && $resume->pdfExists())
                                            <a href="{{ route('admin.resume.targeted.download', [$resume, 'pdf']) }}"
                                                class="p-2 text-gray-500 hover:text-secondary transition-colors"
                                                title="Download PDF">
                                                <i class="fa-classic fa-file-pdf"></i>
                                            </a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endsection
