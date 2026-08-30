<?php

namespace Tests\Unit\Services\Resume;

use App\Services\Resume\ResumeSectionValidator;
use InvalidArgumentException;
use Tests\TestCase;

class ResumeSectionValidatorTest extends TestCase
{
    private ResumeSectionValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->validator = new ResumeSectionValidator;
    }

    /**
     * The shape a persona actually sent that corrupted the live draft: one job
     * object where the section expects the full replacement list of jobs. The
     * resume view then iterates the job's own fields instead of its jobs.
     */
    public function test_it_rejects_a_single_experience_entry_sent_in_place_of_the_list(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('experience must be a list of entries');

        $this->validator->validate('experience', [
            'jobTitle' => 'Angular/React/Vue Developer',
            'company' => 'Subaru of America',
            'dates' => ['2025-06-02', '2026-06-02'],
            'bullets' => ['Did the thing.'],
        ]);
    }

    public function test_it_rejects_a_single_project_entry_sent_in_place_of_the_list(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('projects must be a list of entries');

        $this->validator->validate('projects', ['projectName' => 'jasonvertucio.com']);
    }

    public function test_it_rejects_a_single_education_entry_sent_in_place_of_the_list(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('education must be a list of entries');

        $this->validator->validate('education', ['institution' => 'Drexel University']);
    }

    public function test_it_rejects_an_experience_entry_missing_its_job_title(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('experience[1].jobTitle');

        $this->validator->validate('experience', [
            ['jobTitle' => 'Engineer', 'company' => 'Acme'],
            ['company' => 'Globex'],
        ]);
    }

    public function test_it_rejects_an_experience_entry_missing_its_company(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('experience[0].company');

        $this->validator->validate('experience', [['jobTitle' => 'Engineer']]);
    }

    public function test_it_rejects_a_project_entry_missing_its_name(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('projects[0].projectName');

        $this->validator->validate('projects', [['description' => 'No name.']]);
    }

    public function test_it_rejects_an_education_entry_missing_its_institution(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('education[0].institution');

        $this->validator->validate('education', [['degree' => 'Computer Science']]);
    }

    public function test_it_rejects_a_non_entry_inside_a_list_section(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('experience[0] must be an object');

        $this->validator->validate('experience', ['just a string']);
    }

    public function test_it_rejects_personal_missing_required_fields(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('personal.email');

        $this->validator->validate('personal', ['name' => 'Jason Vertucio', 'title' => 'Engineer']);
    }

    public function test_it_rejects_personal_sent_as_a_list(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('personal must be an object');

        $this->validator->validate('personal', [['name' => 'Jason Vertucio']]);
    }

    public function test_it_rejects_skills_without_a_top_list(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('skills.top');

        $this->validator->validate('skills', ['other' => []]);
    }

    public function test_it_accepts_a_well_formed_experience_list(): void
    {
        $this->validator->validate('experience', [
            ['jobTitle' => 'Engineer', 'company' => 'Acme', 'bullets' => []],
            ['jobTitle' => 'Developer', 'company' => 'Globex'],
        ]);

        $this->addToAssertionCount(1);
    }

    public function test_it_accepts_a_well_formed_personal_section(): void
    {
        $this->validator->validate('personal', [
            'name' => 'Jason Vertucio',
            'title' => 'Lead Front-End Engineer',
            'email' => 'jasonvertucio@pm.me',
            'phone' => '(267) 225-2696',
        ]);

        $this->addToAssertionCount(1);
    }

    public function test_it_accepts_a_well_formed_skills_section(): void
    {
        $this->validator->validate('skills', [
            'top' => [['title' => 'Front-End', 'list' => ['Vue', 'React']]],
            'other' => [],
        ]);

        $this->addToAssertionCount(1);
    }

    public function test_it_accepts_well_formed_education_and_projects(): void
    {
        $this->validator->validate('education', [['institution' => 'Drexel University']]);
        $this->validator->validate('projects', [['projectName' => 'jasonvertucio.com']]);

        $this->addToAssertionCount(2);
    }

    public function test_it_rejects_an_empty_list_section(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('experience must not be empty');

        $this->validator->validate('experience', []);
    }

    public function test_it_rejects_an_unknown_section(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown resume section');

        $this->validator->validate('hobbies', []);
    }
}
