@php

$nav_blocks = [
    [
        'can' => 'edit-resume',
        'route' => 'admin.resume.editor',
        'icon' => 'pen-to-square',
        'label' => 'Resume Builder',
        'description' => 'Build and edit resume content. Documents auto-generate on save.',
    ],
    [
        'can' => null,
        'route' => 'resume.index',
        'icon' => 'eye',
        'label' => 'Resume Preview',
        'description' => 'View the resume as it appears to visitors',
    ],
    [
        'can' => null,
        'route' => 'admin.resume.codes.index',
        'icon' => 'file-code',
        'label' => 'Share Codes',
        'description' => 'Share and manage codes for unauthenticated resume access',
    ],
];

@endphp

@extends('layout')

@section('title', 'Resume Management')

@section('main')
    <div class="max-w-4xl mx-auto px-4 py-8">
        <a href="{{ route('admin.index') }}" class="text-sm text-primary hover:underline">&larr; Back to Admin</a>
        <h1 class="text-3xl font-heading font-bold text-primary mt-2 mb-8">Resume Management</h1>

        <div class="grid gap-6 md:grid-cols-2">
            @foreach($nav_blocks as $nav)
                @can($nav['can'])
                    <x-admin.nav-block route="{{ $nav['route'] }}" icon="{{ $nav['icon'] }}" label="{{ $nav['label'] }}"
                        description="{{ $nav['description'] }}" />
                @endcan
            @endforeach
        </div>
    </div>
@endsection
