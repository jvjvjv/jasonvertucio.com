<?php

namespace Tests\Feature;

use App\Models\ResumeEducation;
use App\Models\ResumeExperience;
use App\Models\ResumeExperienceBullet;
use App\Models\ResumePersonalInfo;
use App\Models\ResumeProject;
use App\Models\ResumeProjectBullet;
use App\Models\ResumeSkill;
use App\Models\ResumeSkillCategory;
use App\Models\ResumeVersion;
use App\Services\DatabaseResumeDataService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DatabaseResumeDataServiceTest extends TestCase
{
    use DatabaseTransactions;

    protected DatabaseResumeDataService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new DatabaseResumeDataService();
    }

    public function test_get_all_editable_data_returns_empty_when_no_version(): void
    {
        $data = $this->service->getAllEditableData();

        $this->assertSame([], $data['personal']);
        $this->assertSame(['top' => [], 'other' => []], $data['skills']);
        $this->assertSame([], $data['experience']);
        $this->assertSame([], $data['education']);
        $this->assertSame([], $data['projects']);
    }

    public function test_get_all_editable_data_returns_correct_structure(): void
    {
        $version = ResumeVersion::factory()->create(['is_current' => true]);
        ResumePersonalInfo::factory()->create([
            'version_id' => $version->id,
            'name' => 'Jason Vertucio',
            'title' => 'Developer',
            'email' => 'jason@example.com',
            'phone' => '555-1234',
            'linkedin' => 'https://linkedin.com/in/jasonvertucio',
        ]);

        $topCategory = ResumeSkillCategory::factory()->create([
            'version_id' => $version->id,
            'group' => 'top',
            'title' => 'Languages',
            'sort_order' => 0,
        ]);
        ResumeSkill::factory()->create(['category_id' => $topCategory->id, 'name' => 'PHP', 'sort_order' => 0]);
        ResumeSkill::factory()->create(['category_id' => $topCategory->id, 'name' => 'JavaScript', 'sort_order' => 1]);

        $exp = ResumeExperience::factory()->create([
            'version_id' => $version->id,
            'job_title' => 'Senior Dev',
            'company' => 'Acme Corp',
            'location' => 'New York, NY',
            'date_start' => '2020',
            'date_end' => 'Present',
            'sort_order' => 0,
        ]);
        ResumeExperienceBullet::factory()->create(['experience_id' => $exp->id, 'content' => 'Built things', 'sort_order' => 0]);

        ResumeEducation::factory()->create([
            'version_id' => $version->id,
            'institution' => 'MIT',
            'degree' => 'BS Computer Science',
            'date_start' => '2014',
            'date_end' => '2018',
            'sort_order' => 0,
        ]);

        $proj = ResumeProject::factory()->create([
            'version_id' => $version->id,
            'project_name' => 'Cool Project',
            'description' => 'A cool project',
            'sort_order' => 0,
        ]);
        ResumeProjectBullet::factory()->create(['project_id' => $proj->id, 'content' => 'Used React', 'sort_order' => 0]);

        $data = $this->service->getAllEditableData();

        // Personal
        $this->assertEquals('Jason Vertucio', $data['personal']['name']);
        $this->assertEquals('Developer', $data['personal']['title']);
        $this->assertEquals('jason@example.com', $data['personal']['email']);

        // Skills
        $this->assertCount(1, $data['skills']['top']);
        $this->assertEquals('Languages', $data['skills']['top'][0]['title']);
        $this->assertEquals(['PHP', 'JavaScript'], $data['skills']['top'][0]['list']);
        $this->assertSame([], $data['skills']['other']);

        // Experience
        $this->assertCount(1, $data['experience']);
        $this->assertEquals('Senior Dev', $data['experience'][0]['jobTitle']);
        $this->assertEquals('Acme Corp', $data['experience'][0]['company']);
        $this->assertEquals(['2020', 'Present'], $data['experience'][0]['dates']);
        $this->assertEquals(['Built things'], $data['experience'][0]['bullets']);

        // Education
        $this->assertCount(1, $data['education']);
        $this->assertEquals('MIT', $data['education'][0]['institution']);
        $this->assertEquals('BS Computer Science', $data['education'][0]['degree']);

        // Projects
        $this->assertCount(1, $data['projects']);
        $this->assertEquals('Cool Project', $data['projects'][0]['projectName']);
        $this->assertEquals(['Used React'], $data['projects'][0]['bullets']);
    }

    public function test_get_display_data_excludes_education(): void
    {
        $version = ResumeVersion::factory()->create(['is_current' => true]);
        ResumePersonalInfo::factory()->create(['version_id' => $version->id]);
        ResumeEducation::factory()->create(['version_id' => $version->id]);

        $data = $this->service->getDisplayData();

        $this->assertArrayNotHasKey('education', $data);
        $this->assertArrayHasKey('personal', $data);
        $this->assertArrayHasKey('skills', $data);
        $this->assertArrayHasKey('experience', $data);
        $this->assertArrayHasKey('projects', $data);
    }

    public function test_get_docx_data_includes_education_and_flattened(): void
    {
        $version = ResumeVersion::factory()->create(['is_current' => true]);
        ResumePersonalInfo::factory()->create([
            'version_id' => $version->id,
            'name' => 'Test Person',
            'title' => 'Developer',
            'email' => 'test@example.com',
        ]);

        ResumeExperience::factory()->create([
            'version_id' => $version->id,
            'job_title' => 'Dev',
            'company' => 'Company',
            'date_start' => '2020',
            'date_end' => '2023',
            'sort_order' => 0,
        ]);

        ResumeEducation::factory()->create([
            'version_id' => $version->id,
            'institution' => 'University',
            'degree' => 'BS',
            'date_start' => '2014',
            'date_end' => '2018',
            'sort_order' => 0,
        ]);

        $data = $this->service->getDocxData();

        // Flattened: personal info fields at top level
        $this->assertEquals('Test Person', $data['name']);
        $this->assertEquals('test@example.com', $data['email']);

        // Education included
        $this->assertArrayHasKey('education', $data);
        $this->assertCount(1, $data['education']);

        // Experience has date fields added
        $this->assertEquals('2020', $data['experience'][0]['dateStart']);
        $this->assertEquals('2023', $data['experience'][0]['dateEnd']);
    }

    public function test_save_all_editable_data_creates_records(): void
    {
        $version = ResumeVersion::factory()->create(['is_current' => true]);

        $inputData = [
            'personal' => [
                'name' => 'Jane Doe',
                'title' => 'Engineer',
                'email' => 'jane@example.com',
                'phone' => '555-9876',
            ],
            'skills' => [
                'top' => [
                    ['title' => 'Programming', 'list' => ['PHP', 'Python']],
                ],
                'other' => [
                    ['title' => 'Tools', 'list' => ['Git']],
                ],
            ],
            'experience' => [
                [
                    'jobTitle' => 'Lead Developer',
                    'company' => 'Tech Inc',
                    'location' => 'Remote',
                    'dates' => ['2021', 'Present'],
                    'bullets' => ['Led a team', 'Built APIs'],
                ],
            ],
            'education' => [
                [
                    'institution' => 'State University',
                    'degree' => 'MS CS',
                    'dates' => ['2016', '2018'],
                ],
            ],
            'projects' => [
                [
                    'projectName' => 'My App',
                    'description' => 'An app I made',
                    'bullets' => ['Feature A', 'Feature B'],
                ],
            ],
        ];

        $this->service->saveAllEditableData($inputData);

        // Verify personal info saved
        $info = $version->fresh()->personalInfo;
        $this->assertEquals('Jane Doe', $info->name);
        $this->assertEquals('jane@example.com', $info->email);

        // Verify skills saved
        $this->assertEquals(2, $version->skillCategories()->count());
        $topCat = $version->skillCategories()->where('group', 'top')->first();
        $this->assertEquals('Programming', $topCat->title);
        $this->assertCount(2, $topCat->skills);

        // Verify experience saved
        $this->assertEquals(1, $version->experiences()->count());
        $savedExp = $version->experiences()->first();
        $this->assertEquals('Lead Developer', $savedExp->job_title);
        $this->assertCount(2, $savedExp->bullets);

        // Verify education saved
        $this->assertEquals(1, $version->educations()->count());
        $this->assertEquals('State University', $version->educations()->first()->institution);

        // Verify projects saved
        $this->assertEquals(1, $version->projects()->count());
        $savedProj = $version->projects()->first();
        $this->assertEquals('My App', $savedProj->project_name);
        $this->assertCount(2, $savedProj->bullets);
    }

    public function test_save_all_editable_data_replaces_existing_records(): void
    {
        $version = ResumeVersion::factory()->create(['is_current' => true]);
        ResumePersonalInfo::factory()->create(['version_id' => $version->id, 'name' => 'Old Name']);
        ResumeExperience::factory()->create(['version_id' => $version->id]);
        ResumeEducation::factory()->create(['version_id' => $version->id]);
        ResumeProject::factory()->create(['version_id' => $version->id]);

        $inputData = [
            'personal' => [
                'name' => 'New Name',
                'title' => 'New Title',
                'email' => 'new@example.com',
            ],
            'skills' => [
                'top' => [
                    ['title' => 'Frameworks', 'list' => ['Laravel']],
                ],
            ],
            'experience' => [
                [
                    'jobTitle' => 'New Job',
                    'company' => 'New Company',
                    'dates' => ['2023'],
                    'bullets' => [],
                ],
            ],
            'education' => [
                [
                    'institution' => 'New School',
                ],
            ],
            'projects' => [
                [
                    'projectName' => 'New Project',
                    'bullets' => [],
                ],
            ],
        ];

        $this->service->saveAllEditableData($inputData);

        $version->refresh();
        $this->assertEquals('New Name', $version->personalInfo->name);
        $this->assertEquals(1, $version->experiences()->count());
        $this->assertEquals('New Job', $version->experiences()->first()->job_title);
    }

    public function test_save_throws_validation_when_no_current_version(): void
    {
        $this->expectException(ValidationException::class);

        $this->service->saveAllEditableData([
            'personal' => ['name' => 'Test', 'title' => 'Dev', 'email' => 'test@test.com'],
            'skills' => ['top' => []],
            'experience' => [['jobTitle' => 'Dev', 'company' => 'Co']],
            'education' => [['institution' => 'School']],
            'projects' => [['projectName' => 'Proj']],
        ]);
    }

    public function test_save_validates_required_personal_fields(): void
    {
        ResumeVersion::factory()->create(['is_current' => true]);

        $this->expectException(ValidationException::class);

        $this->service->saveAllEditableData([
            'personal' => ['name' => '', 'title' => 'Dev', 'email' => 'test@test.com'],
            'skills' => ['top' => []],
            'experience' => [['jobTitle' => 'Dev', 'company' => 'Co']],
            'education' => [['institution' => 'School']],
            'projects' => [['projectName' => 'Proj']],
        ]);
    }

    public function test_save_validates_experience_required_fields(): void
    {
        ResumeVersion::factory()->create(['is_current' => true]);

        $this->expectException(ValidationException::class);

        $this->service->saveAllEditableData([
            'personal' => ['name' => 'Test', 'title' => 'Dev', 'email' => 'test@test.com'],
            'skills' => ['top' => []],
            'experience' => [['jobTitle' => '', 'company' => 'Co']],
            'education' => [['institution' => 'School']],
            'projects' => [['projectName' => 'Proj']],
        ]);
    }

    public function test_save_validates_at_least_one_experience(): void
    {
        ResumeVersion::factory()->create(['is_current' => true]);

        $this->expectException(ValidationException::class);

        $this->service->saveAllEditableData([
            'personal' => ['name' => 'Test', 'title' => 'Dev', 'email' => 'test@test.com'],
            'skills' => ['top' => []],
            'experience' => [],
            'education' => [['institution' => 'School']],
            'projects' => [['projectName' => 'Proj']],
        ]);
    }

    public function test_save_validates_top_skills_required(): void
    {
        ResumeVersion::factory()->create(['is_current' => true]);

        $this->expectException(ValidationException::class);

        $this->service->saveAllEditableData([
            'personal' => ['name' => 'Test', 'title' => 'Dev', 'email' => 'test@test.com'],
            'skills' => [],
            'experience' => [['jobTitle' => 'Dev', 'company' => 'Co']],
            'education' => [['institution' => 'School']],
            'projects' => [['projectName' => 'Proj']],
        ]);
    }

    public function test_roundtrip_save_and_get_preserves_data(): void
    {
        ResumeVersion::factory()->create(['is_current' => true]);

        $inputData = [
            'personal' => [
                'name' => 'Roundtrip Test',
                'title' => 'Full Stack Dev',
                'email' => 'round@trip.com',
                'phone' => '555-0000',
                'linkedin' => 'https://linkedin.com/in/test',
                'summary' => 'A developer summary',
            ],
            'skills' => [
                'top' => [
                    ['title' => 'Languages', 'list' => ['PHP', 'JavaScript', 'TypeScript']],
                    ['title' => 'Frameworks', 'list' => ['Laravel', 'Vue.js']],
                ],
                'other' => [
                    ['title' => 'DevOps', 'list' => ['Docker', 'AWS']],
                ],
            ],
            'experience' => [
                [
                    'jobTitle' => 'Senior Developer',
                    'company' => 'First Company',
                    'location' => 'NYC',
                    'dates' => ['2020', 'Present'],
                    'bullets' => ['Did great things', 'Led team'],
                ],
                [
                    'jobTitle' => 'Junior Developer',
                    'company' => 'Second Company',
                    'location' => 'LA',
                    'dates' => ['2018', '2020'],
                    'bullets' => ['Learned a lot'],
                ],
            ],
            'education' => [
                [
                    'institution' => 'Big University',
                    'degree' => 'BS Computer Science',
                    'dates' => ['2014', '2018'],
                    'description' => 'Graduated with honors',
                ],
            ],
            'projects' => [
                [
                    'projectName' => 'Open Source Lib',
                    'description' => 'A library for things',
                    'bullets' => ['Feature 1', 'Feature 2', 'Feature 3'],
                ],
            ],
        ];

        $this->service->saveAllEditableData($inputData);
        $outputData = $this->service->getAllEditableData();

        // Personal
        $this->assertEquals($inputData['personal']['name'], $outputData['personal']['name']);
        $this->assertEquals($inputData['personal']['email'], $outputData['personal']['email']);
        $this->assertEquals($inputData['personal']['summary'], $outputData['personal']['summary']);

        // Skills
        $this->assertCount(2, $outputData['skills']['top']);
        $this->assertCount(1, $outputData['skills']['other']);
        $this->assertEquals(['PHP', 'JavaScript', 'TypeScript'], $outputData['skills']['top'][0]['list']);

        // Experience
        $this->assertCount(2, $outputData['experience']);
        $this->assertEquals('Senior Developer', $outputData['experience'][0]['jobTitle']);
        $this->assertEquals('Junior Developer', $outputData['experience'][1]['jobTitle']);
        $this->assertEquals(['Did great things', 'Led team'], $outputData['experience'][0]['bullets']);

        // Education
        $this->assertCount(1, $outputData['education']);
        $this->assertEquals('Big University', $outputData['education'][0]['institution']);

        // Projects
        $this->assertCount(1, $outputData['projects']);
        $this->assertCount(3, $outputData['projects'][0]['bullets']);
    }
}
