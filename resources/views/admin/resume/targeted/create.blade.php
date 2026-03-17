@extends('layout')

@section('title', 'New Targeted Resume')

@section('main')
    <div x-data="targetedResumeForm()" class="max-w-4xl mx-auto px-4 py-8">
        <a href="{{ route('admin.resume.targeted.index') }}" class="text-sm text-primary hover:underline">&larr; Back to Targeted Resumes</a>
        <h1 class="text-3xl font-heading font-bold text-primary mt-2 mb-2">Targeted Resume Builder</h1>
        <p class="text-sm text-gray-500 mb-8">Paste a job description and let AI help tailor your resume for the role.</p>

        @if($errors->any())
            <div class="mb-6 px-4 py-3 bg-red-50 border border-red-200 text-red-800 rounded-lg text-sm">
                <p class="font-medium mb-1">Please fix the following errors:</p>
                <ul class="list-disc list-inside space-y-1">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div x-show="error" class="mb-6 px-4 py-3 bg-red-50 border border-red-200 text-red-800 rounded-lg text-sm">
            <p x-text="error"></p>
        </div>

        <form @submit.prevent="submitForm" class="space-y-6">
            @csrf

            {{-- AI System --}}
            <div>
                <label for="ai_system_id" class="block text-sm font-medium text-gray-700 mb-1">AI System</label>
                <select name="ai_system_id" id="ai_system_id" x-model="formData.ai_system_id"
                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary" required>
                    <option value="">Select AI system...</option>
                    @foreach($systems as $system)
                        <option value="{{ $system->id }}" @selected($system->id == $defaultSystemId)>
                            {{ $system->name }} ({{ $system->model }})
                        </option>
                    @endforeach
                </select>
                @if($systems->isEmpty())
                    <p class="mt-1 text-xs text-red-500">
                        No active AI systems found.
                        <a href="{{ route('admin.ai.systems.create') }}" class="underline">Add one first</a>.
                    </p>
                @endif
            </div>

            {{-- Job Title --}}
            <div>
                <label for="job_title" class="block text-sm font-medium text-gray-700 mb-1">
                    Job Title <span class="text-gray-400 font-normal">(optional)</span>
                </label>
                <input type="text" name="job_title" id="job_title" x-model="formData.job_title"
                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary"
                    placeholder="e.g., Senior Software Engineer">
            </div>

            {{-- Job Description --}}
            <div>
                <label for="job_description" class="block text-sm font-medium text-gray-700 mb-1">Job Description</label>
                <textarea name="job_description" id="job_description" x-model="formData.job_description" rows="12"
                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary"
                    placeholder="Paste the full job description here. This can be a copy/paste from LinkedIn or any job board." required></textarea>
                <p class="mt-1 text-xs text-gray-400">Tip: Copy and paste the entire job posting including company info, requirements, and qualifications.</p>
            </div>

            <div class="flex items-center justify-end gap-4">
                <a href="{{ route('admin.resume.targeted.index') }}" class="text-sm text-gray-600 hover:underline">Cancel</a>
                <button type="submit" :disabled="isSubmitting"
                    class="inline-flex items-center gap-2 px-5 py-2.5 bg-primary text-white text-sm font-medium rounded-lg hover:bg-primary/90 transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                    <template x-if="isSubmitting">
                        <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                    </template>
                    <template x-if="!isSubmitting">
                        <i class="fa-classic fa-robot"></i>
                    </template>
                    <span x-text="isSubmitting ? 'Starting...' : 'Start Analysis'"></span>
                </button>
            </div>
        </form>
    </div>

    <script>
    function targetedResumeForm() {
        return {
            formData: {
                ai_system_id: '{{ $defaultSystemId ?? '' }}',
                job_title: '',
                job_description: '',
            },
            isSubmitting: false,
            error: null,

            async submitForm() {
                this.isSubmitting = true;
                this.error = null;

                try {
                    const response = await fetch('{{ route('admin.resume.targeted.start') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify(this.formData),
                    });

                    const data = await response.json();

                    if (!response.ok) {
                        if (data.errors) {
                            this.error = Object.values(data.errors).flat().join('. ');
                        } else {
                            this.error = data.message || 'Failed to start analysis.';
                        }
                        return;
                    }

                    // Redirect to the conversation page
                    window.location.replace(data.redirect);
                } catch (err) {
                    this.error = 'Network error. Please try again.';
                } finally {
                    this.isSubmitting = false;
                }
            }
        };
    }
    </script>
@endsection
