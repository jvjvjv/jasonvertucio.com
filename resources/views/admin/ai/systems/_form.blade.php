@php
    $system = $system ?? null;
    $isEdit = (bool) $system;
    $allFeatures = ['targeted-resume', 'cover-letter'];
    $currentDefaults = $system ? $system->featureDefaults->pluck('feature')->toArray() : [];
@endphp

<div x-data="aiSystemForm({ isEdit: {{ $isEdit ? 'true' : 'false' }} })" class="space-y-6">
    {{-- Name --}}
    <div>
        <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Name</label>
        <input type="text" name="name" id="name" value="{{ old('name', $system?->name) }}"
            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary"
            placeholder="e.g., Claude Sonnet for Resumes" required>
    </div>

    @if($isEdit)
        {{-- Provider (read-only on edit) --}}
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Provider</label>
            <p class="px-3 py-2 text-sm bg-gray-50 border border-gray-200 rounded-lg text-gray-700">
                {{ ucfirst($system->provider) }}
            </p>
        </div>

        {{-- API Key (read-only on edit) --}}
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">API Key</label>
            <p class="px-3 py-2 text-sm bg-gray-50 border border-gray-200 rounded-lg text-gray-500 font-mono">
                ••••••••
            </p>
        </div>

        {{-- Model (read-only on edit) --}}
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Model</label>
            <p class="px-3 py-2 text-sm bg-gray-50 border border-gray-200 rounded-lg text-gray-700 font-mono">
                {{ $system->model }}
            </p>
        </div>

        <p class="text-xs text-gray-400">Provider, API key, and model cannot be changed after creation. Use <strong>Duplicate</strong> to create a new system with different settings.</p>
    @else
        {{-- Provider (editable on create) --}}
        <div>
            <label for="provider" class="block text-sm font-medium text-gray-700 mb-1">Provider</label>
            <select name="provider" id="provider" x-model="provider" @change="onProviderChange()"
                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary" required>
                <option value="">Select provider...</option>
                <option value="anthropic" @selected(old('provider') === 'anthropic')>Anthropic</option>
            </select>
        </div>

        {{-- API Key (editable on create) --}}
        <div>
            <label for="api_key" class="block text-sm font-medium text-gray-700 mb-1">API Key</label>
            <input type="password" name="api_key" id="api_key" x-model="apiKey" @blur="fetchModels()"
                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm font-mono focus:border-primary focus:ring-1 focus:ring-primary"
                placeholder="sk-..." required>
        </div>

        {{-- Model (dropdown with fetch on create) --}}
        <div>
            <label for="model" class="block text-sm font-medium text-gray-700 mb-1">Model</label>

            <div x-show="isFetchingModels" class="flex items-center gap-2 px-3 py-2 text-sm text-gray-500">
                <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                </svg>
                Fetching models...
            </div>

            <template x-if="availableModels.length > 0 && !isFetchingModels">
                <select name="model" id="model"
                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm font-mono focus:border-primary focus:ring-1 focus:ring-primary" required>
                    <option value="">Select model...</option>
                    <template x-for="m in availableModels" :key="m.id">
                        <option :value="m.id" x-text="m.name + ' (' + m.id + ')'" :selected="m.id === '{{ old('model') }}'"></option>
                    </template>
                </select>
            </template>

            <template x-if="availableModels.length === 0 && !isFetchingModels">
                <div>
                    <input type="text" name="model" id="model" value="{{ old('model') }}"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm font-mono focus:border-primary focus:ring-1 focus:ring-primary"
                        placeholder="e.g., claude-sonnet-4-6" required>
                    <p class="mt-1 text-xs text-gray-400" x-show="provider && apiKey">
                        <button type="button" @click="fetchModels()" class="text-primary hover:underline">Fetch models from API</button>
                        or type the model ID manually.
                    </p>
                    <p class="mt-1 text-xs text-gray-400" x-show="!provider || !apiKey">
                        Select a provider and enter an API key to fetch available models.
                    </p>
                </div>
            </template>

            <p x-show="fetchError" class="mt-1 text-xs text-red-600" x-text="fetchError"></p>
        </div>
    @endif

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

    {{-- System Prompt --}}
    <div>
        <label for="system_prompt" class="block text-sm font-medium text-gray-700 mb-1">
            System Prompt <span class="text-gray-400 font-normal">(optional)</span>
        </label>
        <textarea name="system_prompt" id="system_prompt" rows="6"
            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary"
            placeholder="Base system prompt for this AI system. Features may prepend or override this.">{{ old('system_prompt', $system?->system_prompt) }}</textarea>
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

@unless($isEdit)
<script>
function aiSystemForm(config) {
    return {
        provider: '{{ old('provider') }}',
        apiKey: '',
        availableModels: [],
        isFetchingModels: false,
        fetchError: null,

        onProviderChange() {
            this.availableModels = [];
            this.fetchError = null;
            if (this.apiKey) {
                this.fetchModels();
            }
        },

        async fetchModels() {
            if (!this.provider || !this.apiKey) return;

            this.isFetchingModels = true;
            this.fetchError = null;

            try {
                const response = await fetch('{{ route("admin.ai.systems.fetch-models") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        provider: this.provider,
                        api_key: this.apiKey,
                    }),
                });

                const data = await response.json();

                if (!response.ok) {
                    this.fetchError = data.error || data.message || 'Failed to fetch models.';
                    this.availableModels = [];
                    return;
                }

                this.availableModels = data.models || [];
            } catch (err) {
                this.fetchError = 'Network error fetching models.';
                this.availableModels = [];
            } finally {
                this.isFetchingModels = false;
            }
        },
    };
}
</script>
@endunless
