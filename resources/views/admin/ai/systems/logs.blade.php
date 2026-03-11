@extends('layout')

@section('title', 'AI System Logs — ' . $aiSystem->name)

@section('main')
    <div class="max-w-6xl mx-auto px-4 py-8">
        <a href="{{ route('admin.ai.systems.index') }}" class="text-sm text-primary hover:underline">&larr; Back to AI Systems</a>
        <h1 class="text-3xl font-heading font-bold text-primary mt-2 mb-8">Logs: {{ $aiSystem->name }}</h1>

        @if($logs->isEmpty())
            <div class="text-center py-16 text-gray-500">
                <i class="fa-classic fa-list text-4xl mb-4 block text-gray-300"></i>
                <p class="text-lg">No interaction logs yet.</p>
                <p class="text-sm mt-1 text-gray-400">Logs will appear here once this system processes requests.</p>
            </div>
        @else
            <div class="border border-gray-200 rounded-lg overflow-hidden">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-gray-50 border-b border-gray-200">
                            <th class="text-left px-4 py-3 font-semibold text-gray-700">Date</th>
                            <th class="text-left px-4 py-3 font-semibold text-gray-700">User</th>
                            <th class="text-left px-4 py-3 font-semibold text-gray-700">Feature</th>
                            <th class="text-left px-4 py-3 font-semibold text-gray-700">Model</th>
                            <th class="text-right px-4 py-3 font-semibold text-gray-700">Tokens In</th>
                            <th class="text-right px-4 py-3 font-semibold text-gray-700">Tokens Out</th>
                            <th class="text-right px-4 py-3 font-semibold text-gray-700">Duration</th>
                            <th class="text-left px-4 py-3 font-semibold text-gray-700">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($logs as $log)
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="px-4 py-3 text-gray-600 text-xs">{{ $log->created_at->format('M j, Y g:ia') }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ $log->user?->name ?? '—' }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-block bg-blue-50 text-blue-700 px-2 py-0.5 rounded text-xs">{{ $log->feature }}</span>
                                </td>
                                <td class="px-4 py-3 text-gray-600 font-mono text-xs">{{ $log->model }}</td>
                                <td class="px-4 py-3 text-gray-600 text-right">{{ $log->input_tokens ? number_format($log->input_tokens) : '—' }}</td>
                                <td class="px-4 py-3 text-gray-600 text-right">{{ $log->output_tokens ? number_format($log->output_tokens) : '—' }}</td>
                                <td class="px-4 py-3 text-gray-600 text-right">{{ $log->duration_ms ? number_format($log->duration_ms) . 'ms' : '—' }}</td>
                                <td class="px-4 py-3">
                                    @if($log->status === 'success')
                                        <span class="text-green-600 text-xs font-medium">Success</span>
                                    @else
                                        <span class="text-red-600 text-xs font-medium" title="{{ $log->error_message }}">Error</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-6">
                {{ $logs->links() }}
            </div>
        @endif
    </div>
@endsection
