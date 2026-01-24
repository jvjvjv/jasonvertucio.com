@extends('layout')

@section('title', 'Admin Dashboard')

@section('main')
        <div class="max-w-4xl mx-auto px-4 py-8">
            <h1 class="text-3xl font-heading font-bold text-primary mb-8">Admin Dashboard</h1>

            <div class="grid gap-6 md:grid-cols-2">

                <x-admin.nav-block route="admin.resume.index" icon="file-code" label="Resume Share Codes"
                    description="Manage share codes for unauthenticated resume access" />

                @can('edit-resume')
                    <x-admin.nav-block route="admin.resume.editor" icon="file-pen" label="Resume Editor"
                        description="Edit resume content and generate DOCX files" />
                @endcan

            </div>
    </div>
@endsection
