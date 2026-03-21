<div class="space-y-6">
    <div>
        <label for="feature" class="block text-sm font-medium text-gray-700 mb-1">Feature</label>
        @if(isset($memory))
            <input type="text" value="{{ $memory->feature }}" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm bg-gray-50" disabled>
        @else
            <input type="text" name="feature" id="feature" value="{{ old('feature', 'targeted-resume') }}"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary/20 focus:border-primary"
                required>
        @endif
    </div>

    <div>
        <label for="category" class="block text-sm font-medium text-gray-700 mb-1">Category</label>
        <select name="category" id="category"
            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary/20 focus:border-primary"
            required>
            <option value="preference" @selected(old('category', $memory->category ?? '') === 'preference')>Preference</option>
            <option value="domain_knowledge" @selected(old('category', $memory->category ?? '') === 'domain_knowledge')>Domain Knowledge</option>
            <option value="system_tuning" @selected(old('category', $memory->category ?? '') === 'system_tuning')>System Tuning</option>
        </select>
    </div>

    <div>
        <label for="key" class="block text-sm font-medium text-gray-700 mb-1">Key</label>
        <input type="text" name="key" id="key" value="{{ old('key', $memory->key ?? '') }}"
            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono focus:ring-2 focus:ring-primary/20 focus:border-primary"
            placeholder="descriptive-kebab-case-key"
            required>
        <p class="text-xs text-gray-500 mt-1">Unique identifier in lowercase-kebab-case</p>
    </div>

    <div>
        <label for="content" class="block text-sm font-medium text-gray-700 mb-1">Content</label>
        <textarea name="content" id="content" rows="4"
            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary/20 focus:border-primary"
            required>{{ old('content', $memory->content ?? '') }}</textarea>
    </div>

    <div class="grid grid-cols-2 gap-6">
        <div>
            <label for="confidence" class="block text-sm font-medium text-gray-700 mb-1">Confidence (1-100)</label>
            <input type="number" name="confidence" id="confidence" min="1" max="100"
                value="{{ old('confidence', $memory->confidence ?? 50) }}"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary/20 focus:border-primary"
                required>
        </div>

        <div class="flex items-end pb-1">
            <label class="flex items-center gap-2 cursor-pointer">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" value="1"
                    class="rounded border-gray-300 text-primary focus:ring-primary/20"
                    @checked(old('is_active', $memory->is_active ?? true))>
                <span class="text-sm text-gray-700">Active</span>
            </label>
        </div>
    </div>
</div>
