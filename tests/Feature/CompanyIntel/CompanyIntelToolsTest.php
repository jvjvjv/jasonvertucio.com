<?php

namespace Tests\Feature\CompanyIntel;

use App\Models\AiConversation;
use App\Models\Application;
use App\Models\Company;
use App\Models\CompanyInsight;
use App\Models\RoleIntel;
use App\Models\User;
use App\Services\CompanyIntel\CompanyIntelService;
use App\Services\Mcp\CompanyIntelToolRegistry;
use BSPDX\Keystone\Models\KeystonePermission as Permission;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Ai\Contracts\Tool as LaravelAiTool;
use Tests\TestCase;

class CompanyIntelToolsTest extends TestCase
{
    use DatabaseTransactions;

    private function editor(): User
    {
        Permission::firstOrCreate(['name' => 'edit-resume']);
        $user = User::factory()->create();
        $user->givePermissionTo('edit-resume');

        return $user;
    }

    private function intel(?Company $company = null, ?User $user = null): RoleIntel
    {
        $conversation = AiConversation::factory()->create([
            'feature' => CompanyIntelService::FEATURE,
            'user_id' => ($user ?? $this->editor())->id,
        ]);

        return RoleIntel::factory()->create([
            'application_id' => Application::factory()->create(['company_name' => 'Acme', 'position' => 'Staff Engineer'])->id,
            'company_id' => $company?->id,
            'ai_conversation_id' => $conversation->id,
            'summary' => null,
            'responsibilities' => null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function callTool(RoleIntel $intel, string $tool, array $input = []): array
    {
        $conversation = AiConversation::findOrFail($intel->ai_conversation_id);

        return (new CompanyIntelToolRegistry($conversation))->dispatch($tool, $input);
    }

    public function test_the_context_describes_the_job(): void
    {
        $result = $this->callTool($this->intel(), 'get-intel-context');

        $this->assertSame('Staff Engineer', $result['job']['title']);
        $this->assertSame('Acme', $result['job']['company_name']);
        $this->assertNull($result['company']);
    }

    public function test_saving_a_profile_creates_and_attaches_a_company(): void
    {
        $intel = $this->intel();

        $result = $this->callTool($intel, 'save-company-profile', [
            'name' => 'Acme',
            'website' => 'https://www.acme-tools-test.com',
            'employee_count' => '501–1,000',
            'ownership' => 'Private',
            'founded_year' => 1999,
        ]);

        $this->assertTrue($result['success']);
        $this->assertTrue($result['created']);

        $company = $intel->fresh()->company;
        $this->assertSame('acme-tools-test.com', $company->website_domain);
        $this->assertSame('501–1,000', $company->employee_count);
        $this->assertSame('private', $company->ownership);
        $this->assertSame(1999, $company->founded_year);
        $this->assertNotNull($company->researched_at);
    }

    public function test_a_non_web_website_is_dropped(): void
    {
        $intel = $this->intel();

        $this->callTool($intel, 'save-company-profile', ['name' => 'Scriptco', 'website' => 'javascript:alert(document.cookie)']);

        $company = $intel->fresh()->company;
        $this->assertNull($company->website_url);
        $this->assertNull($company->website_domain);
    }

    public function test_a_bare_domain_is_stored_as_an_https_url(): void
    {
        $intel = $this->intel();

        $this->callTool($intel, 'save-company-profile', ['name' => 'Bareco', 'website' => 'bareco-tools-test.com']);

        $this->assertSame('https://bareco-tools-test.com', $intel->fresh()->company->website_url);
    }

    public function test_a_second_application_at_the_same_domain_reuses_the_company(): void
    {
        $existing = Company::factory()->create(['name' => 'Acme Inc.', 'website_domain' => 'acme-reuse-test.com', 'revenue' => '$5M']);
        $intel = $this->intel();

        $result = $this->callTool($intel, 'save-company-profile', ['name' => 'Acme', 'website' => 'acme-reuse-test.com']);

        $this->assertFalse($result['created']);
        $this->assertTrue($intel->fresh()->company->is($existing));
        $this->assertSame('$5M', $existing->fresh()->revenue);
    }

    public function test_a_second_call_fills_gaps_without_erasing(): void
    {
        $company = Company::factory()->create(['revenue' => '$5M', 'headquarters' => null]);
        $intel = $this->intel($company);

        $this->callTool($intel, 'save-company-profile', ['name' => $company->name, 'headquarters' => 'Portland, OR']);

        $company->refresh();
        $this->assertSame('$5M', $company->revenue);
        $this->assertSame('Portland, OR', $company->headquarters);
    }

    public function test_a_different_company_cannot_replace_the_attached_one(): void
    {
        $company = Company::factory()->create();
        $intel = $this->intel($company);

        $result = $this->callTool($intel, 'save-company-profile', ['name' => 'Other', 'website' => 'other-replace-test.com']);

        $this->assertArrayHasKey('error', $result);
        $this->assertTrue($intel->fresh()->company->is($company));
    }

    public function test_a_rejected_company_is_refused(): void
    {
        $intel = $this->intel();
        $intel->rejected_companies = [['name' => 'Wrong Co', 'domain' => 'wrong-tools-test.com']];
        $intel->save();

        $result = $this->callTool($intel, 'save-company-profile', ['name' => 'Wrong Company', 'website' => 'https://wrong-tools-test.com']);

        $this->assertStringContainsString('rejected', $result['error']);
        $this->assertNull($intel->fresh()->company_id);
    }

    public function test_an_insight_needs_an_attached_company(): void
    {
        $result = $this->callTool($this->intel(), 'add-company-insight', [
            'category' => 'culture',
            'title' => 'Remote first',
            'content' => 'Mostly remote.',
        ]);

        $this->assertArrayHasKey('error', $result);
        $this->assertSame(0, CompanyInsight::query()->where('title', 'Remote first')->count());
    }

    public function test_an_insight_is_recorded_on_the_company(): void
    {
        $company = Company::factory()->create();
        $intel = $this->intel($company);

        $result = $this->callTool($intel, 'add-company-insight', [
            'category' => 'interview-process',
            'title' => 'Four rounds',
            'content' => 'Screen, technical, system design, values.',
            'source_url' => 'https://example.com/interviews',
        ]);

        $this->assertTrue($result['success']);
        $insight = $company->insights()->sole();
        $this->assertSame('Four rounds', $insight->title);
        $this->assertSame('https://example.com/interviews', $insight->source_url);
    }

    public function test_a_partial_role_update_leaves_other_sections(): void
    {
        $intel = $this->intel();
        $intel->update(['summary' => 'Original summary']);

        $this->callTool($intel, 'save-role-intel', ['compensation' => '$180k–$220k']);

        $intel->refresh();
        $this->assertSame('Original summary', $intel->summary);
        $this->assertSame('$180k–$220k', $intel->compensation);
    }

    public function test_tools_refuse_outside_an_intel_session(): void
    {
        $conversation = AiConversation::factory()->create(['user_id' => $this->editor()->id]);

        $result = (new CompanyIntelToolRegistry($conversation))->dispatch('save-role-intel', ['summary' => 'x']);

        $this->assertStringContainsString('only works in a Company Intel session', $result['error']);
    }

    public function test_tools_refuse_a_user_without_resume_access(): void
    {
        $intel = $this->intel(user: User::factory()->create());

        $result = $this->callTool($intel, 'save-role-intel', ['summary' => 'x']);

        $this->assertArrayHasKey('error', $result);
        $this->assertNull($intel->fresh()->summary);
    }

    /**
     * @return list<string>
     */
    private function toolNames(RoleIntel $intel, ?array $granted): array
    {
        $conversation = AiConversation::findOrFail($intel->ai_conversation_id);

        return array_map(
            fn (LaravelAiTool $tool): string => $tool->name(),
            (new CompanyIntelToolRegistry($conversation))->toLaravelAiTools($granted),
        );
    }

    public function test_web_tools_follow_the_system_grants(): void
    {
        $intel = $this->intel();

        $without = $this->toolNames($intel, []);
        $with = $this->toolNames($intel, ['fetch-web-page']);

        $this->assertContains('save-company-profile', $without);
        $this->assertNotContains('fetch-web-page', $without);
        $this->assertContains('fetch-web-page', $with);
    }

    public function test_the_instructions_carry_the_prompt_and_the_recording_rules(): void
    {
        $instructions = CompanyIntelService::instructions('Custom research prompt.');

        $this->assertStringStartsWith('Custom research prompt.', $instructions);
        $this->assertStringContainsString('## Recording your findings', $instructions);
        $this->assertStringContainsString(CompanyIntelService::buildCompanyIntelPrompt(), CompanyIntelService::instructions(null));
    }
}
