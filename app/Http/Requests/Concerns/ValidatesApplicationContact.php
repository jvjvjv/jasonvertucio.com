<?php

namespace App\Http\Requests\Concerns;

use App\Models\Application;
use App\Models\ApplicationContact;
use Illuminate\Validation\Rule;

/**
 * The rules shared by adding and editing a contact. The email is compared
 * lower-cased, as it is stored, so it is unique within the application
 * ignoring case.
 */
trait ValidatesApplicationContact
{
    /**
     * @return array<string, array<int, mixed>>
     */
    protected function contactRules(?ApplicationContact $ignore = null): array
    {
        /** @var Application $application */
        $application = $this->route('application');

        return [
            'email' => [
                'required',
                'string',
                'max:254',
                'email:rfc',
                Rule::unique('application_contacts', 'email')
                    ->where('application_id', $application->id)
                    ->ignore($ignore?->id),
            ],
            'name' => ['nullable', 'string', 'max:191'],
            'role' => ['nullable', 'string', 'max:191'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.required' => 'Enter the contact\'s email address.',
            'email.email' => 'That is not a valid email address.',
            'email.max' => 'Email addresses are limited to 254 characters.',
            'email.unique' => 'This application already has a contact with that email address.',
            'name.max' => 'Names are limited to 191 characters.',
            'role.max' => 'Roles are limited to 191 characters.',
            'notes.max' => 'Notes are limited to 2,000 characters.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower(trim($this->input('email')))]);
        }
    }

    /**
     * @return array{email: string, name: ?string, role: ?string, notes: ?string}
     */
    public function contact(): array
    {
        return [
            'email' => $this->validated('email'),
            'name' => $this->blankToNull($this->validated('name')),
            'role' => $this->blankToNull($this->validated('role')),
            'notes' => $this->blankToNull($this->validated('notes')),
        ];
    }

    private function blankToNull(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }
}
