<?php

namespace Tests\Feature\CompanyIntel;

use App\Enums\RoleIntelStatus;
use App\Models\Application;
use App\Models\Company;
use App\Models\RoleIntel;
use App\Models\User;
use BSPDX\Keystone\Models\KeystonePermission as Permission;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ApplicationIntelColumnTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();
        Permission::firstOrCreate(['name' => 'edit-resume']);
        $this->admin->givePermissionTo('edit-resume');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function rows(): array
    {
        $rows = null;

        $this->actingAs($this->admin)
            ->get(route('admin.resume.applications.index'))
            ->assertOk()
            ->assertInertia(function (Assert $page) use (&$rows): void {
                $rows = $page->toArray()['props']['applications'];
            });

        return collect($rows)->keyBy('id')->all();
    }

    public function test_the_intel_cell_links_role_company_or_nothing(): void
    {
        $company = Company::factory()->create(['name' => 'Columnco Industries']);
        $withIntel = Application::factory()->create(['company_name' => 'Columnco Industries']);
        $intel = RoleIntel::factory()->status(RoleIntelStatus::Running)->create(['application_id' => $withIntel->id, 'company_id' => $company->id]);
        $sameCompany = Application::factory()->create(['company_name' => 'COLUMNCO industries']);
        $unknown = Application::factory()->create(['company_name' => 'Nobody Knows Co']);

        $rows = $this->rows();

        $this->assertSame(['id' => $intel->id, 'status' => 'running', 'scope' => 'role', 'company_name' => null], $rows[$withIntel->id]['intel']);
        $this->assertSame(['id' => $intel->id, 'status' => 'running', 'scope' => 'company', 'company_name' => 'Columnco Industries'], $rows[$sameCompany->id]['intel']);
        $this->assertNull($rows[$unknown->id]['intel']);
    }

    public function test_the_list_does_not_query_intel_per_row(): void
    {
        foreach (range(1, 3) as $i) {
            RoleIntel::factory()->create(['application_id' => Application::factory()->create(['company_name' => "Nplusone {$i}"])->id]);
            Application::factory()->create(['company_name' => "Nplusone other {$i}"]);
        }

        DB::enableQueryLog();
        $this->rows();
        $intelQueries = collect(DB::getQueryLog())->filter(fn (array $query): bool => str_contains($query['query'], 'role_intels'));
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(2, $intelQueries->count());
    }

    public function test_the_discussion_page_carries_the_intel(): void
    {
        $intel = RoleIntel::factory()->create();

        $this->actingAs($this->admin)
            ->get(route('admin.resume.applications.show', $intel->application_id))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('roleIntel', ['id' => $intel->id, 'status' => 'ready']));
    }
}
