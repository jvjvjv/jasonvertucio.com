@extends('layouts.app')

@section('content')
<div class="p-6 max-w-4xl mx-auto">
    <a href="{{ route('admin.mail-preview.index') }}" class="text-blue-600 hover:underline mb-4 block">← Back</a>

    <h1 class="text-2xl font-bold mb-2">{{ $mailable['name'] }}</h1>
    <p class="text-gray-600 mb-6">Subject: {{ $subject ?? 'N/A' }}</p>

    @if(isset($error))
        <div class="p-4 bg-red-50 border border-red-200 rounded mb-6">
            <p class="text-red-800"><strong>Error:</strong> {{ $error }}</p>
        </div>
    @else
        <div class="border rounded p-6 bg-white">
            {!! $preview !!}
        </div>
    @endif
</div>
@endsection
