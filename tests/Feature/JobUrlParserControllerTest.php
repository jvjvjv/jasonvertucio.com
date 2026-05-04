<?php

namespace Tests\Feature;

use App\Models\JobUrl;
use App\Models\JobUrlParser;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class JobUrlParserControllerTest extends TestCase
{
    use DatabaseTransactions;

    private function authenticatedUser(): User
    {
        Permission::findOrCreate('manage-ai-tools', 'web');
        $user = User::factory()->create();
        $user->givePermissionTo('manage-ai-tools');

        return $user;
    }

    public function test_destroy_requires_authentication(): void
    {
        $parser = JobUrlParser::factory()->create();

        $response = $this->delete(route('admin.ai.job-url-parsers.destroy', $parser));

        $response->assertRedirect(route('login'));
    }

    public function test_destroy_requires_manage_ai_tools_permission(): void
    {
        $user = User::factory()->create();
        $parser = JobUrlParser::factory()->create();

        $response = $this->actingAs($user)
            ->delete(route('admin.ai.job-url-parsers.destroy', $parser));

        $response->assertForbidden();
        $this->assertDatabaseHas('job_url_parsers', ['id' => $parser->id]);
    }

    public function test_destroy_deletes_inactive_parser(): void
    {
        $user = $this->authenticatedUser();
        $parser = JobUrlParser::factory()->create(['status' => 'inactive']);

        $response = $this->actingAs($user)
            ->delete(route('admin.ai.job-url-parsers.destroy', $parser));

        $response->assertRedirect(route('admin.ai.job-url-parsers.index'));
        $this->assertDatabaseMissing('job_url_parsers', ['id' => $parser->id]);
    }

    public function test_destroy_redirects_with_success_flash(): void
    {
        $user = $this->authenticatedUser();
        $parser = JobUrlParser::factory()->create(['status' => 'inactive']);

        $response = $this->actingAs($user)
            ->delete(route('admin.ai.job-url-parsers.destroy', $parser));

        $response->assertSessionHas('success');
    }

    public function test_destroy_prevents_deleting_active_parser(): void
    {
        $user = $this->authenticatedUser();
        $parser = JobUrlParser::factory()->active()->create();

        $response = $this->actingAs($user)
            ->delete(route('admin.ai.job-url-parsers.destroy', $parser));

        $response->assertForbidden();
        $this->assertDatabaseHas('job_url_parsers', ['id' => $parser->id]);
    }

    public function test_destroy_cascades_to_associated_job_urls(): void
    {
        $user = $this->authenticatedUser();
        $parser = JobUrlParser::factory()->create(['status' => 'inactive']);
        $jobUrl = JobUrl::factory()->create(['job_url_parser_id' => $parser->id]);

        $this->actingAs($user)
            ->delete(route('admin.ai.job-url-parsers.destroy', $parser));

        $this->assertDatabaseMissing('job_url_parsers', ['id' => $parser->id]);
        $this->assertDatabaseMissing('job_urls', ['id' => $jobUrl->id]);
    }
}
