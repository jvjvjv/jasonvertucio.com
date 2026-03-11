@php
    $system = $system ?? null;
    $allFeatures = ['targeted-resume', 'cover-letter'];
    $currentDefaults = $system ? $system->featureDefaults->pluck('feature')->toArray() : [];
@endphp

<div class="space-y-6">
    {{-- Name --}}
    <div>
        <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Name</label>
        <input type="text" name="name" id="name" value="{{ old('name', $system?->name) }}"
            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary"
            placeholder="e.g., Claude Sonnet for Resumes" required>
    </div>

    {{-- Provider --}}
    <div>
        <label for="provider" class="block text-sm font-medium text-gray-700 mb-1">Provider</label>
        <select name="provider" id="provider"
            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary" required>
            <option value="">Select provider...</option>
            <option value="anthropic" @selected(old('provider', $system?->provider) === 'anthropic')>Anthropic</option>
            <option value="openai" @selected(old('provider', $system?->provider) === 'openai')>OpenAI</option>
        </select>
    </div>

    {{-- API Key --}}
    <div>
        <label for="api_key" class="block text-sm font-medium text-gray-700 mb-1">
            API Key
            @if($system)
                <span class="text-gray-400 font-normal">(leave blank to keep current)</span>
            @endif
        </label>
        <input type="password" name="api_key" id="api_key" value="{{ old('api_key') }}"
            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm font-mono focus:border-primary focus:ring-1 focus:ring-primary"
            placeholder="{{ $system ? '••••••••' : 'sk-...' }}" {{ $system ? '' : 'required' }}>
    </div>

    {{-- Model --}}
    <div>
        <label for="model" class="block text-sm font-medium text-gray-700 mb-1">Model</label>
        <input type="text" name="model" id="model" value="{{ old('model', $system?->model) }}"
            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm font-mono focus:border-primary focus:ring-1 focus:ring-primary"
            placeholder="e.g., claude-sonnet-4-6" required>
    </div>

    <div class="grid grid-cols-2 gap-6">
        {{-- Base URL --}}
        <div>
            <label for="base_url" class="block text-sm font-medium text-gray-700 mb-1">
                Base URL <span class="text-gray-400 font-normal">(optional)</span>
            </label>
            <input type="url" name="base_url" id="base_url" value="{{ old('base_url', $system?->base_url) }}"
                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary"
                placeholder="https://api.anthropic.com/v1">
        </div>

        {{-- API Version --}}
        <div>
            <label for="api_version" class="block text-sm font-medium text-gray-700 mb-1">
                API Version <span class="text-gray-400 font-normal">(optional)</span>
            </label>
            <input type="text" name="api_version" id="api_version" value="{{ old('api_version', $system?->api_version) }}"
                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary"
                placeholder="2023-06-01">
        </div>
    </div>

    <div class="grid grid-cols-2 gap-6">
        {{-- Max Tokens --}}
        <div>
            <label for="max_tokens" class="block text-sm font-medium text-gray-700 mb-1">Max Tokens</label>
            <input type="number" name="max_tokens" id="max_tokens" value="{{ old('max_tokens', $system?->max_tokens ?? 4096) }}"
                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary"
                min="1" max="200000" required>
        </div>

        {{-- Temperature --}}
        <div>
            <label for="temperature" class="block text-sm font-medium text-gray-700 mb-1">
                Temperature <span class="text-gray-400 font-normal">(optional, 0-1)</span>
            </label>
            <input type="number" name="temperature" id="temperature" value="{{ old('temperature', $system?->temperature) }}"
                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary"
                min="0" max="1" step="0.01">
        </div>
    </div>

    {{-- Active Status --}}
    <div class="flex items-center gap-2">
        <input type="hidden" name="is_active" value="0">
        <input type="checkbox" name="is_active" id="is_active" value="1"
            class="rounded border-gray-300 text-primary focus:ring-primary"
            @checked(old('is_active', $system?->is_active ?? true))>
        <label for="is_active" class="text-sm font-medium text-gray-700">Active</label>
    </div>

    {{-- Feature Defaults --}}
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-2">Default for Features</label>
        <div class="space-y-2">
            @foreach($allFeatures as $feature)
                @php
                    $isAssignedElsewhere = in_array($feature, $existingDefaults ?? []);
                @endphp
                <label class="flex items-center gap-2 {{ $isAssignedElsewhere ? 'opacity-50' : '' }}">
                    <input type="checkbox" name="feature_defaults[]" value="{{ $feature }}"
                        class="rounded border-gray-300 text-primary focus:ring-primary"
                        @checked(in_array($feature, old('feature_defaults', $currentDefaults)))
                        {{ $isAssignedElsewhere ? 'disabled' : '' }}>
                    <span class="text-sm text-gray-700">{{ $feature }}</span>
                    @if($isAssignedElsewhere)
                        <span class="text-xs text-gray-400">(assigned to another system)</span>
                    @endif
                </label>
            @endforeach
        </div>
    </div>

    {{-- Extra Config --}}
    <div>
        <label for="config" class="block text-sm font-medium text-gray-700 mb-1">
            Additional Config <span class="text-gray-400 font-normal">(JSON, optional)</span>
        </label>
        <textarea name="config" id="config" rows="3"
            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm font-mono focus:border-primary focus:ring-1 focus:ring-primary"
            placeholder='{"key": "value"}'>{{ old('config', $system?->config ? json_encode($system->config, JSON_PRETTY_PRINT) : '') }}</textarea>
    </div>
</div>
