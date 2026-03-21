@extends('layout')

@section('title', 'AI Memory')

@section('main')
    <div class="max-w-5xl mx-auto px-4 py-8">
        <a href="{{ route('admin.ai.index') }}" class="text-sm text-primary hover:underline">&larr; Back to AI Tools</a>

        <div class="flex items-center justify-between mt-2 mb-8">
            <h1 class="text-3xl font-heading font-bold text-primary">AI Memory</h1>
            <a href="{{ route('admin.ai.memories.create') }}"
                class="inline-flex items-center gap-2 px-4 py-2 bg-primary text-white text-sm font-medium rounded-lg hover:bg-primary/90 transition-colors">
                <i class="fa-classic fa-plus"></i>
                Add Memory
            </a>
        </div>

        @if(session('success'))
            <div class="mb-6 px-4 py-3 bg-green-50 border border-green-200 text-green-800 rounded-lg text-sm">
                {{ session('success') }}
            </div>
        @endif

        {{-- Filters --}}
        <form method="GET" action="{{ route('admin.ai.memories.index') }}" class="flex flex-wrap items-center gap-3 mb-6">
            <select name="feature" class="text-sm border border-gray-300 rounded-lg px-3 py-2">
                <option value="">All Features</option>
                @foreach($features as $f)
                    <option value="{{ $f }}" @selected(request('feature') === $f)>{{ $f }}</option>
                @endforeach
            </select>

            <select name="category" class="text-sm border border-gray-300 rounded-lg px-3 py-2">
                <option value="">All Categories</option>
                <option value="preference" @selected(request('category') === 'preference')>Preference</option>
                <option value="domain_knowledge" @selected(request('category') === 'domain_knowledge')>Domain Knowledge</option>
                <option value="system_tuning" @selected(request('category') === 'system_tuning')>System Tuning</option>
            </select>

            <select name="status" class="text-sm border border-gray-300 rounded-lg px-3 py-2">
                <option value="">All Status</option>
                <option value="active" @selected(request('status') === 'active')>Active</option>
                <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
            </select>

            <button type="submit"
                class="px-4 py-2 bg-gray-100 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-200 transition-colors">
                Filter
            </button>

            @if(request()->hasAny(['feature', 'category', 'status']))
                <a href="{{ route('admin.ai.memories.index') }}" class="text-sm text-gray-500 hover:underline">Clear</a>
            @endif
        </form>

        {{-- Rebuild buttons --}}
        @if($features->isNotEmpty())
            <div class="flex flex-wrap items-center gap-2 mb-6">
                <span class="text-sm text-gray-500">Rebuild:</span>
                @foreach($features as $f)
                    <form method="POST" action="{{ route('admin.ai.memories.rebuild', $f) }}"
                        onsubmit="return confirm('Rebuild all memories for {{ $f }}? This deactivates existing memories and re-processes all completed conversations.')">
                        @csrf
                        <button type="submit"
                            class="inline-flex items-center gap-1 px-3 py-1.5 bg-amber-50 text-amber-700 text-xs font-medium rounded-lg border border-amber-200 hover:bg-amber-100 transition-colors">
                            <i class="fa-classic fa-arrows-rotate"></i>
                            {{ $f }}
                        </button>
                    </form>
                @endforeach
            </div>
        @endif

        @if($memories->isEmpty())
            <div class="text-center py-16 text-gray-500">
                <i class="fa-classic fa-brain text-4xl mb-4 block text-gray-300"></i>
                <p class="text-lg">No memories yet.</p>
                <p class="text-sm mt-1">Memories are automatically created when AI conversations are completed.</p>
            </div>
        @else
            <div class="border border-gray-200 rounded-lg overflow-hidden">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-gray-50 border-b border-gray-200">
                            <th class="text-left px-4 py-3 font-semibold text-gray-700">Key</th>
                            <th class="text-left px-4 py-3 font-semibold text-gray-700">Category</th>
                            <th class="text-left px-4 py-3 font-semibold text-gray-700">Content</th>
                            <th class="text-center px-4 py-3 font-semibold text-gray-700">Confidence</th>
                            <th class="text-center px-4 py-3 font-semibold text-gray-700">Reinforced</th>
                            <th class="text-center px-4 py-3 font-semibold text-gray-700">Status</th>
                            <th class="text-right px-4 py-3 font-semibold text-gray-700">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($memories as $memory)
                            <tr class="hover:bg-gray-50 transition-colors {{ !$memory->is_active ? 'opacity-50' : '' }}">
                                <td class="px-4 py-3 font-mono text-xs">
                                    <a href="{{ route('admin.ai.memories.edit', $memory) }}"
                                        class="text-primary hover:underline">
                                        {{ $memory->key }}
                                    </a>
                                </td>
                                <td class="px-4 py-3">
                                    @php
                                        $categoryColors = [
                                            'preference' => 'bg-purple-50 text-purple-700',
                                            'domain_knowledge' => 'bg-blue-50 text-blue-700',
                                            'system_tuning' => 'bg-green-50 text-green-700',
                                        ];
                                        $color = $categoryColors[$memory->category] ?? 'bg-gray-50 text-gray-700';
                                    @endphp
                                    <span class="inline-block {{ $color }} px-2 py-0.5 rounded text-xs">
                                        {{ str_replace('_', ' ', $memory->category) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-gray-600 max-w-xs truncate" title="{{ $memory->content }}">
                                    {{ Str::limit($memory->content, 80) }}
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span class="inline-block min-w-[2.5rem] text-center px-2 py-0.5 rounded text-xs font-medium
                                        {{ $memory->confidence >= 75 ? 'bg-green-50 text-green-700' : ($memory->confidence >= 50 ? 'bg-yellow-50 text-yellow-700' : 'bg-red-50 text-red-700') }}">
                                        {{ $memory->confidence }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-center text-gray-600">
                                    {{ $memory->times_reinforced }}x
                                </td>
                                <td class="px-4 py-3 text-center">
                                    @if($memory->is_active)
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
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-end gap-2">
                                        <a href="{{ route('admin.ai.memories.edit', $memory) }}"
                                            class="p-2 text-gray-500 hover:text-primary transition-colors"
                                            title="Edit">
                                            <i class="fa-classic fa-file-pen"></i>
                                        </a>
                                        <form method="POST" action="{{ route('admin.ai.memories.destroy', $memory) }}"
                                            onsubmit="return confirm('Delete this memory entry?')">
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

            <div class="mt-4">
                {{ $memories->withQueryString()->links() }}
            </div>
        @endif
    </div>
@endsection
