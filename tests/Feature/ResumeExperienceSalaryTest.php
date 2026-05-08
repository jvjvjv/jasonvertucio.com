<?php

namespace Tests\Feature;

use App\Enums\SalaryPeriod;
use App\Models\ResumeExperience;
use App\Models\ResumeVersion;
use App\Services\DatabaseResumeDataService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ResumeExperienceSalaryTest extends TestCase
{
    use DatabaseTransactions;

    public function test_salary_fields_are_nullable_and_persist(): void
    {
        $version = ResumeVersion::factory()->create();

        $experience = ResumeExperience::factory()->create([
            'version_id' => $version->id,
            'salary_start_amount' => 75000.00,
            'salary_start_period' => SalaryPeriod::PerYear,
            'salary_end_amount' => 95000.50,
            'salary_end_period' => SalaryPeriod::PerYear,
            'is_freelance' => false,
        ]);

        $fresh = $experience->fresh();

        $this->assertEquals('75000.00', $fresh->salary_start_amount);
        $this->assertEquals(SalaryPeriod::PerYear, $fresh->salary_start_period);
        $this->assertEquals('95000.50', $fresh->salary_end_amount);
        $this->assertEquals(SalaryPeriod::PerYear, $fresh->salary_end_period);
        $this->assertFalse($fresh->is_freelance);
    }

    public function test_salary_fields_can_be_null(): void
    {
        $version = ResumeVersion::factory()->create();

        $experience = ResumeExperience::factory()->create([
            'version_id' => $version->id,
            'salary_start_amount' => null,
            'salary_start_period' => null,
            'salary_end_amount' => null,
            'salary_end_period' => null,
        ]);

        $fresh = $experience->fresh();

        $this->assertNull($fresh->salary_start_amount);
        $this->assertNull($fresh->salary_start_period);
        $this->assertNull($fresh->salary_end_amount);
        $this->assertNull($fresh->salary_end_period);
    }

    public function test_is_freelance_defaults_to_false(): void
    {
        $version = ResumeVersion::factory()->create();

        $experience = $version->experiences()->create([
            'job_title' => 'Developer',
            'company' => 'Acme',
            'sort_order' => 0,
        ]);

        $this->assertFalse($experience->fresh()->is_freelance);
    }

    public function test_freelance_factory_state(): void
    {
        $experience = ResumeExperience::factory()->freelance()->create();

        $this->assertTrue($experience->fresh()->is_freelance);
    }

    public function test_salary_period_enum_casts_correctly(): void
    {
        $version = ResumeVersion::factory()->create();

        foreach (SalaryPeriod::cases() as $period) {
            $experience = ResumeExperience::factory()->create([
                'version_id' => $version->id,
                'salary_start_period' => $period,
                'salary_end_period' => $period,
            ]);

            $fresh = $experience->fresh();
            $this->assertInstanceOf(SalaryPeriod::class, $fresh->salary_start_period);
            $this->assertInstanceOf(SalaryPeriod::class, $fresh->salary_end_period);
            $this->assertEquals($period, $fresh->salary_start_period);
        }
    }

    public function test_get_all_editable_data_includes_salary(): void
    {
        $version = ResumeVersion::factory()->create(['is_current' => true]);

        // Ensure no other version is current
        ResumeVersion::where('id', '!=', $version->id)->update(['is_current' => false]);

        $version->personalInfo()->create([
            'name' => 'Test',
            'title' => 'Dev',
            'email' => 'test@test.com',
        ]);

        $version->experiences()->create([
            'job_title' => 'Engineer',
            'company' => 'TestCo',
            'salary_start_amount' => 50.00,
            'salary_start_period' => SalaryPeriod::PerHour,
            'salary_end_amount' => 65.00,
            'salary_end_period' => SalaryPeriod::PerHour,
            'is_freelance' => true,
            'sort_order' => 0,
        ]);

        $service = app(DatabaseResumeDataService::class);
        $data = $service->getAllEditableData();

        $job = $data['experience'][0];
        $this->assertArrayHasKey('salaryStart', $job);
        $this->assertArrayHasKey('salaryEnd', $job);
        $this->assertArrayHasKey('isFreelance', $job);
        $this->assertEquals(50.00, $job['salaryStart']['amount']);
        $this->assertEquals('per_hour', $job['salaryStart']['period']);
        $this->assertEquals(65.00, $job['salaryEnd']['amount']);
        $this->assertTrue($job['isFreelance']);
    }

    public function test_get_display_data_excludes_salary(): void
    {
        $version = ResumeVersion::factory()->create(['is_current' => true]);

        ResumeVersion::where('id', '!=', $version->id)->update(['is_current' => false]);

        $version->personalInfo()->create([
            'name' => 'Test',
            'title' => 'Dev',
            'email' => 'test@test.com',
        ]);

        $version->experiences()->create([
            'job_title' => 'Engineer',
            'company' => 'TestCo',
            'salary_start_amount' => 80000,
            'salary_start_period' => SalaryPeriod::PerYear,
            'is_freelance' => false,
            'sort_order' => 0,
        ]);

        $service = app(DatabaseResumeDataService::class);
        $data = $service->getDisplayData();

        $job = $data['experience'][0];
        $this->assertArrayNotHasKey('salaryStart', $job);
        $this->assertArrayNotHasKey('salaryEnd', $job);
        $this->assertArrayNotHasKey('isFreelance', $job);
    }

    public function test_save_all_editable_data_persists_salary_fields(): void
    {
        $version = ResumeVersion::factory()->create(['is_current' => true]);

        ResumeVersion::where('id', '!=', $version->id)->update(['is_current' => false]);

        $version->personalInfo()->create([
            'name' => 'Test',
            'title' => 'Dev',
            'email' => 'test@test.com',
        ]);

        $service = app(DatabaseResumeDataService::class);

        $service->saveAllEditableData([
            'personal' => ['name' => 'Test', 'title' => 'Dev', 'email' => 'test@test.com'],
            'skills' => ['top' => [['title' => 'Languages', 'list' => ['PHP']]], 'other' => []],
            'experience' => [
                [
                    'jobTitle' => 'Senior Dev',
                    'company' => 'BigCorp',
                    'location' => 'Remote',
                    'dates' => ['2020', '2024'],
                    'bullets' => ['Did things'],
                    'salaryStart' => ['amount' => 120000, 'period' => 'per_year'],
                    'salaryEnd' => ['amount' => 150000, 'period' => 'per_year'],
                    'isFreelance' => false,
                ],
                [
                    'jobTitle' => 'Freelance Dev',
                    'company' => 'Self',
                    'dates' => ['2018', '2020'],
                    'bullets' => [],
                    'salaryStart' => ['amount' => 75, 'period' => 'per_hour'],
                    'salaryEnd' => ['amount' => null, 'period' => ''],
                    'isFreelance' => true,
                ],
            ],
            'education' => [['institution' => 'University', 'degree' => 'BS', 'dates' => ['2014', '2018']]],
            'projects' => [['projectName' => 'Project', 'bullets' => []]],
        ]);

        $experiences = $version->fresh()->experiences()->orderBy('sort_order')->get();

        $this->assertCount(2, $experiences);

        // First job: full salary data
        $this->assertEquals('120000.00', $experiences[0]->salary_start_amount);
        $this->assertEquals(SalaryPeriod::PerYear, $experiences[0]->salary_start_period);
        $this->assertEquals('150000.00', $experiences[0]->salary_end_amount);
        $this->assertFalse($experiences[0]->is_freelance);

        // Second job: freelance with partial salary
        $this->assertEquals('75.00', $experiences[1]->salary_start_amount);
        $this->assertEquals(SalaryPeriod::PerHour, $experiences[1]->salary_start_period);
        $this->assertNull($experiences[1]->salary_end_amount);
        $this->assertTrue($experiences[1]->is_freelance);
    }
}
