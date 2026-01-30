@extends('layout')

@section('title', 'Mail Preview')

@section('main')
<div class="p-6 max-w-2xl mx-auto">
    <h1 class="text-2xl font-bold mb-6">Mail Preview</h1>

    @if(!count($mailables))
        <p class="text-gray-500">No mailables found.</p>
    @else
        <div class="space-y-2">
            @foreach($mailables as $mailable)
                <a href="{{ route('admin.mail-preview.show', $mailable['class']) }}"
                   class="block p-4 border rounded hover:bg-gray-50 transition">
                    <h3 class="font-semibold">{{ $mailable['name'] }}</h3>
                    <p class="text-sm text-gray-500">{{ $mailable['file'] }}</p>
                </a>
            @endforeach
        </div>
    @endif
</div>
@endsection
