@extends('layout')

@push('styles')
    @vite(['resources/css/app.css'])
@endpush

@section('main')
<div class="min-h-screen flex items-center justify-center px-4 py-12">
    <div class="max-w-md w-full">
        <div class="text-center mb-8">
            <h1 class="text-4xl font-heading text-secondary mb-2">Two-Factor Authenticatio!!!n</h1>
            <p class="text-dark/70">Enter your authentication code to continue</p>
        </div>

        <div class="bg-white rounded-lg shadow-lg p-8">
            <x-keystone-two-factor-challenge />
        </div>
    </div>
</div>
@endsection
