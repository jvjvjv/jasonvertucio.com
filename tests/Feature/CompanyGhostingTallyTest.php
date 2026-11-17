<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\Company;
use App\Models\RoleIntel;
use App\Services\ApplicationService;
use App\Services\CompanyGhostingTally;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

class CompanyGhostingTallyTest extends TestCase
{
    use DatabaseTransactions;

    private CompanyGhostingTally $tally;

    private string $acme;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('resume.ghosted_fit_penalty', 10);
        $this->tally = app(CompanyGhostingTally::class);
        $this->acme = 'Acme '.Str::random(8);
    }

    public function test_one_ghosting_counts_for_another_application_at_the_company_ignoring_case_and_spaces(): void
    {
        $this->ghosted($this->acme);
        $draft = Application::factory()->create(['company_name' => '  '.mb_strtoupper($this->acme).' ']);

        $this->assertSame(1, $this->tally->forApplication($draft));
    }

    public function test_applications_match_through_a_researched_company(): void
    {
        $company = Company::factory()->create(['name' => $this->acme]);
        $ghosted = $this->ghosted($this->acme.' Corp');
        RoleIntel::factory()->create(['application_id' => $ghosted->id, 'company_id' => $company->id]);

        $byName = Application::factory()->create(['company_name' => $this->acme]);
        $byCompany = Application::factory()->create(['company_name' => 'Something else '.Str::random(6)]);
        RoleIntel::factory()->create(['application_id' => $byCompany->id, 'company_id' => $company->id]);

        $this->assertSame([$byName->id => 1, $byCompany->id => 1], $this->tally->forApplications(collect([$byName, $byCompany])));
    }

    public function test_a_company_that_replies_drops_off_the_tally(): void
    {
        $ghosted = $this->ghosted($this->acme);
        $other = Application::factory()->create(['company_name' => $this->acme]);

        app(ApplicationService::class)->addStatusUpdate($ghosted->fresh(), ApplicationStatus::Rejected);

        $this->assertSame(0, $this->tally->forApplication($other));
    }

    public function test_a_deleted_ghosted_application_does_not_count(): void
    {
        $this->ghosted($this->acme)->delete();
        $other = Application::factory()->create(['company_name' => $this->acme]);

        $this->assertSame(0, $this->tally->forApplication($other));
    }

    public function test_an_application_does_not_count_toward_its_own_tally(): void
    {
        $ghosted = $this->ghosted($this->acme);

        $this->assertSame(0, $this->tally->forApplication($ghosted));
    }

    public function test_two_ghosted_applications_count_for_each_other(): void
    {
        $first = $this->ghosted($this->acme);
        $second = $this->ghosted($this->acme);
        $third = Application::factory()->create(['company_name' => $this->acme]);

        $this->assertSame([$first->id => 1, $second->id => 1, $third->id => 2], $this->tally->forApplications(collect([$first, $second, $third])));
    }

    public function test_different_companies_do_not_match(): void
    {
        $this->ghosted($this->acme);
        $globex = Application::factory()->create(['company_name' => 'Globex '.Str::random(8)]);

        $this->assertSame(0, $this->tally->forApplication($globex));
    }

    public function test_the_unknown_company_placeholder_never_matches_by_name(): void
    {
        $this->ghosted(Application::UNKNOWN_COMPANY);
        $draft = Application::factory()->create(['company_name' => Application::UNKNOWN_COMPANY]);

        $this->assertSame(0, $this->tally->forApplication($draft));
    }

    public function test_adjusted_fit_score_subtracts_the_penalty_per_ghosting_and_never_drops_below_one(): void
    {
        $this->assertSame(58, $this->tally->adjustedFitScore(78, 2));
        $this->assertSame(1, $this->tally->adjustedFitScore(15, 3));
        $this->assertSame(78, $this->tally->adjustedFitScore(78, 0));
        $this->assertNull($this->tally->adjustedFitScore(null, 1));
        $this->assertSame(['count' => 2, 'penalty' => 20, 'adjusted_fit_score' => 58], $this->tally->summary(78, 2));
    }

    public function test_the_penalty_is_configurable(): void
    {
        config()->set('resume.ghosted_fit_penalty', 25);

        $this->assertSame(55, $this->tally->adjustedFitScore(80, 1));
    }

    private function ghosted(string $company): Application
    {
        $application = Application::factory()->applied(now()->subDays(60))->create(['company_name' => $company]);
        app(ApplicationService::class)->markGhosted($application, 30);

        return $application->fresh();
    }
}
