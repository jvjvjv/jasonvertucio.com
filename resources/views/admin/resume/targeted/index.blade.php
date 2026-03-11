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

        @if($targetedResumes->isEmpty())
            <div class="text-center py-16 text-gray-500">
                <i class="fa-classic fa-bullseye text-4xl mb-4 block text-gray-300"></i>
                <p class="text-lg">No targeted resumes yet.</p>
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
                            <th class="text-left px-4 py-3 font-semibold text-gray-700">Company</th>
                            <th class="text-left px-4 py-3 font-semibold text-gray-700">Position</th>
                            <th class="text-left px-4 py-3 font-semibold text-gray-700">Fit</th>
                            <th class="text-left px-4 py-3 font-semibold text-gray-700">Status</th>
                            <th class="text-left px-4 py-3 font-semibold text-gray-700">Base Version</th>
                            <th class="text-left px-4 py-3 font-semibold text-gray-700">Created</th>
                            <th class="text-right px-4 py-3 font-semibold text-gray-700">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($targetedResumes as $resume)
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="px-4 py-3 font-medium">
                                    @if($resume->conversation)
                                        <a href="{{ route('admin.resume.targeted.show', $resume->conversation) }}"
                                            class="text-primary hover:underline">
                                            {{ $resume->company_name }}
                                        </a>
                                    @else
                                        {{ $resume->company_name }}
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-gray-600">{{ $resume->position }}</td>
                                <td class="px-4 py-3">
                                    @if($resume->fit_score)
                                        <span class="inline-flex items-center gap-1 text-xs font-medium
                                            {{ $resume->fit_score >= 70 ? 'text-green-700' : ($resume->fit_score >= 40 ? 'text-yellow-700' : 'text-red-700') }}">
                                            {{ $resume->fit_score }}%
                                        </span>
                                    @else
                                        <span class="text-gray-400">&mdash;</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-block px-2 py-0.5 rounded text-xs font-medium
                                        {{ $resume->status === 'finalized' ? 'bg-green-50 text-green-700' : ($resume->status === 'draft' ? 'bg-yellow-50 text-yellow-700' : 'bg-gray-100 text-gray-600') }}">
                                        {{ ucfirst($resume->status) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-gray-600 font-mono text-xs">{{ $resume->resumeVersion?->version ?? '—' }}</td>
                                <td class="px-4 py-3 text-gray-600 text-xs">{{ $resume->created_at->format('M j, Y') }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-end gap-2">
                                        @if($resume->conversation)
                                            <a href="{{ route('admin.resume.targeted.show', $resume->conversation) }}"
                                                class="p-2 text-gray-500 hover:text-primary transition-colors"
                                                title="View conversation">
                                                <i class="fa-classic fa-comments"></i>
                                            </a>
                                        @endif
                                        @if($resume->docxExists())
                                            <a href="{{ route('admin.resume.targeted.download', [$resume, 'docx']) }}"
                                                class="p-2 text-gray-500 hover:text-primary transition-colors"
                                                title="Download DOCX">
                                                <i class="fa-classic fa-file-word"></i>
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
