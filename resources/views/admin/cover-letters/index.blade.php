@extends('layout')

@section('title', 'Cover Letters')

@section('main')
    <div class="max-w-5xl mx-auto px-4 py-8">
        <a href="{{ route('admin.index') }}" class="text-sm text-primary hover:underline">&larr; Back to Admin</a>

        <div class="flex items-center justify-between mt-2 mb-8">
            <h1 class="text-3xl font-heading font-bold text-primary">Cover Letters</h1>
            <a href="{{ route('admin.cover-letters.create') }}"
                class="inline-flex items-center gap-2 px-4 py-2 bg-primary text-white text-sm font-medium rounded-lg hover:bg-primary/90 transition-colors">
                <i class="fa-classic fa-plus"></i>
                Add Cover Letter
            </a>
        </div>

        @if(session('success'))
            <div class="mb-6 px-4 py-3 bg-green-50 border border-green-200 text-green-800 rounded-lg text-sm">
                {{ session('success') }}
            </div>
        @endif

        @if($coverLetters->isEmpty())
            <div class="text-center py-16 text-gray-500">
                <i class="fa-classic fa-envelope text-4xl mb-4 block text-gray-300"></i>
                <p class="text-lg">No cover letters yet.</p>
                <p class="text-sm mt-1">
                    <a href="{{ route('admin.cover-letters.create') }}" class="text-primary hover:underline">Add your first one</a>
                </p>
            </div>
        @else
            <div class="border border-gray-200 rounded-lg overflow-hidden">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-gray-50 border-b border-gray-200">
                            <th class="text-left px-4 py-3 font-semibold text-gray-700">Company</th>
                            <th class="text-left px-4 py-3 font-semibold text-gray-700">Position</th>
                            <th class="text-left px-4 py-3 font-semibold text-gray-700">Date</th>
                            <th class="text-right px-4 py-3 font-semibold text-gray-700">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($coverLetters as $coverLetter)
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="px-4 py-3 font-medium">
                                    <a href="{{ route('admin.cover-letters.preview', $coverLetter) }}"
                                        class="text-primary hover:underline">
                                        {{ $coverLetter->company_name }}
                                    </a>
                                </td>
                                <td class="px-4 py-3 text-gray-600">
                                    <a href="{{ route('admin.cover-letters.preview', $coverLetter) }}"
                                        class="hover:text-primary hover:underline">
                                        {{ $coverLetter->position }}
                                    </a>
                                </td>
                                <td class="px-4 py-3 text-gray-600">{{ $coverLetter->date->format('M j, Y') }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-end gap-2">
                                        <a href="{{ route('admin.cover-letters.edit', $coverLetter) }}"
                                            class="p-2 text-gray-500 hover:text-primary transition-colors"
                                            title="Edit">
                                            <i class="fa-classic fa-file-pen"></i>
                                        </a>
                                        <form method="POST" action="{{ route('admin.cover-letters.destroy', $coverLetter) }}"
                                            onsubmit="return confirm('Delete this cover letter? This cannot be undone.')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit"
                                                class="p-2 text-gray-500 hover:text-red-600 transition-colors"
                                                title="Delete">
                                                <i class="fa-classic fa-trash-can"></i>
                                            </button>
                                        </form>
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
