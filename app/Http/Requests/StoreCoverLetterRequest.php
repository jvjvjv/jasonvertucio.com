<?php

namespace App\Http\Requests;

use App\Models\Application;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCoverLetterRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * A letter written for an application that names no resume version
     * takes the application's own, so the letter and the job agree on which
     * resume was sent. The version is still required of a standalone letter.
     */
    protected function prepareForValidation(): void
    {
        if ($this->filled('resume_version_id') || ! $this->filled('application_id') || ! $this->mayLinkApplication()) {
            return;
        }

        $resumeVersionId = Application::query()
            ->whereKey($this->input('application_id'))
            ->value('resume_version_id');

        if ($resumeVersionId !== null) {
            $this->merge(['resume_version_id' => $resumeVersionId]);
        }
    }

    /**
     * Cover letters are managed under a different permission than
     * applications. Linking a letter to an application reads and changes
     * application data, so it also needs the applications' own permission.
     */
    private function mayLinkApplication(): bool
    {
        return (bool) $this->user()?->can('edit-resume');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'resume_version_id' => ['required', 'integer', 'exists:resume_versions,id'],
            'application_id' => $this->mayLinkApplication()
                ? ['nullable', 'integer', Rule::exists('applications', 'id')->withoutTrashed()]
                : ['prohibited'],
            'company_name' => ['required', 'string', 'max:255'],
            'position' => ['required', 'string', 'max:255'],
            'date' => ['required', 'date'],
            'company_address' => ['nullable', 'string'],
            'greeting' => ['required', 'string', 'max:255'],
            'message_body' => ['required', 'string'],
            'closing' => ['nullable', 'string', 'max:255'],
            'signature' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Get the custom error messages for the defined rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'application_id.exists' => 'That application no longer exists.',
        ];
    }
}
