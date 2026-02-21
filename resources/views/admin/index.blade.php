@php

$nav_blocks = [
    [
        'can' => null,
        'route' => 'admin.mail-preview.index',
        'icon' => 'inbox',
        'label' => 'Mail preview',
        'description' => 'See how emails might be rendered, right here in the browser!',
    ],
    [
        'can' => null,
        'route' => 'admin.resume.index',
        'icon' => 'file-lines',
        'label' => 'Resume Management',
        'description' => 'Edit resume content, manage share codes, and generate documents',
    ],
    [
        'can' => null,
        'route' => 'admin.cover-letters.index',
        'icon' => 'envelope',
        'label' => 'Cover Letter Management',
        'description' => 'Create and manage cover letters with automatic DOCX and PDF generation',
    ],
    [
        'can' => null,
        'route' => 'admin.site-settings.edit',
        'icon' => 'bars',
        'label' => 'Site Settings',
        'description' => 'Manage sidebar navigation links and their order',
    ],
];

@endphp

@extends('layout')

@section('title', 'Admin Dashboard')

@section('main')
    <div class="max-w-6xl mx-auto px-4 py-8">
        <h1 class="text-3xl font-heading font-bold text-primary mb-8">Admin Dashboard</h1>

        <div class="grid gap-6 sm:grid-cols-2 md:grid-cols-2 lg:grid-cols-3">
            @foreach($nav_blocks as $nav)
                @can($nav['can'])
                    <x-admin.nav-block route="{{  $nav['route'] }}" icon="{{ $nav['icon']}}" label="{{ $nav['label']}}"
                        description="{{ $nav['description'] }}" />
                @endcan
            @endforeach
        </div>
    </div>
@endsection
