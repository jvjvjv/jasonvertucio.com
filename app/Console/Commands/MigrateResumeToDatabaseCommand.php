<?php

namespace App\Console\Commands;

use App\Contracts\ResumeDataServiceContract;
use App\Contracts\ResumeVersionServiceContract;
use Illuminate\Console\Command;

class MigrateResumeToDatabaseCommand extends Command
{
    protected $signature = 'resume:migrate-to-db {--force : Run migration without confirmation}';

    protected $description = 'Migrate resume content into database-backed tables';

    public function __construct(
        protected ResumeDataServiceContract $resumeDataService,
        protected ResumeVersionServiceContract $resumeVersionService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if (! $this->option('force') && ! $this->confirm('This will replace current resume data in the database. Continue?')) {
            $this->warn('Migration aborted.');

            return self::SUCCESS;
        }

        $payload = $this->buildPayload();
        $version = now()->format('Y').'.1.0';

        $this->resumeVersionService->setVersion($version);
        $this->resumeDataService->saveAllEditableData($payload);

        $this->table(['Section', 'Count'], [
            ['Skills (top categories)', count($payload['skills']['top'])],
            ['Skills (other categories)', count($payload['skills']['other'])],
            ['Experience', count($payload['experience'])],
            ['Education', count($payload['education'])],
            ['Projects', count($payload['projects'])],
        ]);

        $this->info('Migration complete!');
        $this->line('RESUME_DRIVER=database');

        return self::SUCCESS;
    }

    /**
     * @return array{personal: array, skills: array{top: array<int, array{title: string, list: array<int, string>}>, other: array<int, array{title: string, list: array<int, string>}>}, experience: array<int, array{jobTitle: string, company: string, location: string, dates: array<int, string>, bullets: array<int, string>}>, education: array<int, array{institution: string, degree: string, dates: array<int, string>, description: string}>, projects: array<int, array{projectName: string, description: string, bullets: array<int, string>}>}
     */
    protected function buildPayload(): array
    {
        $configPath = resource_path('config/config.json');
        $config = [];

        if (file_exists($configPath)) {
            $decoded = json_decode((string) file_get_contents($configPath), true);
            if (is_array($decoded)) {
                $config = $decoded;
            }
        }

        $sections = $config['about_me']['sections'] ?? [];
        $summary = is_array($sections) && count($sections) > 0
            ? $sections[0]
            : 'Senior software engineer.';

        $experience = [];
        foreach (($config['experience'] ?? []) as $job) {
            if (! is_array($job)) {
                continue;
            }

            $dateParts = $this->extractDateRange((string) ($job['date'] ?? ''));

            $experience[] = [
                'jobTitle' => 'Software Engineer',
                'company' => (string) ($job['company'] ?? 'Unknown Company'),
                'location' => (string) ($job['location'] ?? ''),
                'dates' => $dateParts,
                'bullets' => array_values(array_filter(
                    is_array($job['highlights'] ?? null) ? $job['highlights'] : [],
                    fn ($item): bool => is_string($item) && $item !== ''
                )),
            ];
        }

        if (count($experience) === 0) {
            $experience[] = [
                'jobTitle' => 'Software Engineer',
                'company' => 'Liberty Fox Technologies',
                'location' => 'Philadelphia, PA',
                'dates' => ['2017', '2025'],
                'bullets' => ['Maintained and shipped production applications.'],
            ];
        }

        return [
            'personal' => [
                'name' => 'Jason Vertucio',
                'title' => 'Web Applications Engineer',
                'email' => 'me@jasonvertucio.com',
                'phone' => '',
                'linkedin' => 'https://www.linkedin.com/in/jasonvertucio/',
                'summary' => $summary,
            ],
            'skills' => [
                'top' => [
                    [
                        'title' => 'Languages',
                        'list' => ['PHP', 'JavaScript', 'TypeScript', 'SQL'],
                    ],
                    [
                        'title' => 'Frameworks',
                        'list' => ['Laravel', 'Vue', 'React'],
                    ],
                ],
                'other' => [
                    [
                        'title' => 'Tooling',
                        'list' => ['Docker', 'Git', 'CI/CD'],
                    ],
                ],
            ],
            'experience' => $experience,
            'education' => [
                [
                    'institution' => 'Temple University',
                    'degree' => 'Information Science',
                    'dates' => ['2005', '2009'],
                    'description' => 'Undergraduate studies.',
                ],
            ],
            'projects' => [
                [
                    'projectName' => 'Ghost Letter',
                    'description' => 'Ephemeral image sharing web application.',
                    'bullets' => ['Built with Laravel', 'Mobile-first UX'],
                ],
                [
                    'projectName' => 'BalanceCloud',
                    'description' => 'Privacy-first ledger application.',
                    'bullets' => ['SPA architecture', 'API-driven backend'],
                ],
            ],
        ];
    }

    /**
     * @return array<int, string>
     */
    protected function extractDateRange(string $dateText): array
    {
        preg_match_all('/\b(\d{4})\b/', $dateText, $matches);

        $years = $matches[1] ?? [];
        if (count($years) === 0) {
            return [];
        }

        if (count($years) === 1) {
            return [$years[0]];
        }

        return [$years[0], end($years) ?: $years[0]];
    }
}
