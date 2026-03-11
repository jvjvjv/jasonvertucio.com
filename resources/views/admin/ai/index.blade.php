@php

$nav_blocks = [
    [
        'can' => null,
        'route' => 'admin.ai.systems.index',
        'icon' => 'microchip',
        'label' => 'AI Systems',
        'description' => 'Manage AI providers, API keys, and feature defaults',
    ],
];

@endphp

@extends('layout')

@section('title', 'AI Tools')

@section('main')
    <div class="max-w-4xl mx-auto px-4 py-8">
        <a href="{{ route('admin.index') }}" class="text-sm text-primary hover:underline">&larr; Back to Admin</a>
        <h1 class="text-3xl font-heading font-bold text-primary mt-2 mb-8">AI Tools</h1>

        <div class="grid gap-6 md:grid-cols-2">
            @foreach($nav_blocks as $nav)
                @if(is_null($nav['can']) || auth()->user()?->can($nav['can']))
                    <x-admin.nav-block route="{{ $nav['route'] }}" icon="{{ $nav['icon'] }}" label="{{ $nav['label'] }}"
                        description="{{ $nav['description'] }}" />
                @endif
            @endforeach
        </div>
    </div>
@endsection
