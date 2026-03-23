<?php

namespace App\Services;

use App\Contracts\ResumeDataServiceContract;
use App\Enums\ResumeSkillGroup;
use App\Models\ResumePersonalInfo;
use App\Models\ResumeVersion;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DatabaseResumeDataService implements ResumeDataServiceContract
{
    /**
     * Get all editable data for the editor.
     */
    public function getAllEditableData(): array
    {
        $version = $this->getCurrentVersion();

        if (!$version) {
            return [
                'personal' => [],
                'skills' => ['top' => [], 'other' => []],
                'experience' => [],
                'education' => [],
                'projects' => [],
            ];
        }

        $version->load([
            'personalInfo',
            'skillCategories.skills',
            'experiences.bullets',
            'educations',
            'projects.bullets',
        ]);

        return [
            'personal' => $this->transformPersonalInfo($version->personalInfo),
            'skills' => $this->transformSkills($version->skillCategories),
            'experience' => $this->transformExperiences($version->experiences, includeSalary: true),
            'education' => $this->transformEducations($version->educations, forEditor: true),
            'projects' => $this->transformProjects($version->projects),
        ];
    }

    /**
     * Save all editable data from the editor.
     *
     * @throws ValidationException
     */
    public function saveAllEditableData(array $data): void
    {
        $this->validatePersonalInfo($data['personal'] ?? []);
        $this->validateTechnicalSkills($data['skills'] ?? []);
        $this->validateExperience($data['experience'] ?? []);
        $this->validateEducation($data['education'] ?? []);
        $this->validateProjects($data['projects'] ?? []);

        $version = $this->getCurrentVersion();

        if (!$version) {
            throw ValidationException::withMessages([
                'version' => 'No current version found. Set a version first.',
            ]);
        }

        DB::transaction(function () use ($version, $data) {
            // Delete existing child records (cascades handle grandchildren)
            $version->personalInfo?->delete();
            $version->skillCategories()->delete();
            $version->experiences()->delete();
            $version->educations()->delete();
            $version->projects()->delete();

            // Re-create personal info
            $version->personalInfo()->create([
                'name' => $data['personal']['name'],
                'title' => $data['personal']['title'],
                'email' => $data['personal']['email'],
                'phone' => $data['personal']['phone'] ?? null,
                'linkedin' => $data['personal']['linkedin'] ?? null,
                'summary' => $data['personal']['summary'] ?? null,
            ]);

            // Re-create skills
            foreach (['top', 'other'] as $group) {
                $categories = $data['skills'][$group] ?? [];
                foreach ($categories as $catIndex => $category) {
                    $skillCategory = $version->skillCategories()->create([
                        'group' => $group,
                        'title' => $category['title'],
                        'sort_order' => $catIndex,
                    ]);

                    $skillList = $category['list'] ?? [];
                    foreach ($skillList as $skillIndex => $skillName) {
                        $skillCategory->skills()->create([
                            'name' => $skillName,
                            'sort_order' => $skillIndex,
                        ]);
                    }
                }
            }

            // Re-create experiences
            foreach ($data['experience'] as $expIndex => $exp) {
                $dates = $exp['dates'] ?? [];
                $experience = $version->experiences()->create([
                    'job_title' => $exp['jobTitle'],
                    'company' => $exp['company'],
                    'location' => $exp['location'] ?? null,
                    'date_start' => $this->sanitizeDateValue($dates[0] ?? null),
                    'date_end' => $this->sanitizeDateValue(!empty($dates) ? end($dates) : null, allowPresent: true),
                    'salary_start_amount' => !empty($exp['salaryStart']['amount']) ? $exp['salaryStart']['amount'] : null,
                    'salary_start_period' => !empty($exp['salaryStart']['period']) ? $exp['salaryStart']['period'] : null,
                    'salary_end_amount' => !empty($exp['salaryEnd']['amount']) ? $exp['salaryEnd']['amount'] : null,
                    'salary_end_period' => !empty($exp['salaryEnd']['period']) ? $exp['salaryEnd']['period'] : null,
                    'is_freelance' => (bool) ($exp['isFreelance'] ?? false),
                    'sort_order' => $expIndex,
                ]);

                foreach ($exp['bullets'] ?? [] as $bulletIndex => $bullet) {
                    $experience->bullets()->create([
                        'content' => $bullet,
                        'sort_order' => $bulletIndex,
                    ]);
                }
            }

            // Re-create educations
            foreach ($data['education'] as $eduIndex => $edu) {
                $dates = $edu['dates'] ?? [];
                $version->educations()->create([
                    'institution' => $edu['institution'],
                    'degree' => $edu['degree'] ?? null,
                    'date_start' => $this->sanitizeDateValue($dates[0] ?? null),
                    'date_end' => $this->sanitizeDateValue(!empty($dates) ? end($dates) : null, allowPresent: true),
                    'description' => $edu['description'] ?? null,
                    'sort_order' => $eduIndex,
                ]);
            }

            // Re-create projects
            foreach ($data['projects'] as $projIndex => $proj) {
                $project = $version->projects()->create([
                    'project_name' => $proj['projectName'],
                    'description' => $proj['description'] ?? null,
                    'sort_order' => $projIndex,
                ]);

                foreach ($proj['bullets'] ?? [] as $bulletIndex => $bullet) {
                    $project->bullets()->create([
                        'content' => $bullet,
                        'sort_order' => $bulletIndex,
                    ]);
                }
            }
        });
    }

    /**
     * Get data for HTML/JSON/text display (excludes education).
     */
    public function getDisplayData(): array
    {
        $version = $this->getCurrentVersion();

        if (!$version) {
            return [
                'personal' => [],
                'skills' => ['top' => [], 'other' => []],
                'experience' => [],
                'projects' => [],
            ];
        }

        $version->load([
            'personalInfo',
            'skillCategories.skills',
            'experiences.bullets',
            'projects.bullets',
        ]);

        return [
            'personal' => $this->transformPersonalInfo($version->personalInfo),
            'skills' => $this->transformSkills($version->skillCategories),
            'experience' => $this->transformExperiences($version->experiences),
            'projects' => $this->transformProjects($version->projects),
        ];
    }

    /**
     * Get all data for DOCX generation (includes education), flattened for docxtemplater.
     */
    public function getDocxData(): array
    {
        $data = $this->getDisplayData();

        $version = $this->getCurrentVersion();
        if ($version) {
            $version->load('educations');
            $data['education'] = $this->transformEducations($version->educations);
        } else {
            $data['education'] = [];
        }

        return $this->flattenForDocxtemplater($data);
    }

    /**
     * Get the current ResumeVersion model.
     */
    protected function getCurrentVersion(): ?ResumeVersion
    {
        return ResumeVersion::current()->first();
    }

    /**
     * Transform personal info model to array.
     */
    protected function transformPersonalInfo(?ResumePersonalInfo $info): array
    {
        if (!$info) {
            return [];
        }

        return array_filter([
            'name' => $info->name,
            'title' => $info->title,
            'email' => $info->email,
            'phone' => $info->phone,
            'linkedin' => $info->linkedin,
            'summary' => $info->summary,
        ], fn ($value) => $value !== null);
    }

    /**
     * Transform skill categories collection to the expected array format.
     *
     * @return array{top: array, other: array}
     */
    protected function transformSkills($categories): array
    {
        $result = ['top' => [], 'other' => []];

        foreach ($categories as $category) {
            $group = $category->group instanceof ResumeSkillGroup
                ? $category->group->value
                : $category->group;

            $result[$group][] = [
                'title' => $category->title,
                'list' => $category->skills->pluck('name')->toArray(),
            ];
        }

        return $result;
    }

    /**
     * Format a date value for public display (always show year only).
     *
     * "2024-03-15" → "2024", "2024" → "2024", "Present" → "Present"
     */
    private function formatDateForDisplay(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if (strcasecmp($value, 'present') === 0) {
            return 'Present';
        }

        // Extract just the year from YYYY or YYYY-MM-DD
        return substr($value, 0, 4);
    }

    /**
     * Validate a date value is either year-only, full date (YYYY-MM-DD), "Present", or empty.
     */
    private function sanitizeDateValue(?string $value, bool $allowPresent = false): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($allowPresent && strcasecmp($value, 'present') === 0) {
            return 'Present';
        }

        if (preg_match('/^\d{4}$/', $value)) {
            return $value;
        }

        if (preg_match('/^\d{4}-(0[1-9]|1[0-2])-(0[1-9]|[12]\d|3[01])$/', $value)) {
            return $value;
        }

        return null;
    }

    /**
     * Transform experiences collection to the expected array format.
     */
    protected function transformExperiences($experiences, bool $includeSalary = false): array
    {
        return $experiences->map(function ($exp) use ($includeSalary) {
            $dates = $includeSalary
                ? [$exp->date_start ?? '', $exp->date_end ?? '']
                : array_values(array_filter([
                    $this->formatDateForDisplay($exp->date_start),
                    $this->formatDateForDisplay($exp->date_end),
                ]));

            $result = [
                'jobTitle' => $exp->job_title,
                'company' => $exp->company,
                'location' => $exp->location,
                'dates' => array_values($dates),
                'bullets' => $exp->bullets->pluck('content')->toArray(),
            ];

            if ($includeSalary) {
                $result['salaryStart'] = $exp->salary_start_amount ? [
                    'amount' => (float) $exp->salary_start_amount,
                    'period' => $exp->salary_start_period?->value,
                ] : null;
                $result['salaryEnd'] = $exp->salary_end_amount ? [
                    'amount' => (float) $exp->salary_end_amount,
                    'period' => $exp->salary_end_period?->value,
                ] : null;
                $result['isFreelance'] = $exp->is_freelance;
            }

            return $result;
        })->toArray();
    }

    /**
     * Transform educations collection to the expected array format.
     */
    protected function transformEducations($educations, bool $forEditor = false): array
    {
        return $educations->map(function ($edu) use ($forEditor) {
            $dates = $forEditor
                ? [$edu->date_start ?? '', $edu->date_end ?? '']
                : array_values(array_filter([
                    $this->formatDateForDisplay($edu->date_start),
                    $this->formatDateForDisplay($edu->date_end),
                ]));

            return array_filter([
                'institution' => $edu->institution,
                'degree' => $edu->degree,
                'dates' => array_values($dates),
                'description' => $edu->description,
            ], fn ($value) => $value !== null && $value !== []);
        })->toArray();
    }

    /**
     * Transform projects collection to the expected array format.
     */
    protected function transformProjects($projects): array
    {
        return $projects->map(function ($proj) {
            return array_filter([
                'projectName' => $proj->project_name,
                'description' => $proj->description,
                'bullets' => $proj->bullets->pluck('content')->toArray(),
            ], fn ($value) => $value !== null);
        })->toArray();
    }

    /**
     * Flatten nested data for docxtemplater consumption.
     */
    protected function flattenForDocxtemplater(array $data): array
    {
        $flat = $data['personal'];

        $buildDateDisplay = function (array $dates, string $separator = ' • '): string {
            $start = $dates[0] ?? '';
            $end = $dates[1] ?? '';

            if ($start === '' && $end === '') {
                return '';
            }

            $range = $start;
            if ($end !== '') {
                $range .= " \u{2013} " . $end;
            }

            return $separator . $range;
        };

        $flat['experience'] = array_map(function ($job) use ($buildDateDisplay) {
            $dates = $job['dates'] ?? [];
            $job['dateStart'] = $dates[0] ?? '';
            $job['dateEnd'] = $dates[1] ?? '';
            $job['dateRange'] = count($dates) > 0 ? implode(' - ', $dates) : '';
            $job['dateDisplay'] = $buildDateDisplay($dates) . (count($dates) > 0 ? ' • ' : '');
            return $job;
        }, $data['experience']);

        $flat['education'] = array_map(function ($edu) use ($buildDateDisplay) {
            $dates = $edu['dates'] ?? [];
            $edu['dateStart'] = $dates[0] ?? '';
            $edu['dateEnd'] = $dates[1] ?? '';
            $edu['dateRange'] = count($dates) > 0 ? implode(' - ', $dates) : '';
            $edu['dateDisplay'] = $buildDateDisplay($dates);
            return $edu;
        }, $data['education']);

        $flat['skills'] = $this->processSkillCategories($data['skills'], ', ');
        $flat['projects'] = $data['projects'];

        return $flat;
    }

    /**
     * Process skill categories to add listJoined field.
     */
    protected function processSkillCategories(array $skills, string $delimiter): array
    {
        $result = [];
        foreach ($skills as $key => $categories) {
            if (is_array($categories)) {
                $result[$key] = array_map(function ($category) use ($delimiter) {
                    if (isset($category['list']) && is_array($category['list'])) {
                        $category['listJoined'] = implode($delimiter, $category['list']);
                    }
                    return $category;
                }, $categories);
            } else {
                $result[$key] = $categories;
            }
        }
        return $result;
    }

    /**
     * Validate personal information.
     */
    protected function validatePersonalInfo(array $data): void
    {
        $required = ['name', 'title', 'email'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw ValidationException::withMessages([
                    "personal.{$field}" => "The {$field} field is required.",
                ]);
            }
        }
    }

    /**
     * Validate technical skills.
     */
    protected function validateTechnicalSkills(array $data): void
    {
        if (!isset($data['top']) || !is_array($data['top'])) {
            throw ValidationException::withMessages([
                'skills.top' => 'Top skills section is required.',
            ]);
        }
    }

    /**
     * Validate experience.
     */
    protected function validateExperience(array $data): void
    {
        if (!is_array($data) || count($data) === 0) {
            throw ValidationException::withMessages([
                'experience' => 'At least one job experience is required.',
            ]);
        }

        foreach ($data as $index => $job) {
            if (empty($job['jobTitle'])) {
                throw ValidationException::withMessages([
                    "experience.{$index}.jobTitle" => 'Job title is required.',
                ]);
            }
            if (empty($job['company'])) {
                throw ValidationException::withMessages([
                    "experience.{$index}.company" => 'Company is required.',
                ]);
            }
        }
    }

    /**
     * Validate education.
     */
    protected function validateEducation(array $data): void
    {
        if (!is_array($data) || count($data) === 0) {
            throw ValidationException::withMessages([
                'education' => 'At least one education entry is required.',
            ]);
        }

        foreach ($data as $index => $edu) {
            if (empty($edu['institution'])) {
                throw ValidationException::withMessages([
                    "education.{$index}.institution" => 'Institution is required.',
                ]);
            }
        }
    }

    /**
     * Validate projects.
     */
    protected function validateProjects(array $data): void
    {
        if (!is_array($data) || count($data) === 0) {
            throw ValidationException::withMessages([
                'projects' => 'At least one project is required.',
            ]);
        }

        foreach ($data as $index => $project) {
            if (empty($project['projectName'])) {
                throw ValidationException::withMessages([
                    "projects.{$index}.projectName" => 'Project name is required.',
                ]);
            }
        }
    }
}
