<?php

namespace Tests\Feature\CompanyIntel;

use App\Enums\IntelStartMode;
use App\Enums\RoleIntelStatus;
use App\Jobs\RunCompanyIntelJob;
use App\Models\AiConversation;
use App\Models\Application;
use App\Models\Company;
use App\Models\CompanyInsight;
use App\Models\RoleIntel;
use App\Models\User;
use App\Services\CompanyIntel\CompanyIntelService;
use BSPDX\Keystone\Models\KeystonePermission as Permission;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Jvjvjv\CodeTalker\Models\AiSystem;
use Jvjvjv\CodeTalker\Models\AiSystemFeatureDefault;
use Tests\TestCase;

class CompanyIntelHttpTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        AiSystemFeatureDefault::query()->delete();
        AiSystem::query()->where('system_prompt_id', CompanyIntelService::PROMPT_ID_COMPANY_INTEL)->update(['system_prompt_id' => null]);

        $this->admin = User::factory()->create();
        Permission::firstOrCreate(['name' => 'edit-resume']);
        $this->admin->givePermissionTo('edit-resume');
    }

    private function eligibleSystem(): AiSystem
    {
        return AiSystem::factory()->create(['system_prompt_id' => CompanyIntelService::PROMPT_ID_COMPANY_INTEL]);
    }

    private function intelWithCompany(Company $company): RoleIntel
    {
        return RoleIntel::factory()->create([
            'company_id' => $company->id,
            'ai_conversation_id' => AiConversation::factory()->create(['feature' => 'company-intel', 'user_id' => $this->admin->id])->id,
        ]);
    }

    public function test_pages_and_endpoints_refuse_a_user_without_resume_access(): void
    {
        $intel = RoleIntel::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($other)->get(route('admin.employment.company-intel.index'))->assertForbidden();
        $this->actingAs($other)->get(route('admin.employment.company-intel.show', $intel))->assertForbidden();
        $this->actingAs($other)->getJson(route('admin.employment.company-intel.state', $intel))->assertForbidden();
        $this->actingAs($other)->postJson(route('admin.employment.company-intel.start', $intel->application_id), ['mode' => 'background'])->assertForbidden();
    }

    public function test_guests_are_sent_to_login(): void
    {
        $this->get(route('admin.employment.company-intel.index'))->assertRedirect('/login');
    }

    public function test_starting_with_an_eligible_system_queues_intel(): void
    {
        $application = Application::factory()->create();
        $system = $this->eligibleSystem();

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.employment.company-intel.start', $application), ['ai_system_id' => $system->id, 'mode' => 'background'])
            ->assertCreated();

        $intel = RoleIntel::findOrFail($response->json('role_intel_id'));
        $this->assertSame($application->id, $intel->application_id);
        $response->assertJsonPath('redirect', route('admin.employment.company-intel.show', $intel));
        Queue::assertPushed(RunCompanyIntelJob::class);
    }

    public function test_starting_twice_is_a_conflict(): void
    {
        $application = Application::factory()->create();
        RoleIntel::factory()->create(['application_id' => $application->id]);

        $this->actingAs($this->admin)
            ->postJson(route('admin.employment.company-intel.start', $application), ['ai_system_id' => $this->eligibleSystem()->id, 'mode' => 'background'])
            ->assertConflict();
    }

    public function test_an_ineligible_system_is_refused(): void
    {
        $application = Application::factory()->create();

        $this->actingAs($this->admin)
            ->postJson(route('admin.employment.company-intel.start', $application), ['ai_system_id' => AiSystem::factory()->create(['system_prompt_id' => 1])->id, 'mode' => 'background'])
            ->assertUnprocessable();

        $this->assertNull($application->fresh()->roleIntel);
    }

    public function test_no_system_and_no_default_is_refused(): void
    {
        $application = Application::factory()->create();

        $this->actingAs($this->admin)
            ->postJson(route('admin.employment.company-intel.start', $application), ['mode' => 'stream'])
            ->assertUnprocessable();

        $this->assertNull($application->fresh()->roleIntel);
    }

    public function test_eligible_systems_lists_the_default_and_the_prompt_users(): void
    {
        $eligible = $this->eligibleSystem();
        $default = AiSystem::factory()->create(['system_prompt_id' => 1]);
        AiSystemFeatureDefault::create(['ai_system_id' => $default->id, 'feature' => 'company-intel']);

        $this->actingAs($this->admin)
            ->getJson(route('admin.employment.company-intel.eligible-systems'))
            ->assertOk()
            ->assertJsonPath('default.id', $default->id)
            ->assertJsonPath('systems.0.id', $eligible->id);
    }

    public function test_state_returns_the_findings(): void
    {
        $company = Company::factory()->create(['name' => 'Statey']);
        CompanyInsight::factory()->create(['company_id' => $company->id, 'category' => 'culture', 'title' => 'Remote first']);
        $intel = $this->intelWithCompany($company);

        $this->actingAs($this->admin)
            ->getJson(route('admin.employment.company-intel.state', $intel))
            ->assertOk()
            ->assertJsonPath('status', 'ready')
            ->assertJsonPath('company.name', 'Statey')
            ->assertJsonPath('company.insights.0.category', 'culture')
            ->assertJsonPath('company.insights.0.items.0.title', 'Remote first')
            ->assertJsonPath('role.summary', $intel->summary);
    }

    public function test_retry_reruns_failed_intel_in_the_background(): void
    {
        $intel = RoleIntel::factory()->status(RoleIntelStatus::Failed)->create(['failure_reason' => 'Timed out']);

        $this->actingAs($this->admin)
            ->postJson(route('admin.employment.company-intel.retry', $intel), ['mode' => 'background'])
            ->assertOk()
            ->assertJsonPath('status', 'queued');

        $this->assertNull($intel->fresh()->failure_reason);
        Queue::assertPushed(RunCompanyIntelJob::class);
    }

    public function test_retry_is_refused_unless_failed(): void
    {
        $intel = RoleIntel::factory()->create();

        $this->actingAs($this->admin)
            ->postJson(route('admin.employment.company-intel.retry', $intel), ['mode' => 'background'])
            ->assertConflict();
    }

    public function test_a_wrong_unshared_company_is_deleted_with_its_insights(): void
    {
        $company = Company::factory()->create(['name' => 'Wrongco', 'website_domain' => 'wrongco-http.example']);
        $insight = CompanyInsight::factory()->create(['company_id' => $company->id]);
        $intel = $this->intelWithCompany($company);

        $this->actingAs($this->admin)
            ->postJson(route('admin.employment.company-intel.wrong-company', $intel), ['hint' => 'Rightco, rightco.example'])
            ->assertOk()
            ->assertJsonPath('message', CompanyIntelService::correctionMessage('Wrongco', 'Rightco, rightco.example'))
            ->assertJsonPath('intel.company', null);

        $this->assertNull(Company::find($company->id));
        $this->assertNull(CompanyInsight::find($insight->id));
        $this->assertSame([['name' => 'Wrongco', 'domain' => 'wrongco-http.example']], $intel->fresh()->rejected_companies);
    }

    public function test_a_wrong_shared_company_is_kept_for_the_other_application(): void
    {
        $company = Company::factory()->create();
        $intel = $this->intelWithCompany($company);
        $other = $this->intelWithCompany($company);

        $this->actingAs($this->admin)
            ->postJson(route('admin.employment.company-intel.wrong-company', $intel), ['hint' => 'Someone else'])
            ->assertOk();

        $this->assertNotNull(Company::find($company->id));
        $this->assertSame($company->id, $other->fresh()->company_id);
        $this->assertNull($intel->fresh()->company_id);
    }

    public function test_choosing_an_existing_company_attaches_it_without_a_turn(): void
    {
        $intel = $this->intelWithCompany(Company::factory()->create());
        $right = Company::factory()->create();

        $this->actingAs($this->admin)
            ->postJson(route('admin.employment.company-intel.wrong-company', $intel), ['company_id' => $right->id])
            ->assertOk()
            ->assertJsonPath('message', null)
            ->assertJsonPath('intel.company.id', $right->id);
    }

    public function test_wrong_company_needs_a_hint_or_a_choice(): void
    {
        $intel = $this->intelWithCompany(Company::factory()->create());

        $this->actingAs($this->admin)
            ->postJson(route('admin.employment.company-intel.wrong-company', $intel), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['hint', 'company_id']);
    }

    public function test_the_picker_leaves_out_rejected_companies(): void
    {
        $intel = $this->intelWithCompany(Company::factory()->create(['name' => 'Picked Wrong', 'website_domain' => 'picked-wrong.example']));
        $kept = Company::factory()->create(['name' => 'Pickable']);
        $this->intelWithCompany($wrongShared = Company::factory()->create(['name' => 'Shared Wrong', 'website_domain' => 'shared-wrong.example']));
        $intel->rejected_companies = [['name' => 'Shared Wrong', 'domain' => 'shared-wrong.example']];
        $intel->save();

        $this->actingAs($this->admin)
            ->get(route('admin.employment.company-intel.show', $intel))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('employment/company-intel/Show', false)
                ->where('companyOptions', fn ($options): bool => collect($options)->contains('id', $kept->id)
                    && ! collect($options)->contains('id', $wrongShared->id)));
    }

    public function test_chat_refuses_while_the_queued_turn_waits(): void
    {
        $intel = app(CompanyIntelService::class)->start(Application::factory()->create(), $this->eligibleSystem(), IntelStartMode::Background, $this->admin->id);

        $this->actingAs($this->admin)
            ->postJson(route('admin.employment.company-intel.chat', $intel), ['message' => 'Hello'])
            ->assertConflict();
    }

    public function test_the_list_shows_intel_of_live_applications_newest_first(): void
    {
        $older = RoleIntel::factory()->create(['updated_at' => now()->subDay()]);
        $newer = RoleIntel::factory()->withoutCompany()->create(['updated_at' => now()]);
        $deleted = RoleIntel::factory()->create();
        $deleted->application->delete();

        $this->actingAs($this->admin)
            ->get(route('admin.employment.company-intel.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('employment/company-intel/Index', false)
                ->where('intel', function ($rows) use ($older, $newer, $deleted): bool {
                    $ids = collect($rows)->pluck('id');

                    return $ids->search($newer->id) < $ids->search($older->id)
                        && ! $ids->contains($deleted->id)
                        && collect($rows)->firstWhere('id', $newer->id)['company_name'] === null;
                }));
    }

    public function test_a_deleted_application_hides_its_intel_page(): void
    {
        $intel = RoleIntel::factory()->create();
        $intel->application->delete();

        $this->actingAs($this->admin)->get(route('admin.employment.company-intel.show', $intel))->assertNotFound();
    }
}
