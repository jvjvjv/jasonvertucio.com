<?php

namespace Tests\Feature;

use App\Mcp\Servers\PublicServer;
use App\Mcp\Tools\PublicResumeDataTool;
use App\Models\ResumeVersion;
use App\Models\User;
use App\Services\Mcp\Tools\ChatBot\GetResumeDataTool;
use App\Services\ResumeEditCandidateService;
use BSPDX\Keystone\Models\KeystonePermission as Permission;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Jvjvjv\CodeTalker\Support\ToolContext;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Testing\TestResponse;
use Tests\TestCase;

/**
 * What each caller of the public MCP endpoint is entitled to see, and — just as
 * importantly — which of those rules stop at the endpoint and which reach the
 * shared tool.
 */
class PublicMcpProjectionTest extends TestCase
{
    use DatabaseTransactions;

    private function liveVersion(): ResumeVersion
    {
        $version = ResumeVersion::factory()->create(['is_current' => true]);

        $version->personalInfo()->create([
            'name' => 'Jason Vertucio',
            'title' => 'Lead Front-End Engineer',
            'email' => 'jason@example.com',
            'phone' => '(555) 123-4567',
            'linkedin' => 'linkedin.com/in/jasonvertucio',
            'url' => 'https://jasonvertucio.com',
            'summary' => 'Builds things.',
        ]);

        $version->experiences()->create([
            'job_title' => 'Engineer',
            'company' => 'Acme',
            'sort_order' => 0,
            'salary_start_amount' => '100000',
            'salary_start_period' => 'per_year',
            'salary_end_amount' => '150000',
            'salary_end_period' => 'per_year',
        ]);

        $version->educations()->create([
            'institution' => 'State University',
            'sort_order' => 0,
        ]);

        $version->projects()->create([
            'project_name' => 'Side Project',
            'sort_order' => 0,
        ]);

        return $version;
    }

    private function userWith(string $permission): User
    {
        Permission::firstOrCreate(['name' => $permission]);

        $user = User::factory()->create();
        $user->givePermissionTo($permission);

        return $user;
    }

    /**
     * The structured payload out of an MCP test response.
     *
     * `assertStructuredContent()`'s closure form hands over an AssertableJson
     * and then demands every key was interacted with, which is the wrong shape
     * for asserting that specific keys are absent.
     *
     * @return array<string, mixed>
     */
    private function structured(TestResponse $response): array
    {
        return (fn (): array => $this->response->toArray()['result']['structuredContent'] ?? [])->call($response);
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function publicEndpointResumeData(array $arguments = [], ?User $user = null): array
    {
        $pending = $user === null
            ? PublicServer::tool(PublicResumeDataTool::class, $arguments)
            : PublicServer::actingAs($user)->tool(PublicResumeDataTool::class, $arguments);

        return $this->structured($pending->assertOk());
    }

    /**
     * The payload the chat path produces, built the way the chat loop builds
     * it: a ToolContext supplied per-call rather than resolved from an HTTP
     * caller. Reaches the protected projection directly so the assertion is
     * about the data, not about MCP response framing.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function chatPathResumeData(?int $userId = null, array $arguments = []): array
    {
        $tool = app()->makeWith(GetResumeDataTool::class, [
            'context' => ToolContext::forUser($userId),
        ]);

        $request = new Request($arguments);

        return (fn (): array => $this->resumeDataFor($request))->call($tool);
    }

    public function test_anonymous_caller_receives_no_direct_contact_details(): void
    {
        $this->liveVersion();

        $content = $this->publicEndpointResumeData();

        $this->assertArrayNotHasKey('email', $content['personal']);
        $this->assertArrayNotHasKey('phone', $content['personal']);

        $this->assertSame('Jason Vertucio', $content['personal']['name']);
        $this->assertSame('linkedin.com/in/jasonvertucio', $content['personal']['linkedin']);
        $this->assertSame('https://jasonvertucio.com', $content['personal']['url']);
        $this->assertSame('Builds things.', $content['personal']['summary']);
    }

    public function test_anonymous_caller_still_receives_education_experience_skills_and_projects(): void
    {
        $this->liveVersion();

        $content = $this->publicEndpointResumeData();

        $this->assertNotEmpty($content['education']);
        $this->assertNotEmpty($content['experience']);
        $this->assertNotEmpty($content['projects']);
        $this->assertArrayHasKey('skills', $content);

        $this->assertSame('State University', $content['education'][0]['institution']);
    }

    public function test_token_holder_receives_direct_contact_details(): void
    {
        $this->liveVersion();

        $content = $this->publicEndpointResumeData(user: $this->userWith('save-resume'));

        $this->assertSame('jason@example.com', $content['personal']['email']);
        $this->assertSame('(555) 123-4567', $content['personal']['phone']);
    }

    public function test_anonymous_caller_receives_no_salary_history(): void
    {
        $this->liveVersion();

        $content = $this->publicEndpointResumeData();

        $this->assertNull($content['experience'][0]['salaryStart']);
        $this->assertNull($content['experience'][0]['salaryEnd']);
    }

    public function test_token_holder_with_permission_receives_salary_history(): void
    {
        $this->liveVersion();

        $content = $this->publicEndpointResumeData(user: $this->userWith('save-resume'));

        $this->assertNotNull($content['experience'][0]['salaryStart']);
        $this->assertSame('100000', (string) $content['experience'][0]['salaryStart']['amount']);
    }

    public function test_anonymous_caller_is_not_told_a_draft_exists(): void
    {
        $version = $this->liveVersion();
        app(ResumeEditCandidateService::class)->resolveOrCreateCandidateForEdit($version, null);

        $content = $this->publicEndpointResumeData();

        $this->assertArrayNotHasKey('pending_revision_number', $content);
    }

    public function test_anonymous_request_for_a_revision_is_indistinguishable_from_no_request(): void
    {
        $version = $this->liveVersion();
        $candidate = app(ResumeEditCandidateService::class)->resolveOrCreateCandidateForEdit($version, null);

        $withoutArgument = $this->publicEndpointResumeData();
        $withArgument = $this->publicEndpointResumeData(['revision_number' => $candidate->revision_number]);

        $this->assertSame($withoutArgument, $withArgument);
        $this->assertArrayNotHasKey('requested_revision_found', $withArgument);
        $this->assertArrayNotHasKey('viewing_revision_number', $withArgument);
        $this->assertArrayNotHasKey('viewing_revision_status', $withArgument);
    }

    public function test_edit_resume_holder_still_sees_pending_revisions(): void
    {
        $version = $this->liveVersion();
        $candidate = app(ResumeEditCandidateService::class)->resolveOrCreateCandidateForEdit($version, null);
        $user = $this->userWith('edit-resume');

        $listing = $this->publicEndpointResumeData(user: $user);
        $this->assertSame($candidate->revision_number, $listing['pending_revision_number']);

        $viewing = $this->publicEndpointResumeData(['revision_number' => $candidate->revision_number], $user);
        $this->assertTrue($viewing['requested_revision_found']);
        $this->assertSame($candidate->revision_number, $viewing['viewing_revision_number']);
        $this->assertSame('pending', $viewing['viewing_revision_status']);
    }

    public function test_contact_redaction_stops_at_the_endpoint(): void
    {
        $this->liveVersion();

        $chatData = $this->chatPathResumeData(null);

        $this->assertSame('jason@example.com', $chatData['personal']['email']);
        $this->assertSame('(555) 123-4567', $chatData['personal']['phone']);
    }

    public function test_draft_suppression_does_reach_the_chat_path(): void
    {
        $version = $this->liveVersion();
        app(ResumeEditCandidateService::class)->resolveOrCreateCandidateForEdit($version, null);

        $chatData = $this->chatPathResumeData(null);

        $this->assertArrayNotHasKey('pending_revision_number', $chatData);
    }
}
