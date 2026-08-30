<?php

namespace App\Services\Resume;

use InvalidArgumentException;

/**
 * Shape-checks one resume section before it is written into a draft snapshot.
 *
 * A persona proposing an edit hands over free-form decoded JSON, and a snapshot
 * is rendered straight into the resume views — so a section with the wrong shape
 * doesn't fail at the tool call, it fails later as a 500 on the review page.
 * The failure that prompted this: a persona sent `experience` as a single job
 * object instead of the full replacement list, and `@foreach($experience as $job)`
 * then iterated that job's own fields.
 *
 * Deliberately the same minimum the editor enforces on save
 * (`DatabaseResumeDataService::validate*`) plus the list/object distinction the
 * editor gets for free from its form, so a draft that passes here can be
 * approved without tripping validation at the finish line. The messages are
 * written to be read by the model that made the call, so it can correct itself
 * and retry rather than just seeing "invalid".
 */
class ResumeSectionValidator
{
    /**
     * Sections that are a list of entries, mapped to the fields every entry
     * must carry.
     *
     * @var array<string, array<int, string>>
     */
    private const LIST_SECTIONS = [
        'experience' => ['jobTitle', 'company'],
        'education' => ['institution'],
        'projects' => ['projectName'],
    ];

    /**
     * @throws InvalidArgumentException when the section is unknown or malformed
     */
    public function validate(string $section, mixed $data): void
    {
        if (array_key_exists($section, self::LIST_SECTIONS)) {
            $this->validateListSection($section, $data, self::LIST_SECTIONS[$section]);

            return;
        }

        match ($section) {
            'personal' => $this->validatePersonal($data),
            'skills' => $this->validateSkills($data),
            default => throw new InvalidArgumentException(
                "Unknown resume section \"{$section}\". Must be one of: personal, skills, experience, education, projects."
            ),
        };
    }

    /**
     * @param  array<int, string>  $requiredFields
     */
    private function validateListSection(string $section, mixed $data, array $requiredFields): void
    {
        if (! is_array($data) || ! array_is_list($data)) {
            throw new InvalidArgumentException(
                "{$section} must be a list of entries — send the full replacement array for the section, "
                ."not a single entry. Example: [{\"{$requiredFields[0]}\": \"...\"}, ...]."
            );
        }

        if ($data === []) {
            throw new InvalidArgumentException("{$section} must not be empty — the resume needs at least one entry.");
        }

        foreach ($data as $index => $entry) {
            if (! is_array($entry) || array_is_list($entry)) {
                throw new InvalidArgumentException("{$section}[{$index}] must be an object describing one entry.");
            }

            foreach ($requiredFields as $field) {
                if (blank($entry[$field] ?? null)) {
                    throw new InvalidArgumentException("{$section}[{$index}].{$field} is required and must not be empty.");
                }
            }
        }
    }

    private function validatePersonal(mixed $data): void
    {
        $this->assertObject('personal', $data);

        foreach (['name', 'title', 'email'] as $field) {
            if (blank($data[$field] ?? null)) {
                throw new InvalidArgumentException("personal.{$field} is required and must not be empty.");
            }
        }
    }

    private function validateSkills(mixed $data): void
    {
        $this->assertObject('skills', $data);

        if (! isset($data['top']) || ! is_array($data['top'])) {
            throw new InvalidArgumentException('skills.top is required and must be a list of {"title", "list"} groups.');
        }

        foreach (['top', 'other'] as $group) {
            if (! isset($data[$group])) {
                continue;
            }

            if (! is_array($data[$group]) || ! array_is_list($data[$group])) {
                throw new InvalidArgumentException("skills.{$group} must be a list of {\"title\", \"list\"} groups.");
            }
        }
    }

    private function assertObject(string $section, mixed $data): void
    {
        if (! is_array($data) || ($data !== [] && array_is_list($data))) {
            throw new InvalidArgumentException("{$section} must be an object of fields, not a list.");
        }
    }
}
