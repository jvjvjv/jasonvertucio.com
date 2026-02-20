@extends('layout')

@section('title', 'New Cover Letter')

@section('main')
    <div class="max-w-3xl mx-auto px-4 py-8">
        <a href="{{ route('admin.cover-letters.index') }}" class="text-sm text-primary hover:underline">&larr; Back to Cover Letters</a>
        <h1 class="text-3xl font-heading font-bold text-primary mt-2 mb-8">New Cover Letter</h1>

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

        <form method="POST" action="{{ route('admin.cover-letters.store') }}">
            @csrf

            @include('admin.cover-letters.form')

            <div class="mt-8 flex items-center justify-end gap-4">
                <a href="{{ route('admin.cover-letters.index') }}" class="text-sm text-gray-600 hover:underline">Cancel</a>
                <button type="submit"
                    class="inline-flex items-center gap-2 px-5 py-2.5 bg-primary text-white text-sm font-medium rounded-lg hover:bg-primary/90 transition-colors">
                    <i class="fa-classic fa-floppy-disk"></i>
                    Save &amp; Generate
                </button>
            </div>
        </form>
    </div>
@endsection
