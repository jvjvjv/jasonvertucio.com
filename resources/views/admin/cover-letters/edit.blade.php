@extends('layout')

@section('title', 'Edit Cover Letter — ' . $coverLetter->company_name)

@section('main')
    <div class="max-w-3xl mx-auto px-4 py-8">
        <a href="{{ route('admin.cover-letters.index') }}" class="text-sm text-primary hover:underline">&larr; Back to Cover Letters</a>

        <div class="flex items-start justify-between mt-2 mb-8">
            <div>
                <h1 class="text-3xl font-heading font-bold text-primary">
                    {{ $coverLetter->company_name }}
                </h1>
                <p class="text-gray-500 text-sm mt-1">{{ $coverLetter->position }} &middot; {{ $coverLetter->date->format('F j, Y') }}</p>
            </div>
            <a href="{{ route('admin.cover-letters.preview', $coverLetter) }}"
                class="inline-flex items-center gap-2 px-4 py-2 border border-gray-300 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-50 transition-colors shrink-0 mt-1">
                <i class="fa-classic fa-eye"></i>
                Preview
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

        @if($errors->any())
            <div class="mb-6 px-4 py-3 bg-red-50 border border-red-200 text-red-800 rounded-lg text-sm">
                <p class="font-medium mb-1">Please fix the following errors:</p>
                <ul class="list-disc list-inside space-y-1">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form id="save-form" method="POST" action="{{ route('admin.cover-letters.update', $coverLetter) }}">
            @csrf
            @method('PUT')

            @include('admin.cover-letters.form')

            <div class="mt-8 flex items-center justify-between gap-4">
                <button type="button"
                    onclick="if(confirm('Delete this cover letter and its generated files? This cannot be undone.')) document.getElementById('delete-form').submit();"
                    class="inline-flex items-center gap-2 px-4 py-2 text-red-600 text-sm font-medium hover:underline">
                    <i class="fa-classic fa-trash-can"></i>
                    Delete
                </button>

                <div class="flex items-center gap-4">
                    <a href="{{ route('admin.cover-letters.index') }}" class="text-sm text-gray-600 hover:underline">Cancel</a>
                    <button type="submit"
                        class="inline-flex items-center gap-2 px-5 py-2.5 bg-primary text-white text-sm font-medium rounded-lg hover:bg-primary/90 transition-colors">
                        <i class="fa-classic fa-floppy-disk"></i>
                        Save &amp; Regenerate
                    </button>
                </div>
            </div>
        </form>

        <form id="delete-form" method="POST" action="{{ route('admin.cover-letters.destroy', $coverLetter) }}">
            @csrf
            @method('DELETE')
        </form>
    </div>
@endsection
