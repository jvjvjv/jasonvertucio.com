@php

$nav_blocks = [
    [
        'can' => 'edit-resume',
        'route' => 'admin.resume.editor',
        'icon' => 'file-pen',
        'label' => 'Resume Editor',
        'description' => 'Edit resume content and generate DOCX files',
    ],
    [
        'can' => null,
        'route' => 'admin.resume.index',
        'icon' => 'file-code',
        'label' => 'Share Resume Codes',
        'description' => 'Share and manage codes for unauthenticated resume access',
    ]

];

@endphp

@extends('layout')

@section('title', 'Admin Dashboard')

@section('main')
    <div class="max-w-4xl mx-auto px-4 py-8">
        <h1 class="text-3xl font-heading font-bold text-primary mb-8">Admin Dashboard</h1>

        <div class="grid gap-6 md:grid-cols-2">
            @foreach($nav_blocks as $nav)
                @can($nav['can'])
                    <x-admin.nav-block route="{{  $nav['route'] }}" icon="{{ $nav['icon']}}" label="{{ $nav['label']}}"
                        description="{{ $nav['description'] }}" />
                @endcan
            @endforeach
        </div>
    </div>
@endsection
