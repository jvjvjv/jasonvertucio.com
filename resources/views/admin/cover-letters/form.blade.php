{{-- Cover Letter Form Partial --}}
{{-- Variables expected: $coverLetter (optional, for edit), $errors --}}

@php
    $cl = $coverLetter ?? null;
    $resumeVersions = $resumeVersions ?? collect();
    $selectedResumeVersionId = old('resume_version_id')
        ?? optional($cl)->resume_version_id
        ?? optional($resumeVersions->firstWhere('is_current', true))->id
        ?? optional($resumeVersions->first())->id;
@endphp

<div class="space-y-6">
    {{-- Resume Version --}}
    <div class="md:w-1/2">
        <label for="resume_version_id" class="block text-sm font-medium text-gray-700 mb-1">Resume Version</label>
        <select id="resume_version_id" name="resume_version_id"
            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:border-primary @error('resume_version_id') border-red-400 @enderror"
            required>
            <option value="">Select a resume version</option>
            @foreach($resumeVersions as $resumeVersion)
                <option value="{{ $resumeVersion->id }}"
                    @selected($selectedResumeVersionId == $resumeVersion->id)>
                    {{ $resumeVersion->version }}@if($resumeVersion->is_current) (Current)@endif
                </option>
            @endforeach
        </select>
        @error('resume_version_id')
            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
        @enderror
    </div>

    {{-- Row: Company + Position --}}
    <div class="grid gap-4 md:grid-cols-2">
        <div>
            <label for="company_name" class="block text-sm font-medium text-gray-700 mb-1">Company Name</label>
            <input type="text" id="company_name" name="company_name"
                value="{{ old('company_name', $cl?->company_name) }}"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:border-primary @error('company_name') border-red-400 @enderror"
                placeholder="Acme Corp"
                required>
            @error('company_name')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>
        <div>
            <label for="position" class="block text-sm font-medium text-gray-700 mb-1">Position</label>
            <input type="text" id="position" name="position"
                value="{{ old('position', $cl?->position) }}"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:border-primary @error('position') border-red-400 @enderror"
                placeholder="Senior Software Engineer"
                required>
            @error('position')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>
    </div>

    {{-- Date --}}
    <div class="md:w-1/3">
        <label for="date" class="block text-sm font-medium text-gray-700 mb-1">Date</label>
        <input type="date" id="date" name="date"
            value="{{ old('date', $cl?->date?->format('Y-m-d') ?? now()->format('Y-m-d')) }}"
            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:border-primary @error('date') border-red-400 @enderror"
            required>
        @error('date')
            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
        @enderror
    </div>

    {{-- Company Address --}}
    <div>
        <label for="company_address" class="block text-sm font-medium text-gray-700 mb-1">Company Address</label>
        <p class="text-xs text-gray-500 mb-1">One address line per line. Newlines are preserved in the document.</p>
        <textarea id="company_address" name="company_address" rows="4"
            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:border-primary font-mono @error('company_address') border-red-400 @enderror"
            placeholder="123 Main Street&#10;Suite 100&#10;San Francisco, CA 94105">{{ old('company_address', $cl?->company_address) }}</textarea>
        @error('company_address')
            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
        @enderror
    </div>

    {{-- Greeting --}}
    <div>
        <label for="greeting" class="block text-sm font-medium text-gray-700 mb-1">Greeting</label>
        <input type="text" id="greeting" name="greeting"
            value="{{ old('greeting', $cl?->greeting) }}"
            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:border-primary @error('greeting') border-red-400 @enderror"
            placeholder="Dear Hiring Manager,"
            required>
        @error('greeting')
            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
        @enderror
    </div>

    {{-- Message Body --}}
    <div>
        <label for="message_body" class="block text-sm font-medium text-gray-700 mb-1">Message Body</label>
        <p class="text-xs text-gray-500 mb-1">Write in Markdown. Blank lines create paragraph breaks in the document.</p>
        <textarea id="message_body" name="message_body" rows="16"
            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:border-primary font-mono @error('message_body') border-red-400 @enderror"
            required>{{ old('message_body', $cl?->message_body) }}</textarea>
        @error('message_body')
            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
        @enderror
    </div>

    {{-- Closing + Signature --}}
    <div class="grid gap-4 md:grid-cols-2">
        <div>
            <label for="closing" class="block text-sm font-medium text-gray-700 mb-1">Closing</label>
            <input type="text" id="closing" name="closing"
                value="{{ old('closing', $cl?->closing) }}"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:border-primary @error('closing') border-red-400 @enderror"
                placeholder="Sincerely,">
            @error('closing')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>
        <div>
            <label for="signature" class="block text-sm font-medium text-gray-700 mb-1">Signature</label>
            <input type="text" id="signature" name="signature"
                value="{{ old('signature', $cl?->signature) }}"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:border-primary @error('signature') border-red-400 @enderror"
                placeholder="Jason Vertucio">
            @error('signature')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>
    </div>
</div>
