<?php

namespace App\Console\Commands;

use App\Models\ResumeEducation;
use App\Models\ResumeExperience;
use App\Models\ResumeExperienceBullet;
use App\Models\ResumePersonalInfo;
use App\Models\ResumeProject;
use App\Models\ResumeProjectBullet;
use App\Models\ResumeSkill;
use App\Models\ResumeSkillCategory;
use App\Models\ResumeVersion;
use App\Services\JsonResumeDataService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrateResumeToDatabase extends Command
{
    /**
     * @var string
     */
    protected $signature = 'resume:migrate-to-db {--force : Skip confirmation}';

    /**
     * @var string
     */
    protected $description = 'Migrate resume data from JSON files into the database';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (!$this->option('force') && !$this->confirm('This will import resume JSON data into the database. Continue?')) {
            $this->info('Cancelled.');
            return self::SUCCESS;
        }

        // Read version from JSON file
        $versionPath = config('resume.version_file');
        if (!file_exists($versionPath)) {
            $this->error('Version file not found at: ' . $versionPath);
            return self::FAILURE;
        }

        $versionContent = file_get_contents($versionPath);
        $versionString = json_decode($versionContent, true);
        if (is_string($versionString)) {
            $version = $versionString;
        } else {
            $version = trim($versionContent);
        }

        $this->info("Found version: {$version}");

        // Load all JSON data
        $dataService = new JsonResumeDataService();
        $data = $dataService->getAllEditableData();

        $counts = [
            'personal_info' => 0,
            'skill_categories' => 0,
            'skills' => 0,
            'experiences' => 0,
            'experience_bullets' => 0,
            'educations' => 0,
            'projects' => 0,
            'project_bullets' => 0,
        ];

        DB::transaction(function () use ($version, $data, &$counts) {
            // Create version record
            $versionModel = ResumeVersion::firstOrCreate(
                ['version' => $version],
            );
            $versionModel->update(['is_current' => true]);

            // Unset other current versions
            ResumeVersion::where('id', '!=', $versionModel->id)
                ->where('is_current', true)
                ->update(['is_current' => false]);

            // Clear existing data for this version to allow re-running
            $versionModel->personalInfo?->delete();
            $versionModel->skillCategories()->delete();
            $versionModel->experiences()->delete();
            $versionModel->educations()->delete();
            $versionModel->projects()->delete();

            // Personal info
            if (!empty($data['personal'])) {
                $versionModel->personalInfo()->create([
                    'name' => $data['personal']['name'] ?? '',
                    'title' => $data['personal']['title'] ?? '',
                    'email' => $data['personal']['email'] ?? '',
                    'phone' => $data['personal']['phone'] ?? null,
                    'linkedin' => $data['personal']['linkedin'] ?? null,
                    'summary' => $data['personal']['summary'] ?? null,
                ]);
                $counts['personal_info'] = 1;
            }

            // Skills
            foreach (['top', 'other'] as $group) {
                $categories = $data['skills'][$group] ?? [];
                foreach ($categories as $catIndex => $category) {
                    $skillCategory = $versionModel->skillCategories()->create([
                        'group' => $group,
                        'title' => $category['title'],
                        'sort_order' => $catIndex,
                    ]);
                    $counts['skill_categories']++;

                    $skillList = $category['list'] ?? [];
                    foreach ($skillList as $skillIndex => $skillName) {
                        $skillCategory->skills()->create([
                            'name' => $skillName,
                            'sort_order' => $skillIndex,
                        ]);
                        $counts['skills']++;
                    }
                }
            }

            // Experiences
            foreach ($data['experience'] as $expIndex => $exp) {
                $dates = $exp['dates'] ?? [];
                $dateStart = $dates[0] ?? null;
                $dateEnd = !empty($dates) ? end($dates) : null;

                $experience = $versionModel->experiences()->create([
                    'job_title' => $exp['jobTitle'],
                    'company' => $exp['company'],
                    'location' => $exp['location'] ?? null,
                    'date_start' => $dateStart,
                    'date_end' => $dateEnd,
                    'sort_order' => $expIndex,
                ]);
                $counts['experiences']++;

                foreach ($exp['bullets'] ?? [] as $bulletIndex => $bullet) {
                    $experience->bullets()->create([
                        'content' => $bullet,
                        'sort_order' => $bulletIndex,
                    ]);
                    $counts['experience_bullets']++;
                }
            }

            // Education
            foreach ($data['education'] as $eduIndex => $edu) {
                $dates = $edu['dates'] ?? [];
                $dateStart = $dates[0] ?? null;
                $dateEnd = !empty($dates) ? end($dates) : null;

                $versionModel->educations()->create([
                    'institution' => $edu['institution'],
                    'degree' => $edu['degree'] ?? null,
                    'date_start' => $dateStart,
                    'date_end' => $dateEnd,
                    'description' => $edu['description'] ?? null,
                    'sort_order' => $eduIndex,
                ]);
                $counts['educations']++;
            }

            // Projects
            foreach ($data['projects'] as $projIndex => $proj) {
                $project = $versionModel->projects()->create([
                    'project_name' => $proj['projectName'],
                    'description' => $proj['description'] ?? null,
                    'sort_order' => $projIndex,
                ]);
                $counts['projects']++;

                foreach ($proj['bullets'] ?? [] as $bulletIndex => $bullet) {
                    $project->bullets()->create([
                        'content' => $bullet,
                        'sort_order' => $bulletIndex,
                    ]);
                    $counts['project_bullets']++;
                }
            }

            // Backfill resume_downloads.version_id
            if (\Illuminate\Support\Facades\Schema::hasTable('resume_downloads')
                && \Illuminate\Support\Facades\Schema::hasColumn('resume_downloads', 'version_id')) {
                $downloadCount = DB::table('resume_downloads')
                    ->whereNull('version_id')
                    ->count();

                if ($downloadCount > 0) {
                    DB::table('resume_downloads')
                        ->whereNull('version_id')
                        ->update(['version_id' => $versionModel->id]);

                    $this->info("Backfilled {$downloadCount} download(s) with version_id.");
                }
            }
        });

        $this->info('Migration complete! Summary:');
        $this->table(
            ['Record Type', 'Count'],
            collect($counts)->map(fn ($count, $type) => [
                str_replace('_', ' ', ucfirst($type)),
                $count,
            ])->toArray()
        );

        $this->newLine();
        $this->info('To switch to database driver, set RESUME_DRIVER=database in your .env file.');

        return self::SUCCESS;
    }
}
