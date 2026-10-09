<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\TargetedResume;
use App\Models\User;
use BSPDX\Keystone\Models\KeystonePermission as Permission;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Testing\TestResponse;
use Jvjvjv\CodeTalker\Services\LaravelAi\AgentFactory;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Spec "Application pages and endpoints require resume-editing permission"
 * and "Targeted Resumes list requires resume-editing permission".
 *
 * What the app's auth middleware does, as asserted here: a signed-in user
 * without `edit-resume` gets 403 whether or not the request asks for JSON;
 * an anonymous visitor is redirected to the login page, except that a
 * request asking for JSON gets 401 instead.
 */
class ApplicationPermissionTest extends TestCase
{
    use DatabaseTransactions;

    private Application $application;

    private int $statusUpdateId;

    private int $targetedResumeId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mock(AgentFactory::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('forSystem');
        });

        Permission::firstOrCreate(['name' => 'edit-resume']);

        $this->application = Application::factory()->withTargetedResume()->applied()->create();
        $this->statusUpdateId = $this->application->statusUpdates()->sole()->id;
        $this->targetedResumeId = $this->application->targeted_resume_id;
    }

    /**
     * Every page under the applications and targeted-resumes areas.
     *
     * @return array<string, array{0: string, 1: array<int, string>}>
     */
    public static function pageProvider(): array
    {
        return [
            'applications list' => ['admin.resume.applications.index', []],
            'new session page' => ['admin.resume.applications.create', []],
            'discussion page' => ['admin.resume.applications.show', ['application']],
            'targeted resumes list' => ['admin.resume.targeted.index', []],
            'edit targeted resume page' => ['admin.resume.targeted.edit', ['targetedResume']],
            'application metrics' => ['admin.resume.metrics', []],
            'targeted resume download' => ['admin.resume.targeted.download', ['targetedResume', 'format']],
        ];
    }

    /**
     * Every endpoint that changes something, with a payload that would be
     * valid for an authorized caller.
     *
     * @return array<string, array{0: string, 1: string, 2: array<int, string>, 3: array<string, mixed>}>
     */
    public static function endpointProvider(): array
    {
        return [
            'store' => ['post', 'admin.resume.applications.store', [], ['intent' => 'applied', 'job_description' => 'Build things.', 'company_name' => 'Permzq Intruder Co']],
            'update' => ['put', 'admin.resume.applications.update', ['application'], ['company_name' => 'Permzq Intruder Co']],
            'analysis' => ['post', 'admin.resume.applications.analysis', ['application'], []],
            'apply' => ['post', 'admin.resume.applications.apply', ['application'], []],
            'pass' => ['post', 'admin.resume.applications.pass', ['application'], []],
            'chat' => ['post', 'admin.resume.applications.chat', ['application'], ['message' => 'Hello']],
            'finalize' => ['post', 'admin.resume.applications.finalize', ['application'], ['tailored_content' => '# Summary']],
            'finalize cover letter' => ['post', 'admin.resume.applications.finalize-cover-letter', ['application'], ['cover_letter_content' => 'Dear team']],
            'status update store' => ['post', 'admin.resume.applications.status-updates.store', ['application'], ['status' => 'interviewing']],
            'status update update' => ['put', 'admin.resume.applications.status-updates.update', ['application', 'statusUpdate'], ['notes' => 'Hijacked', 'occurred_at' => '2020-01-01']],
            'status update destroy' => ['delete', 'admin.resume.applications.status-updates.destroy', ['application', 'statusUpdate'], []],
            'application destroy' => ['delete', 'admin.resume.applications.destroy', ['application'], []],
            'targeted resume discard' => ['delete', 'admin.resume.targeted.destroy', ['targetedResume'], []],
            'targeted resume update markdown' => ['put', 'admin.resume.targeted-resume.update-markdown', ['targetedResume'], ['markdown' => '# Summary\nHijacked']],
            'targeted resume regenerate' => ['post', 'admin.resume.targeted.regenerate', ['targetedResume'], []],
        ];
    }

    /**
     * @param  array<int, string>  $parameters
     */
    #[DataProvider('pageProvider')]
    public function test_unauthorized_user_is_refused_a_page_with_403(string $routeName, array $parameters): void
    {
        $this->actingAs($this->userWithout())
            ->get($this->url($routeName, $parameters))
            ->assertForbidden();

        $this->actingAs($this->userWith('manage-blog'))
            ->get($this->url($routeName, $parameters))
            ->assertForbidden();
    }

    /**
     * @param  array<int, string>  $parameters
     */
    #[DataProvider('pageProvider')]
    public function test_anonymous_visitor_is_redirected_from_a_page_to_login(string $routeName, array $parameters): void
    {
        $this->get($this->url($routeName, $parameters))->assertRedirect(route('login'));
    }

    /**
     * @param  array<int, string>  $parameters
     * @param  array<string, mixed>  $payload
     */
    #[DataProvider('endpointProvider')]
    public function test_unauthorized_user_is_refused_an_endpoint_with_403(string $method, string $routeName, array $parameters, array $payload): void
    {
        $url = $this->url($routeName, $parameters);

        $this->actingAs($this->userWithout());

        $this->send($method, $url, $payload, json: true)->assertForbidden();
        $this->send($method, $url, $payload, json: false)->assertForbidden();

        $this->assertNothingChanged();
    }

    /**
     * @param  array<int, string>  $parameters
     * @param  array<string, mixed>  $payload
     */
    #[DataProvider('endpointProvider')]
    public function test_anonymous_json_request_to_an_endpoint_is_unauthenticated(string $method, string $routeName, array $parameters, array $payload): void
    {
        $this->send($method, $this->url($routeName, $parameters), $payload, json: true)->assertUnauthorized();

        $this->assertNothingChanged();
    }

    /**
     * @param  array<int, string>  $parameters
     * @param  array<string, mixed>  $payload
     */
    #[DataProvider('endpointProvider')]
    public function test_anonymous_non_json_request_to_an_endpoint_is_redirected_to_login(string $method, string $routeName, array $parameters, array $payload): void
    {
        $this->send($method, $this->url($routeName, $parameters), $payload, json: false)->assertRedirect(route('login'));

        $this->assertNothingChanged();
    }

    /**
     * @param  array<int, string>  $parameters
     */
    #[DataProvider('pageProvider')]
    public function test_anonymous_json_request_for_a_page_is_unauthenticated(string $routeName, array $parameters): void
    {
        $this->getJson($this->url($routeName, $parameters))->assertUnauthorized();
    }

    public function test_former_builder_urls_are_protected_too(): void
    {
        foreach (['/admin/resume/targeted-builder', '/admin/resume/targeted-builder/new'] as $url) {
            $this->get($url)->assertRedirect(route('login'));
        }

        $this->actingAs($this->userWithout());

        foreach (['/admin/resume/targeted-builder', '/admin/resume/targeted-builder/new'] as $url) {
            $this->get($url)->assertForbidden();
        }
    }

    public function test_the_permission_is_what_opens_the_pages(): void
    {
        $user = $this->userWithout();

        $this->actingAs($user)->get(route('admin.resume.applications.index'))->assertForbidden();
        $this->actingAs($user)->get(route('admin.resume.targeted.index'))->assertForbidden();

        $user->givePermissionTo('edit-resume');

        $this->actingAs($user->fresh())->get(route('admin.resume.applications.index'))->assertOk();
        $this->actingAs($user->fresh())->get(route('admin.resume.applications.create'))->assertOk();
        $this->actingAs($user->fresh())->get(route('admin.resume.applications.show', $this->application))->assertOk();
        $this->actingAs($user->fresh())->get(route('admin.resume.targeted.index'))->assertOk();
        $this->actingAs($user->fresh())->get(route('admin.resume.targeted.edit', $this->targetedResumeId))->assertOk();
    }

    /**
     * Not a spec scenario — pins framework behaviour worth knowing about:
     * route-model binding runs before the `can:` middleware, so a signed-in
     * user without the permission is told 404 for an id that does not exist
     * and 403 for one that does. An anonymous visitor learns nothing.
     */
    public function test_an_unknown_application_is_not_found_before_the_permission_is_checked(): void
    {
        $this->get('/admin/resume/applications/999999999')->assertRedirect(route('login'));
        $this->postJson('/api/admin/resume/applications/999999999/pass')->assertUnauthorized();

        $this->actingAs($this->userWithout());

        $this->get(route('admin.resume.applications.show', $this->application))->assertForbidden();
        $this->get('/admin/resume/applications/999999999')->assertNotFound();
        $this->postJson('/api/admin/resume/applications/999999999/pass')->assertNotFound();
    }

    // ---------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------

    private function userWithout(): User
    {
        return User::factory()->create();
    }

    private function userWith(string $permission): User
    {
        Permission::firstOrCreate(['name' => $permission]);
        $user = User::factory()->create();
        $user->givePermissionTo($permission);

        return $user;
    }

    /**
     * @param  array<int, string>  $parameters
     */
    private function url(string $routeName, array $parameters): string
    {
        $values = [
            'application' => $this->application->id,
            'statusUpdate' => $this->statusUpdateId,
            'targetedResume' => $this->targetedResumeId,
            'format' => 'pdf',
        ];

        return route($routeName, array_map(fn (string $parameter): int|string => $values[$parameter], $parameters));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function send(string $method, string $url, array $payload, bool $json): TestResponse
    {
        return $json
            ? $this->json(strtoupper($method), $url, $payload)
            : $this->call(strtoupper($method), $url, $payload);
    }

    private function assertNothingChanged(): void
    {
        $application = Application::withTrashed()->findOrFail($this->application->id);

        $this->assertNull($application->deleted_at);
        $this->assertSame(ApplicationStatus::Applied, $application->status);
        $this->assertSame($this->application->company_name, $application->company_name);
        $this->assertNull($application->ai_conversation_id);
        $this->assertSame($this->targetedResumeId, $application->targeted_resume_id);
        $this->assertSame(0, $application->coverLetters()->count());

        $entry = $application->statusUpdates()->sole();

        $this->assertSame($this->statusUpdateId, $entry->id);
        $this->assertNotSame('Hijacked', $entry->notes);

        $targetedResume = TargetedResume::query()->findOrFail($this->targetedResumeId);

        $this->assertStringNotContainsString('Hijacked', (string) data_get($targetedResume->tailored_data, 'markdown'));
        $this->assertDatabaseMissing('applications', ['company_name' => 'Permzq Intruder Co']);
    }
}
