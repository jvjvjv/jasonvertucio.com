@extends('layout')

@section('title', 'Admin Dashboard')

@section('main')
<div class="max-w-4xl mx-auto px-4 py-8">
    <h1 class="text-3xl font-heading font-bold text-primary mb-8">Admin Dashboard</h1>

    <div class="grid gap-6 md:grid-cols-2">
        <a href="{{ route('admin.resume.index') }}"
           class="block p-6 bg-white rounded-lg shadow-md hover:shadow-lg transition-shadow border border-gray-200">
            <div class="flex items-center">
                <div class="flex-shrink-0 w-12 h-12 bg-primary/10 rounded-lg flex items-center justify-center">
                    <svg class="w-6 h-6 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                </div>
                <div class="ml-4">
                    <h2 class="text-lg font-semibold text-gray-900">Resume Share Codes</h2>
                    <p class="text-sm text-gray-500">Manage share codes for unauthenticated resume access</p>
                </div>
            </div>
        </a>
    </div>
</div>
@endsection
