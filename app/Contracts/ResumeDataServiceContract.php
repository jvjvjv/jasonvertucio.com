<?php

namespace App\Contracts;

use Illuminate\Validation\ValidationException;

interface ResumeDataServiceContract
{
    /**
     * Get all editable data for the editor.
     *
     * @return array{personal: array, skills: array, experience: array, education: array, projects: array}
     */
    public function getAllEditableData(): array;

    /**
     * Save all editable data from the editor.
     *
     * @param  array{personal: array, skills: array, experience: array, education: array, projects: array}  $data
     *
     * @throws ValidationException
     */
    public function saveAllEditableData(array $data): void;

    /**
     * Get data for HTML/JSON/text display (excludes education).
     *
     * @return array{personal: array, skills: array, experience: array, projects: array}
     */
    public function getDisplayData(): array;

    /**
     * Get education data formatted for display (year-only dates, no editor-only fields).
     *
     * @return array<int, array{institution: string, location?: string, degree?: string, level?: string, dates: array, description?: string}>
     */
    public function getEducationData(): array;

    /**
     * Get all data for DOCX generation (includes education), flattened for docxtemplater.
     */
    public function getDocxData(): array;
}
