@extends('layout')

@section('title', 'Resume Share Codes')

@section('main')
<div class="max-w-6xl mx-auto px-4 py-8">
    <div class="flex items-center justify-between mb-8">
        <div>
            <a href="{{ route('admin.index') }}" class="text-sm text-primary hover:underline">&larr; Back to Admin</a>
            <h1 class="text-3xl font-heading font-bold text-primary mt-2">Resume Share Codes</h1>
        </div>
    </div>

    @if(session('success'))
        <div class="mb-6 p-4 bg-green-100 border border-green-400 text-green-700 rounded-lg">
            {{ session('success') }}
        </div>
    @endif

    {{-- Create New Code Form --}}
    <div class="bg-white rounded-lg shadow-md p-6 mb-8 border border-gray-200">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Create New Share Code</h2>
        <form action="{{ route('admin.resume.codes.store') }}" method="POST" x-data="{ emailProvided: false, mailConfigured: {{ $mailConfigured ? 'true' : 'false' }} }">
            @csrf
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700 mb-1">
                        Recipient Name <span class="text-red-500">*</span>
                    </label>
                    <input type="text"
                           name="name"
                           id="name"
                           required
                           value="{{ old('name') }}"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary/50 focus:border-primary">
                    @error('name')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-1">
                        Recipient Email (optional)
                    </label>
                    <input type="email"
                           name="email"
                           id="email"
                           value="{{ old('email') }}"
                           @input="emailProvided = $el.value.trim() !== ''"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary/50 focus:border-primary">
                    @error('email')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <label for="expires_at" class="block text-sm font-medium text-gray-700 mb-1">
                        Expiration Date (optional)
                    </label>
                    <input type="date"
                           name="expires_at"
                           id="expires_at"
                           min="{{ date('Y-m-d') }}"
                           value="{{ old('expires_at') }}"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary/50 focus:border-primary">
                    @error('expires_at')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="mb-4">
                <label class="flex items-center gap-3">
                    <input type="checkbox"
                           name="send_email"
                           value="1"
                           :disabled="!mailConfigured || !emailProvided"
                           {{ old('send_email') ? 'checked' : '' }}
                           class="w-4 h-4 rounded cursor-pointer">
                    <span class="text-sm text-gray-700">Send email notification</span>
                </label>
                <template x-if="!mailConfigured">
                    <p class="mt-1 text-xs text-gray-500">(mail not configured)</p>
                </template>
            </div>

            <template x-if="emailProvided && mailConfigured">
                <p class="mb-4 text-sm text-blue-600">An email will be sent to this address once the code is created.</p>
            </template>

            <button type="submit"
                    class="px-6 py-2 bg-primary text-white font-medium rounded-md hover:bg-primary/90 transition-colors">
                Generate Code
            </button>
        </form>
    </div>

    {{-- Share Codes Table --}}
    <div class="bg-white rounded-lg shadow-md border border-gray-200 overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Code</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Recipient</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Expires</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Views</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Downloads</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200" x-data="{ expanded: null }">
                @forelse($codes as $code)
                    <tr class="{{ $code->trashed() ? 'bg-gray-50 opacity-60' : '' }}">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <code class="px-2 py-1 bg-gray-100 rounded text-sm font-mono">{{ $code->id }}</code>
                            @if(!$code->trashed() && !$code->isExpired())
                                <button type="button"
                                        onclick="navigator.clipboard.writeText('{{ url('/resume?code=' . $code->id) }}')"
                                        class="ml-2 text-xs text-primary hover:underline">
                                    Copy URL
                                </button>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                            {{ $code->name ?: '-' }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            @if($code->email)
                                <span class="flex items-center gap-1">
                                    @if($code->email_sent)
                                        <span class="text-green-600" title="Email sent">✓</span>
                                    @endif
                                    <span class="truncate max-w-xs" title="{{ $code->email }}">
                                        {{ $code->email }}
                                    </span>
                                </span>
                            @else
                                <span class="text-gray-400">-</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            {{ $code->created_at->format('M j, Y g:i A') }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            @if($code->expires_at)
                                {{ $code->expires_at->format('M j, Y') }}
                            @else
                                <span class="text-gray-400">Never</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            @if($code->views_count > 0)
                                <button type="button"
                                        @click="expanded = expanded === '{{ $code->id }}' ? null : '{{ $code->id }}'"
                                        class="text-primary hover:underline">
                                    {{ $code->views_count }} view{{ $code->views_count === 1 ? '' : 's' }}
                                </button>
                            @else
                                0 views
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            @if($code->downloads_count > 0)
                                <button type="button"
                                        @click="expanded = expanded === '{{ $code->id }}-downloads' ? null : '{{ $code->id }}-downloads'"
                                        class="text-primary hover:underline">
                                    {{ $code->downloads_count }} download{{ $code->downloads_count === 1 ? '' : 's' }}
                                </button>
                            @else
                                0 downloads
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            @if($code->trashed())
                                <span class="px-2 py-1 text-xs font-medium bg-red-100 text-red-800 rounded-full">
                                    Invalidated
                                </span>
                            @elseif($code->isExpired())
                                <span class="px-2 py-1 text-xs font-medium bg-yellow-100 text-yellow-800 rounded-full">
                                    Expired
                                </span>
                            @else
                                <span class="px-2 py-1 text-xs font-medium bg-green-100 text-green-800 rounded-full">
                                    Active
                                </span>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                            @if(!$code->trashed())
                                <form action="{{ route('admin.resume.codes.destroy', $code->id) }}"
                                      method="POST"
                                      class="inline"
                                      onsubmit="return confirm('Are you sure you want to invalidate this code?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-600 hover:text-red-800 hover:underline">
                                        Invalidate
                                    </button>
                                </form>
                            @else
                                <span class="text-gray-400">-</span>
                            @endif
                        </td>
                    </tr>
                    {{-- Expanded views row --}}
                    @if($code->views->count() > 0)
                        <tr x-show="expanded === '{{ $code->id }}'" x-cloak class="bg-gray-50">
                            <td colspan="7" class="px-6 py-4">
                                <div class="text-sm">
                                    <h4 class="font-medium text-gray-700 mb-2">View History</h4>
                                    <div class="max-h-48 overflow-y-auto">
                                        <table class="min-w-full text-xs">
                                            <thead>
                                                <tr class="text-gray-500">
                                                    <th class="text-left pr-4 pb-1">Date</th>
                                                    <th class="text-left pr-4 pb-1">IP Address</th>
                                                    <th class="text-left pb-1">User Agent</th>
                                                </tr>
                                            </thead>
                                            <tbody class="text-gray-600">
                                                @foreach($code->views as $view)
                                                    <tr>
                                                        <td class="pr-4 py-1">{{ $view->created_at->format('M j, Y g:i A') }}</td>
                                                        <td class="pr-4 py-1 font-mono">{{ $view->ip_address }}</td>
                                                        <td class="py-1 truncate max-w-xs" title="{{ $view->user_agent }}">
                                                            {{ Str::limit($view->user_agent, 60) }}
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @endif
                    {{-- Expanded downloads row --}}
                    @if($code->downloads->count() > 0)
                        <tr x-show="expanded === '{{ $code->id }}-downloads'" x-cloak class="bg-blue-50">
                            <td colspan="8" class="px-6 py-4">
                                <div class="text-sm">
                                    <h4 class="font-medium text-gray-700 mb-2">Download History</h4>
                                    <div class="max-h-48 overflow-y-auto">
                                        <table class="min-w-full text-xs">
                                            <thead>
                                                <tr class="text-gray-500">
                                                    <th class="text-left pr-4 pb-1">Date</th>
                                                    <th class="text-left pr-4 pb-1">Version</th>
                                                    <th class="text-left pr-4 pb-1">IP Address</th>
                                                    <th class="text-left pb-1">User Agent</th>
                                                </tr>
                                            </thead>
                                            <tbody class="text-gray-600">
                                                @foreach($code->downloads as $download)
                                                    <tr>
                                                        <td class="pr-4 py-1">{{ $download->created_at->format('M j, Y g:i A') }}</td>
                                                        <td class="pr-4 py-1 font-mono">{{ $download->version }}</td>
                                                        <td class="pr-4 py-1 font-mono">{{ $download->ip_address }}</td>
                                                        <td class="py-1 truncate max-w-xs" title="{{ $download->user_agent }}">
                                                            {{ Str::limit($download->user_agent, 60) }}
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @endif
                @empty
                    <tr>
                        <td colspan="8" class="px-6 py-8 text-center text-gray-500">
                            No share codes created yet.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<style>
    [x-cloak] { display: none !important; }
</style>
@endsection
