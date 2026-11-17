<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Enums\FollowUpStatus;
use App\Jobs\DraftApplicationFollowUpJob;
use App\Mail\ApplicationFollowUpMail;
use App\Mail\FollowUpDraftedMail;
use App\Models\Application;
use App\Models\ApplicationStatusUpdate;
use App\Models\User;
use App\Services\ApplicationFollowUpService;
use BSPDX\Keystone\Models\KeystonePermission as Permission;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Jvjvjv\CodeTalker\Enums\AiSystemRole;
use Jvjvjv\CodeTalker\Models\AiSystem;
use Jvjvjv\CodeTalker\Models\AiSystemFeatureDefault;
use Jvjvjv\CodeTalker\Services\LaravelAi\AgentFactory;
use Jvjvjv\CodeTalker\Services\LaravelAi\CodeTalkerAgent;
use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\Usage;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class ApplicationFollowUpDraftTest extends TestCase
{
    use DatabaseTransactions;

    private const string REPLY = "Subject: Checking in on the Staff Engineer role\n\nHi Pat,\n\nI wanted to follow up on my application.\n\nBest,\nJason Vertucio";

    private AiSystem $system;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-06-01 09:00:00'));
        config(['resume.follow_ups.notification_email' => 'owner@example.test']);
        Mail::fake();

        AiSystemFeatureDefault::query()->where('feature', ApplicationFollowUpService::FEATURE)->delete();
        $this->system = AiSystem::factory()->create(['is_active' => true]);
        $this->system->featureDefaults()->create(['feature' => ApplicationFollowUpService::FEATURE]);
    }

    public function test_a_draft_is_stored_pending_and_no_mail_goes_to_contacts(): void
    {
        $application = $this->dueApplication();
        $this->agentReplies(self::REPLY);

        $followUp = app(ApplicationFollowUpService::class)->draft($application);

        $this->assertNotNull($followUp);
        $followUp->refresh();
        $this->assertSame(FollowUpStatus::Pending, $followUp->status);
        $this->assertSame('Checking in on the Staff Engineer role', $followUp->subject);
        $this->assertSame("Hi Pat,\n\nI wanted to follow up on my application.\n\nBest,\nJason Vertucio", $followUp->body);
        $this->assertSame($this->system->id, $followUp->ai_system_id);
        $this->assertSame('2026-06-01 09:00:00', $followUp->drafted_at->toDateTimeString());
        $this->assertNull($followUp->recipients);

        Mail::assertNotSent(ApplicationFollowUpMail::class);
        Mail::assertNotQueued(ApplicationFollowUpMail::class);
    }

    public function test_the_owner_is_notified_without_contact_addresses(): void
    {
        $application = $this->dueApplication();
        $this->agentReplies(self::REPLY);

        app(ApplicationFollowUpService::class)->draft($application);

        Mail::assertQueued(FollowUpDraftedMail::class, function (FollowUpDraftedMail $mail) use ($application): bool {
            $html = $mail->render();

            return $mail->hasTo('owner@example.test')
                && count($mail->to) === 1
                && $mail->cc === [] && $mail->bcc === []
                && str_contains($html, 'Acme')
                && str_contains($html, 'Staff Engineer')
                && str_contains($html, route('admin.employment.applications.show', $application))
                && ! str_contains($html, 'pat@example.com')
                && ! str_contains($html, 'I wanted to follow up');
        });
        Mail::assertQueuedCount(1);
    }

    public function test_the_drafting_message_holds_no_contact_email(): void
    {
        $application = $this->dueApplication();
        $captured = null;
        $this->agentReplies(self::REPLY, function (string $message) use (&$captured): void {
            $captured = $message;
        });

        app(ApplicationFollowUpService::class)->draft($application);

        $this->assertNotNull($captured);
        $this->assertStringNotContainsString('pat@example.com', $captured);
        $this->assertStringContainsString('Pat Lee (Recruiter)', $captured);
        $this->assertStringContainsString('Acme', $captured);
        $this->assertStringContainsString('Staff Engineer', $captured);
        $this->assertStringContainsString('2026-05-25: applied', $captured);
    }

    public function test_the_instructions_are_the_prompt_followed_by_the_output_format(): void
    {
        $instructions = ApplicationFollowUpService::instructions('Be brief.');

        $this->assertStringStartsWith('Be brief.', $instructions);
        $this->assertStringContainsString('Subject: <the subject line>', $instructions);
        $this->assertStringStartsWith(trim(ApplicationFollowUpService::buildFollowUpPrompt()), ApplicationFollowUpService::instructions('   '));
    }

    public function test_no_draft_without_a_follow_up_system_and_the_reason_is_logged(): void
    {
        AiSystemFeatureDefault::query()->where('feature', ApplicationFollowUpService::FEATURE)->delete();
        $application = $this->dueApplication();
        $this->agentNeverCalled();
        Log::spy();

        $this->assertNull(app(ApplicationFollowUpService::class)->draft($application));

        $this->assertSame(0, $application->followUps()->count());
        Log::shouldHaveReceived('warning')->withArgs(fn (string $message, array $context): bool => str_contains($message, 'no active chat AI system')
            && $context['application_id'] === $application->id);
        Mail::assertNothingQueued();
    }

    public function test_an_inactive_or_voice_default_is_not_used(): void
    {
        $this->system->update(['is_active' => false]);
        $this->assertNull(app(ApplicationFollowUpService::class)->system());

        AiSystemFeatureDefault::query()->where('feature', ApplicationFollowUpService::FEATURE)->delete();
        AiSystem::factory()->transcription()->create()->featureDefaults()->create(['feature' => ApplicationFollowUpService::FEATURE]);

        $this->assertNull(app(ApplicationFollowUpService::class)->system());
        $this->assertSame(AiSystemRole::Transcription, AiSystem::defaultForFeature(ApplicationFollowUpService::FEATURE)?->role);
    }

    public function test_an_ai_failure_stores_no_draft_and_leaves_the_application_due(): void
    {
        $application = $this->dueApplication();
        $agent = Mockery::mock(CodeTalkerAgent::class);
        $agent->shouldReceive('prompt')->once()->andThrow(new RuntimeException('Model unavailable'));
        $this->bindAgent($agent);

        $this->assertNull(app(ApplicationFollowUpService::class)->draft($application));

        $this->assertSame(0, $application->followUps()->count());
        $this->assertTrue(app(ApplicationFollowUpService::class)->isDue($application));
        Mail::assertNothingQueued();
    }

    public function test_an_empty_reply_stores_no_draft(): void
    {
        $application = $this->dueApplication();
        $this->agentReplies("Subject: Hello\n\n   ");

        $this->assertNull(app(ApplicationFollowUpService::class)->draft($application));
        $this->assertSame(0, $application->followUps()->count());
    }

    public function test_a_reply_without_a_subject_gets_the_default_subject(): void
    {
        $application = $this->dueApplication();
        $this->agentReplies("Hi Pat,\n\nJust checking in.\n\nJason");

        $followUp = app(ApplicationFollowUpService::class)->draft($application);

        $this->assertSame('Following up: Staff Engineer at Acme', $followUp->subject);
        $this->assertSame("Hi Pat,\n\nJust checking in.\n\nJason", $followUp->body);
    }

    public function test_the_default_subject_skips_unknown_placeholders(): void
    {
        $application = Application::factory()->make([
            'company_name' => Application::UNKNOWN_COMPANY,
            'position' => 'Staff Engineer',
        ]);

        $this->assertSame('Following up: Staff Engineer', ApplicationFollowUpService::defaultSubject($application));

        $application->position = Application::UNKNOWN_POSITION;
        $this->assertSame('Following up on my application', ApplicationFollowUpService::defaultSubject($application));
    }

    public function test_a_bold_subject_line_is_parsed(): void
    {
        $application = $this->dueApplication();

        $parsed = app(ApplicationFollowUpService::class)->parseDraft("**Subject:** Quick check-in\n\nHello,\n\nThanks.", $application);

        $this->assertSame(['subject' => 'Quick check-in', 'body' => "Hello,\n\nThanks."], $parsed);
    }

    public function test_an_application_that_stopped_being_due_mid_draft_is_not_stored(): void
    {
        $application = $this->dueApplication();
        $this->agentReplies(self::REPLY, function () use ($application): void {
            $application->newQuery()->whereKey($application->id)->update(['follow_ups_enabled' => false]);
        });

        $this->assertNull(app(ApplicationFollowUpService::class)->draft($application));

        $this->assertSame(0, $application->followUps()->count());
        Mail::assertNothingQueued();
    }

    public function test_the_job_drafts_for_its_application(): void
    {
        $application = $this->dueApplication();
        $this->agentReplies(self::REPLY);

        DraftApplicationFollowUpJob::dispatchSync($application->id);

        $this->assertSame(FollowUpStatus::Pending, $application->pendingFollowUp()->first()?->status);
    }

    public function test_the_command_queues_a_job_for_each_due_application_only(): void
    {
        Queue::fake();
        $due = $this->dueApplication();
        $notDue = Application::factory()->followUpsEnabled()->withContact()->create(['status' => ApplicationStatus::Applied]);
        $this->entry($notDue, now()->subDays(3));
        $switchedOff = Application::factory()->withContact()->create(['status' => ApplicationStatus::Applied]);
        $this->entry($switchedOff, now()->subDays(10));

        $this->artisan('applications:draft-follow-ups')->assertSuccessful();

        Queue::assertPushed(DraftApplicationFollowUpJob::class, fn (DraftApplicationFollowUpJob $job): bool => $job->applicationId === $due->id);
        Queue::assertNotPushed(DraftApplicationFollowUpJob::class, fn (DraftApplicationFollowUpJob $job): bool => in_array($job->applicationId, [$notDue->id, $switchedOff->id], true));
    }

    public function test_the_command_dry_run_queues_nothing(): void
    {
        Queue::fake();
        $this->dueApplication();

        $this->artisan('applications:draft-follow-ups', ['--dry-run' => true])->assertSuccessful();

        Queue::assertNothingPushed();
    }

    public function test_the_discussion_page_says_when_no_follow_up_system_is_configured(): void
    {
        $admin = User::factory()->create();
        Permission::firstOrCreate(['name' => 'edit-resume']);
        $admin->givePermissionTo('edit-resume');
        $application = $this->dueApplication();

        $this->actingAs($admin)
            ->get(route('admin.employment.applications.show', $application))
            ->assertInertia(fn (Assert $page) => $page->where('followUps.follow_up_system_configured', true));

        AiSystemFeatureDefault::query()->where('feature', ApplicationFollowUpService::FEATURE)->delete();

        $this->actingAs($admin)
            ->get(route('admin.employment.applications.show', $application))
            ->assertInertia(fn (Assert $page) => $page->where('followUps.follow_up_system_configured', false));
    }

    private function dueApplication(): Application
    {
        $application = Application::factory()
            ->followUpsEnabled()
            ->withContact(['email' => 'pat@example.com', 'name' => 'Pat Lee', 'role' => 'Recruiter'])
            ->create([
                'status' => ApplicationStatus::Applied,
                'company_name' => 'Acme',
                'position' => 'Staff Engineer',
            ]);

        $this->entry($application, now()->subDays(7));

        return $application;
    }

    private function entry(Application $application, Carbon $occurredAt): void
    {
        ApplicationStatusUpdate::factory()->create([
            'application_id' => $application->id,
            'status' => ApplicationStatus::Applied,
            'notes' => null,
            'occurred_at' => $occurredAt,
            'created_at' => $occurredAt,
        ]);
    }

    /**
     * @param  (callable(string): void)|null  $onPrompt
     */
    private function agentReplies(string $text, ?callable $onPrompt = null): void
    {
        $agent = Mockery::mock(CodeTalkerAgent::class);
        $agent->shouldReceive('prompt')->once()->andReturnUsing(function (string $message) use ($text, $onPrompt): AgentResponse {
            if ($onPrompt !== null) {
                $onPrompt($message);
            }

            return new AgentResponse(
                'id-1',
                $text,
                new Usage(promptTokens: 100, completionTokens: 50),
                new Meta(provider: 'anthropic', model: 'claude-sonnet-4-6'),
            );
        });

        $this->bindAgent($agent);
    }

    private function agentNeverCalled(): void
    {
        $factory = Mockery::mock(AgentFactory::class);
        $factory->shouldReceive('forSystem')->never();
        $this->app->instance(AgentFactory::class, $factory);
    }

    private function bindAgent(CodeTalkerAgent $agent): void
    {
        $factory = Mockery::mock(AgentFactory::class);
        $factory->shouldReceive('forSystem')
            ->once()
            ->withArgs(fn (AiSystem $system, string $instructions = ''): bool => $system->is($this->system) && str_contains($instructions, 'Output format'))
            ->andReturn($agent);

        $this->app->instance(AgentFactory::class, $factory);
    }
}
