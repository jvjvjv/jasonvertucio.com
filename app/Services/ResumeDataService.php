<?php

namespace App\Services;

use Log;
class ResumeDataService
{
    protected string $basePath;

    public function __construct()
    {
        $this->basePath = resource_path('resume');
    }

    /**
     * Get data for HTML/JSON/text display (excludes education and technical-profile)
     */
    public function getDisplayData(): array
    {
        return [
            'personal' => $this->loadJson('personal-information.json'),
            'skills' => $this->loadJson('technical-skills.json'),
            'experience' => $this->loadJson('experience.json'),
            'projects' => $this->loadJson('selected-projects.json'),
        ];
    }

    /**
     * Get all data for DOCX generation (includes education and technical-profile)
     */
    public function getDocxData(): array
    {
        $data = $this->getDisplayData();
        $data['education'] = $this->loadJson('education.json');
        $data['technicalProfile'] = $this->loadJson('technical-profile.json');

        return $this->flattenForDocxtemplater($data);
    }

    /**
     * Load a JSON file from the resume directory
     */
    protected function loadJson(string $filename): array
    {
        $path = $this->basePath . '/' . $filename;
        if (!file_exists($path)) {
            return [];
        }
        return json_decode(file_get_contents($path), true) ?? [];
    }

    /**
     * Flatten nested data for docxtemplater consumption
     * Personal info fields are promoted to top level for easy template access
     */
    protected function flattenForDocxtemplater(array $data): array
    {
        // Start with personal info at top level
        $flat = $data['personal'];

        // Process experience to flatten dates array
        $flat['experience'] = array_map(function ($job) {
            if (isset($job['dates']) && is_array($job['dates'])) {
                $job['dateStart'] = $job['dates'][0] ?? '';
                $job['dateEnd'] = $job['dates'][1] ?? '';
                $job['dateRange'] = implode(' - ', $job['dates']);
            }
            return $job;
        }, $data['experience']);

        // Process education to flatten dates array
        $flat['education'] = array_map(function ($edu) {
            if (isset($edu['dates']) && is_array($edu['dates'])) {
                $edu['dateStart'] = $edu['dates'][0] ?? '';
                $edu['dateEnd'] = $edu['dates'][1] ?? '';
                $edu['dateRange'] = implode(' - ', $edu['dates']);
            }
            return $edu;
        }, $data['education']);

        // Process technical skills - join list with comma
        $flat['skills'] = $this->processSkillCategories($data['skills'], ', ');

        $flat['projects'] = $data['projects'];

        // Process technical profile - join skills with middot
        $flat['technicalProfile'] = $this->processTechnicalProfile($data['technicalProfile']);

        return $flat;
    }

    /**
     * Process skill categories to add listJoined field
     */
    protected function processSkillCategories(array $skills, string $delimiter): array {
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
     * Process technical profile to add listJoined field with middot delimiter
     */
    protected function processTechnicalProfile(array $profile): array
    {
        $middot = ' · ';
        $result = [];

        // Process main - single object with category and skills
        if (isset($profile['main'])) {
            $main = $profile['main'];
            if (isset($main['skills']) && is_array($main['skills'])) {
                $skillNames = array_map(function ($s) {
                    return is_array($s) && isset($s['skill']) ? $s['skill'] : $s;
                }, $main['skills']);
                $main['listJoined'] = implode($middot, $skillNames);
            }
            $result['main'] = $main;
        }

        // Process secondary - array of category objects, pass through as-is
        if (isset($profile['secondary'])) {
            $result['secondary'] = $profile['secondary'];
        }

        return $result;
    }
}
