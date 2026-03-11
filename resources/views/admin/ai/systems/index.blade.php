@extends('layout')

@section('title', 'AI Systems')

@section('main')
    <div class="max-w-5xl mx-auto px-4 py-8">
        <a href="{{ route('admin.ai.index') }}" class="text-sm text-primary hover:underline">&larr; Back to AI Tools</a>

        <div class="flex items-center justify-between mt-2 mb-8">
            <h1 class="text-3xl font-heading font-bold text-primary">AI Systems</h1>
            <a href="{{ route('admin.ai.systems.create') }}"
                class="inline-flex items-center gap-2 px-4 py-2 bg-primary text-white text-sm font-medium rounded-lg hover:bg-primary/90 transition-colors">
                <i class="fa-classic fa-plus"></i>
                Add System
            </a>
        </div>

        @if(session('success'))
            <div class="mb-6 px-4 py-3 bg-green-50 border border-green-200 text-green-800 rounded-lg text-sm">
                {{ session('success') }}
            </div>
        @endif

        @if($systems->isEmpty())
            <div class="text-center py-16 text-gray-500">
                <i class="fa-classic fa-microchip text-4xl mb-4 block text-gray-300"></i>
                <p class="text-lg">No AI systems configured yet.</p>
                <p class="text-sm mt-1">
                    <a href="{{ route('admin.ai.systems.create') }}" class="text-primary hover:underline">Add your first one</a>
                </p>
            </div>
        @else
            <div class="border border-gray-200 rounded-lg overflow-hidden">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-gray-50 border-b border-gray-200">
                            <th class="text-left px-4 py-3 font-semibold text-gray-700">Name</th>
                            <th class="text-left px-4 py-3 font-semibold text-gray-700">Provider</th>
                            <th class="text-left px-4 py-3 font-semibold text-gray-700">Model</th>
                            <th class="text-left px-4 py-3 font-semibold text-gray-700">Status</th>
                            <th class="text-left px-4 py-3 font-semibold text-gray-700">Default For</th>
                            <th class="text-left px-4 py-3 font-semibold text-gray-700">API Calls</th>
                            <th class="text-right px-4 py-3 font-semibold text-gray-700">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($systems as $system)
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="px-4 py-3 font-medium">
                                    <a href="{{ route('admin.ai.systems.edit', $system) }}"
                                        class="text-primary hover:underline">
                                        {{ $system->name }}
                                    </a>
                                </td>
                                <td class="px-4 py-3 text-gray-600">{{ ucfirst($system->provider) }}</td>
                                <td class="px-4 py-3 text-gray-600 font-mono text-xs">{{ $system->model }}</td>
                                <td class="px-4 py-3">
                                    @if($system->is_active)
                                        <span class="inline-flex items-center gap-1 text-green-700 text-xs font-medium">
                                            <span class="w-2 h-2 bg-green-500 rounded-full"></span>
                                            Active
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 text-gray-400 text-xs font-medium">
                                            <span class="w-2 h-2 bg-gray-300 rounded-full"></span>
                                            Inactive
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-gray-600 text-xs">
                                    @if($system->featureDefaults->isNotEmpty())
                                        @foreach($system->featureDefaults as $default)
                                            <span class="inline-block bg-blue-50 text-blue-700 px-2 py-0.5 rounded mr-1">{{ $default->feature }}</span>
                                        @endforeach
                                    @else
                                        <span class="text-gray-400">&mdash;</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-gray-600">
                                    <a href="{{ route('admin.ai.systems.logs', $system) }}"
                                        class="text-primary hover:underline">
                                        {{ $system->interaction_logs_count }}
                                    </a>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-end gap-2">
                                        <a href="{{ route('admin.ai.systems.edit', $system) }}"
                                            class="p-2 text-gray-500 hover:text-primary transition-colors"
                                            title="Edit">
                                            <i class="fa-classic fa-file-pen"></i>
                                        </a>
                                        <a href="{{ route('admin.ai.systems.logs', $system) }}"
                                            class="p-2 text-gray-500 hover:text-primary transition-colors"
                                            title="View logs">
                                            <i class="fa-classic fa-list"></i>
                                        </a>
                                        <form method="POST" action="{{ route('admin.ai.systems.destroy', $system) }}"
                                            onsubmit="return confirm('Delete this AI system? This cannot be undone.')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit"
                                                class="p-2 text-gray-500 hover:text-red-600 transition-colors"
                                                title="Delete">
                                                <i class="fa-classic fa-trash-can"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endsection
