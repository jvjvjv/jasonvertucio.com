@extends('layout')

@section('title', 'Edit Memory')

@section('main')
    <div class="max-w-3xl mx-auto px-4 py-8">
        <a href="{{ route('admin.ai.memories.index') }}" class="text-sm text-primary hover:underline">&larr; Back to AI Memory</a>
        <h1 class="text-3xl font-heading font-bold text-primary mt-2 mb-8">Edit: {{ $memory->key }}</h1>

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

        <div class="mb-6 px-4 py-3 bg-gray-50 border border-gray-200 rounded-lg text-sm text-gray-600">
            <div class="flex flex-wrap gap-x-6 gap-y-1">
                <span>Feature: <strong>{{ $memory->feature }}</strong></span>
                <span>Reinforced: <strong>{{ $memory->times_reinforced }}x</strong></span>
                @if($memory->last_reinforced_at)
                    <span>Last reinforced: <strong>{{ $memory->last_reinforced_at->diffForHumans() }}</strong></span>
                @endif
                @if($memory->sourceConversation)
                    <span>Source conversation: <strong>#{{ $memory->source_conversation_id }}</strong></span>
                @endif
            </div>
        </div>

        <form method="POST" action="{{ route('admin.ai.memories.update', $memory) }}">
            @csrf
            @method('PUT')

            @include('admin.ai.memories._form', ['memory' => $memory])

            <div class="mt-8 flex items-center justify-end gap-4">
                <a href="{{ route('admin.ai.memories.index') }}" class="text-sm text-gray-600 hover:underline">Cancel</a>
                <button type="submit"
                    class="inline-flex items-center gap-2 px-5 py-2.5 bg-primary text-white text-sm font-medium rounded-lg hover:bg-primary/90 transition-colors">
                    <i class="fa-classic fa-floppy-disk"></i>
                    Update Memory
                </button>
            </div>
        </form>
    </div>
@endsection
