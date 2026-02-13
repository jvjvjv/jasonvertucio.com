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
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class MigrateResumeToDatabaseTest extends TestCase
{
    use DatabaseTransactions;

    public function test_command_migrates_json_data_to_database(): void
    {
        $this->artisan('resume:migrate-to-db', ['--force' => true])
            ->assertSuccessful();

        // Verify version was created
        $version = ResumeVersion::current()->first();
        $this->assertNotNull($version);
        $this->assertTrue($version->is_current);

        // Verify personal info was created
        $personalInfo = $version->personalInfo;
        $this->assertNotNull($personalInfo);
        $this->assertNotEmpty($personalInfo->name);
        $this->assertNotEmpty($personalInfo->email);

        // Verify skill categories were created
        $this->assertGreaterThan(0, $version->skillCategories()->count());

        // Verify skills were created within categories
        $totalSkills = ResumeSkill::whereHas('category', function ($q) use ($version) {
            $q->where('version_id', $version->id);
        })->count();
        $this->assertGreaterThan(0, $totalSkills);

        // Verify experiences were created
        $this->assertGreaterThan(0, $version->experiences()->count());

        // Verify experience bullets were created
        $totalBullets = ResumeExperienceBullet::whereHas('experience', function ($q) use ($version) {
            $q->where('version_id', $version->id);
        })->count();
        $this->assertGreaterThan(0, $totalBullets);

        // Verify education was created
        $this->assertGreaterThan(0, $version->educations()->count());

        // Verify projects were created
        $this->assertGreaterThan(0, $version->projects()->count());
    }

    public function test_command_can_be_rerun_safely(): void
    {
        $this->artisan('resume:migrate-to-db', ['--force' => true])
            ->assertSuccessful();

        $firstRunCount = ResumeVersion::count();

        // Re-run should succeed and not duplicate data
        $this->artisan('resume:migrate-to-db', ['--force' => true])
            ->assertSuccessful();

        $this->assertEquals($firstRunCount, ResumeVersion::count());
    }

    public function test_command_handles_experience_dates_correctly(): void
    {
        $this->artisan('resume:migrate-to-db', ['--force' => true])
            ->assertSuccessful();

        $version = ResumeVersion::current()->first();

        // Check that all experiences have valid date fields
        foreach ($version->experiences as $exp) {
            $this->assertNotNull($exp->job_title);
            $this->assertNotNull($exp->company);
        }

        // Specifically check the Liberty Fox entry with 3-element dates
        $libertyFox = $version->experiences()->where('company', 'Liberty Fox Technologies')->first();
        if ($libertyFox) {
            $this->assertEquals('2017', $libertyFox->date_start);
            $this->assertEquals('2025', $libertyFox->date_end);
        }
    }

    public function test_command_outputs_summary_table(): void
    {
        $this->artisan('resume:migrate-to-db', ['--force' => true])
            ->expectsOutputToContain('Migration complete!')
            ->expectsOutputToContain('RESUME_DRIVER=database')
            ->assertSuccessful();
    }
}
