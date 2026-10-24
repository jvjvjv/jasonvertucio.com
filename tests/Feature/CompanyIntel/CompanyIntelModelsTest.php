<?php

namespace Tests\Feature\CompanyIntel;

use App\Enums\RoleIntelStatus;
use App\Models\Application;
use App\Models\Company;
use App\Models\RoleIntel;
use App\Services\CompanyIntel\CompanyIntelService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Jvjvjv\CodeTalker\Models\AiSystemPrompt;
use Tests\TestCase;

class CompanyIntelModelsTest extends TestCase
{
    use DatabaseTransactions;

    public function test_a_role_intel_builds_with_its_application_and_company(): void
    {
        $intel = RoleIntel::factory()->create();

        $this->assertInstanceOf(Application::class, $intel->application);
        $this->assertInstanceOf(Company::class, $intel->company);
        $this->assertSame(RoleIntelStatus::Ready, $intel->status);
        $this->assertTrue($intel->application->roleIntel->is($intel));
    }

    public function test_domains_are_normalized(): void
    {
        $this->assertSame('acme.com', Company::normalizeDomain('https://www.Acme.com/careers'));
        $this->assertSame('acme.com', Company::normalizeDomain('acme.com'));
        $this->assertSame('jobs.acme.co.uk', Company::normalizeDomain('http://jobs.acme.co.uk'));
        $this->assertNull(Company::normalizeDomain(''));
        $this->assertNull(Company::normalizeDomain('not a website'));
    }

    public function test_matching_ignores_name_case(): void
    {
        $company = Company::factory()->create(['name' => 'Acme Robotics', 'website_domain' => null]);

        $this->assertTrue(Company::findMatching('acme robotics', null)->is($company));
    }

    public function test_a_domain_match_beats_a_name_match(): void
    {
        Company::factory()->create(['name' => 'Acme', 'website_domain' => null]);
        $byDomain = Company::factory()->create(['name' => 'Acme Corporation', 'website_domain' => 'acme-intel-test.com']);

        $this->assertTrue(Company::findMatching('Acme', 'https://www.acme-intel-test.com')->is($byDomain));
    }

    public function test_a_name_match_does_not_claim_a_company_with_another_domain(): void
    {
        Company::factory()->create(['name' => 'Acme', 'website_domain' => 'acme-one.example']);

        $this->assertNull(Company::findMatching('Acme', 'acme-two.example'));
    }

    public function test_rejected_companies_match_by_name_or_domain(): void
    {
        $intel = RoleIntel::factory()->create();
        $wrong = Company::factory()->create(['name' => 'Wrong Co', 'website_domain' => 'wrong-intel.example']);

        $intel->rememberRejected($wrong);
        $intel->save();

        $intel->refresh();
        $this->assertTrue($intel->hasRejected('wrong co', null));
        $this->assertTrue($intel->hasRejected('Another Name', 'https://wrong-intel.example/about'));
        $this->assertFalse($intel->hasRejected('Right Co', 'right-intel.example'));
    }

    public function test_the_prompt_is_found_by_id_after_renaming(): void
    {
        AiSystemPrompt::findOrFail(CompanyIntelService::PROMPT_ID_COMPANY_INTEL)->update(['title' => 'Renamed']);

        $this->assertSame('Renamed', AiSystemPrompt::find(CompanyIntelService::PROMPT_ID_COMPANY_INTEL)?->title);
    }
}
