@extends('layout')

@section('title', 'Add Memory')

@section('main')
    <div class="max-w-3xl mx-auto px-4 py-8">
        <a href="{{ route('admin.ai.memories.index') }}" class="text-sm text-primary hover:underline">&larr; Back to AI Memory</a>
        <h1 class="text-3xl font-heading font-bold text-primary mt-2 mb-8">Add Memory Entry</h1>

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

        <form method="POST" action="{{ route('admin.ai.memories.store') }}">
            @csrf

            @include('admin.ai.memories._form')

            <div class="mt-8 flex items-center justify-end gap-4">
                <a href="{{ route('admin.ai.memories.index') }}" class="text-sm text-gray-600 hover:underline">Cancel</a>
                <button type="submit"
                    class="inline-flex items-center gap-2 px-5 py-2.5 bg-primary text-white text-sm font-medium rounded-lg hover:bg-primary/90 transition-colors">
                    <i class="fa-classic fa-plus"></i>
                    Create Memory
                </button>
            </div>
        </form>
    </div>
@endsection
