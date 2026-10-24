<?php

namespace Tests\Feature\CompanyIntel;

use App\Enums\ApplicationStatus;
use App\Enums\IntelStartMode;
use App\Enums\RoleIntelStatus;
use App\Exceptions\RoleIntelAlreadyExistsException;
use App\Jobs\RunCompanyIntelJob;
use App\Models\AiConversation;
use App\Models\Application;
use App\Models\ResumeVersion;
use App\Models\RoleIntel;
use App\Models\User;
use App\Services\ApplicationService;
use App\Services\CompanyIntel\CompanyIntelService;
use App\Services\CompanyIntel\CompanyIntelSystems;
use BSPDX\Keystone\Models\KeystonePermission as Permission;
use Generator;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Queue\TimeoutExceededException;
use Illuminate\Support\Facades\Queue;
use Jvjvjv\CodeTalker\Models\AiSystem;
use Jvjvjv\CodeTalker\Models\AiSystemFeatureDefault;
use Jvjvjv\CodeTalker\Services\LaravelAi\AgentFactory;
use Jvjvjv\CodeTalker\Services\LaravelAi\CodeTalkerAgent;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\Usage;
use Laravel\Ai\Responses\StreamableAgentResponse;
use Laravel\Ai\Streaming\Events\StreamEnd;
use Laravel\Ai\Streaming\Events\StreamStart;
use Laravel\Ai\Streaming\Events\TextDelta;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class CompanyIntelLifecycleTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        AiSystemFeatureDefault::query()->delete();

        $this->admin = User::factory()->create();
        Permission::firstOrCreate(['name' => 'edit-resume']);
        $this->admin->givePermissionTo('edit-resume');
        $this->actingAs($this->admin);
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    private function service(): CompanyIntelService
    {
        return app(CompanyIntelService::class);
    }

    private function makeDefault(): AiSystem
    {
        $system = AiSystem::factory()->create();
        AiSystemFeatureDefault::create(['ai_system_id' => $system->id, 'feature' => CompanyIntelService::FEATURE]);

        return $system;
    }

    private function application(array $attributes = []): Application
    {
        return Application::factory()->create([
            'company_name' => 'Acme',
            'position' => 'Staff Engineer',
            'location' => 'Remote',
            'job_description' => 'Build things.',
            ...$attributes,
        ]);
    }

    private function bindAgent(?string $reply, ?\Throwable $failure = null): void
    {
        $agent = Mockery::mock(CodeTalkerAgent::class);
        $agent->shouldReceive('messages')->andReturn([]);

        if ($failure !== null) {
            $agent->shouldReceive('stream')->andThrow($failure);
        } else {
            $agent->shouldReceive('stream')->once()->andReturn(new StreamableAgentResponse(
                'id-1',
                static function () use ($reply): Generator {
                    yield new StreamStart('id-1', 'anthropic', 'claude-sonnet-4-6', time());
                    yield new TextDelta('e1', 'm1', $reply, time());
                    yield new StreamEnd('id-1', 'stop', new Usage(promptTokens: 10, completionTokens: 5), time());
                },
                new Meta(provider: 'anthropic', model: 'claude-sonnet-4-6'),
            ));
        }

        $factory = Mockery::mock(AgentFactory::class);
        $factory->shouldReceive('forSystem')->andReturn($agent);
        $this->app->instance(AgentFactory::class, $factory);
    }

    public function test_a_background_start_creates_the_session_and_queues_the_first_turn(): void
    {
        $application = $this->application();
        $system = AiSystem::factory()->create();

        $intel = $this->service()->start($application, $system, IntelStartMode::Background);

        $this->assertSame(RoleIntelStatus::Queued, $intel->status);
        $conversation = AiConversation::findOrFail($intel->ai_conversation_id);
        $this->assertSame('company-intel', $conversation->feature);
        $this->assertSame($system->id, $conversation->ai_system_id);
        $this->assertSame($this->admin->id, $conversation->user_id);
        $this->assertFalse($conversation->context['auto_start_pending']);

        $messages = $conversation->messages()->orderBy('id')->get();
        $this->assertSame(['system', 'user'], $messages->pluck('role')->all());
        $this->assertStringContainsString('## Recording your findings', $messages[0]->content);
        $this->assertStringStartsWith(CompanyIntelService::INITIAL_MESSAGE, $messages[1]->content);
        $this->assertStringContainsString('Job Title: Staff Engineer', $messages[1]->content);
        $this->assertStringContainsString('Company Name: Acme', $messages[1]->content);
        $this->assertStringContainsString('Build things.', $messages[1]->content);

        Queue::assertPushed(RunCompanyIntelJob::class, fn (RunCompanyIntelJob $job): bool => $job->roleIntelId === $intel->id);
    }

    public function test_a_streamed_start_leaves_the_turn_for_the_page(): void
    {
        $intel = $this->service()->start($this->application(), AiSystem::factory()->create(), IntelStartMode::Stream);

        $this->assertTrue(AiConversation::findOrFail($intel->ai_conversation_id)->context['auto_start_pending']);
        Queue::assertNothingPushed();
    }

    public function test_a_second_start_is_refused(): void
    {
        $application = $this->application();
        $this->service()->start($application, AiSystem::factory()->create(), IntelStartMode::Stream);

        $this->expectException(RoleIntelAlreadyExistsException::class);

        $this->service()->start($application->fresh(), AiSystem::factory()->create(), IntelStartMode::Stream);
    }

    public function test_a_turn_stores_the_reply_and_leaves_the_intel_ready(): void
    {
        $intel = $this->service()->start($this->application(), AiSystem::factory()->create(), IntelStartMode::Stream);
        $this->bindAgent('Acme makes anvils.');

        $frames = implode('', iterator_to_array($this->service()->runTurn($intel), false));

        $this->assertStringContainsString('"type":"page_reload"', $frames);
        $this->assertStringContainsString('[DONE]', $frames);
        $this->assertSame(RoleIntelStatus::Ready, $intel->fresh()->status);

        $conversation = AiConversation::findOrFail($intel->ai_conversation_id);
        $this->assertSame('Acme makes anvils.', $conversation->messages()->where('role', 'assistant')->sole()->content);
        $this->assertFalse($conversation->context['auto_start_pending']);
    }

    public function test_the_chat_endpoint_streams_the_pending_turn(): void
    {
        $intel = $this->service()->start($this->application(), AiSystem::factory()->create(), IntelStartMode::Stream);
        $this->bindAgent('Acme is hiring.');

        $response = $this->postJson(route('admin.employment.company-intel.chat', $intel), ['message' => '']);

        $response->assertOk();
        $this->assertStringContainsString('text/event-stream', $response->headers->get('Content-Type'));
        $body = $response->streamedContent();
        $this->assertStringContainsString('Acme is hiring.', $body);
        $this->assertStringContainsString('[DONE]', $body);
        $this->assertSame(RoleIntelStatus::Ready, $intel->fresh()->status);
        $this->assertSame('Acme is hiring.', AiConversation::findOrFail($intel->ai_conversation_id)->messages()->where('role', 'assistant')->sole()->content);
    }

    public function test_a_follow_up_message_is_stored_and_answered(): void
    {
        $intel = $this->service()->start($this->application(), AiSystem::factory()->create(), IntelStartMode::Stream);
        $intel->update(['status' => RoleIntelStatus::Ready]);
        $this->bindAgent('About 40 engineers.');

        $this->postJson(route('admin.employment.company-intel.chat', $intel), ['message' => 'How big is the team?'])
            ->assertOk()
            ->streamedContent();

        $roles = AiConversation::findOrFail($intel->ai_conversation_id)->messages()->orderBy('id')->pluck('content', 'role');
        $this->assertSame('About 40 engineers.', $roles['assistant']);
        $this->assertSame('How big is the team?', AiConversation::findOrFail($intel->ai_conversation_id)->messages()->where('role', 'user')->orderByDesc('id')->first()->content);
    }

    public function test_a_failed_turn_leaves_the_intel_failed_with_a_reason(): void
    {
        $intel = $this->service()->start($this->application(), AiSystem::factory()->create(), IntelStartMode::Stream);
        $this->bindAgent(null, new RuntimeException('Provider unavailable'));

        $frames = implode('', iterator_to_array($this->service()->runTurn($intel), false));

        $this->assertStringContainsString('Provider unavailable', $frames);
        $intel->refresh();
        $this->assertSame(RoleIntelStatus::Failed, $intel->status);
        $this->assertSame('Provider unavailable', $intel->failure_reason);
    }

    public function test_the_job_runs_queued_intel(): void
    {
        $intel = $this->service()->start($this->application(), AiSystem::factory()->create(), IntelStartMode::Background);
        $this->bindAgent('Done.');

        (new RunCompanyIntelJob($intel->id))->handle($this->service());

        $this->assertSame(RoleIntelStatus::Ready, $intel->fresh()->status);
    }

    public function test_a_job_failure_marks_the_intel_failed(): void
    {
        $intel = $this->service()->start($this->application(), AiSystem::factory()->create(), IntelStartMode::Background);

        (new RunCompanyIntelJob($intel->id))->failed(new TimeoutExceededException('took too long'));

        $intel->refresh();
        $this->assertSame(RoleIntelStatus::Failed, $intel->status);
        $this->assertSame('Research timed out.', $intel->failure_reason);
    }

    public function test_marking_applied_with_a_default_queues_intel(): void
    {
        $this->makeDefault();
        $application = $this->application();

        app(ApplicationService::class)->markApplied($application);

        $intel = $application->fresh()->roleIntel;
        $this->assertNotNull($intel);
        $this->assertSame(RoleIntelStatus::Queued, $intel->status);
        Queue::assertPushed(RunCompanyIntelJob::class);
    }

    public function test_marking_applied_without_a_default_starts_nothing(): void
    {
        $application = $this->application();

        app(ApplicationService::class)->markApplied($application);

        $this->assertSame(ApplicationStatus::Applied, $application->fresh()->status);
        $this->assertNull($application->fresh()->roleIntel);
        Queue::assertNothingPushed();
    }

    public function test_marking_applied_with_existing_intel_starts_nothing_more(): void
    {
        $this->makeDefault();
        $application = $this->application();
        $existing = RoleIntel::factory()->create(['application_id' => $application->id]);

        app(ApplicationService::class)->markApplied($application);

        $this->assertSame([$existing->id], RoleIntel::query()->where('application_id', $application->id)->pluck('id')->all());
        Queue::assertNothingPushed();
    }

    public function test_i_applied_queues_intel(): void
    {
        $this->makeDefault();
        ResumeVersion::query()->update(['is_current' => false]);
        ResumeVersion::factory()->create(['is_current' => true]);

        $response = $this->postJson(route('admin.resume.applications.store'), [
            'intent' => 'applied',
            'job_description' => 'Build things.',
            'company_name' => 'Intel Applied Co',
        ])->assertOk();

        $this->assertNotNull(Application::findOrFail($response->json('application_id'))->roleIntel);
        Queue::assertPushed(RunCompanyIntelJob::class);
    }

    public function test_an_automatic_start_failure_does_not_undo_marking_applied(): void
    {
        $systems = Mockery::mock(CompanyIntelSystems::class);
        $systems->shouldReceive('default')->andThrow(new RuntimeException('boom'));
        $this->app->instance(CompanyIntelSystems::class, $systems);
        $application = $this->application();

        $this->postJson(route('admin.resume.applications.apply', $application), [])
            ->assertSuccessful();

        $this->assertSame(ApplicationStatus::Applied, $application->fresh()->status);
        $this->assertNull($application->fresh()->roleIntel);
    }
}
