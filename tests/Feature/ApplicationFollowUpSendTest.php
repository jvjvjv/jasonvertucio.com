<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Enums\FollowUpDiscardReason;
use App\Enums\FollowUpStatus;
use App\Jobs\SendApplicationFollowUpJob;
use App\Mail\ApplicationFollowUpMail;
use App\Models\Application;
use App\Models\ApplicationFollowUp;
use App\Models\ApplicationStatusUpdate;
use App\Models\User;
use App\Services\ApplicationFollowUpService;
use BSPDX\Keystone\Models\KeystonePermission as Permission;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Mail\MailManager;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Testing\Fakes\MailFake;
use Inertia\Testing\AssertableInertia as Assert;
use RuntimeException;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\TestCase;

class ApplicationFollowUpSendTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-06-01 09:00:00'));
        config([
            'resume.follow_ups.notification_email' => 'owner@example.test',
            'resume.follow_ups.max_per_status' => 3,
            'mail.from.address' => 'me@example.test',
            'mail.from.name' => 'Jason Vertucio',
            'mail.cc.address' => null,
        ]);

        $this->admin = User::factory()->create();
        Permission::firstOrCreate(['name' => 'edit-resume']);
        $this->admin->givePermissionTo('edit-resume');
    }

    public function test_editing_a_draft_keeps_it_pending(): void
    {
        [$application, $followUp] = $this->pendingDraft();

        $this->actingAs($this->admin)
            ->putJson($this->url('update', $application, $followUp), ['subject' => 'New subject', 'body' => 'New body.'])
            ->assertOk()
            ->assertJsonPath('pending_follow_up.subject', 'New subject')
            ->assertJsonPath('pending_follow_up.body', 'New body.')
            ->assertJsonPath('pending_follow_up.status', 'pending');

        $followUp->refresh();
        $this->assertSame(FollowUpStatus::Pending, $followUp->status);
        $this->assertSame('New body.', $followUp->body);
    }

    public function test_editing_needs_a_subject_and_a_body(): void
    {
        [$application, $followUp] = $this->pendingDraft();

        $this->actingAs($this->admin)
            ->putJson($this->url('update', $application, $followUp), ['subject' => '', 'body' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['subject' => 'A follow-up needs a subject.', 'body' => 'A follow-up needs a body.']);

        $this->actingAs($this->admin)
            ->putJson($this->url('update', $application, $followUp), ['subject' => str_repeat('x', 192), 'body' => 'Body'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('subject');
    }

    public function test_approving_sends_one_mail_to_every_contact_with_the_owner_blind_copied(): void
    {
        Mail::fake();
        [$application, $followUp] = $this->pendingDraft();

        $this->actingAs($this->admin)
            ->postJson($this->url('approve', $application, $followUp))
            ->assertOk()
            ->assertJsonPath('pending_follow_up', null)
            ->assertJsonPath('follow_up_history.0.id', $followUp->id)
            ->assertJsonPath('follow_up_history.0.status', 'sent')
            ->assertJsonPath('follow_up_history.0.recipients', ['pat@example.com', 'sam@example.com'])
            ->assertJsonPath('sent_since_status_change', 1);

        Mail::assertSent(ApplicationFollowUpMail::class, 1);
        Mail::assertSent(ApplicationFollowUpMail::class, function (ApplicationFollowUpMail $mail): bool {
            return $mail->hasTo('pat@example.com')
                && $mail->hasTo('sam@example.com')
                && $mail->hasBcc('owner@example.test')
                && $mail->hasFrom('me@example.test', 'Jason Vertucio')
                && $mail->hasSubject('Checking in')
                && str_contains($mail->render(), 'Hi Pat &amp; Sam,<br>')
                && ! str_contains($mail->render(), '<x-mail');
        });

        $followUp->refresh();
        $this->assertSame(FollowUpStatus::Sent, $followUp->status);
        $this->assertSame(['pat@example.com', 'sam@example.com'], $followUp->recipients);
        $this->assertSame('2026-06-01 09:00:00', $followUp->sent_at->toDateTimeString());
    }

    public function test_the_configured_cc_address_is_openly_copied_and_the_owner_still_blind_copied(): void
    {
        Mail::fake();
        config(['mail.from.address' => 'noreply@example.test', 'mail.cc.address' => ' Jason@Example.test ']);
        [$application, $followUp] = $this->pendingDraft();

        $this->actingAs($this->admin)
            ->postJson($this->url('approve', $application, $followUp))
            ->assertOk();

        Mail::assertSent(ApplicationFollowUpMail::class, function (ApplicationFollowUpMail $mail): bool {
            return $mail->hasFrom('noreply@example.test', 'Jason Vertucio')
                && $mail->hasTo('pat@example.com')
                && $mail->hasCc('jason@example.test')
                && $mail->hasBcc('owner@example.test');
        });
    }

    public function test_the_owner_is_not_blind_copied_when_already_openly_copied(): void
    {
        Mail::fake();
        config(['mail.cc.address' => 'owner@example.test']);
        [$application, $followUp] = $this->pendingDraft();

        $this->actingAs($this->admin)
            ->postJson($this->url('approve', $application, $followUp))
            ->assertOk();

        Mail::assertSent(ApplicationFollowUpMail::class, function (ApplicationFollowUpMail $mail): bool {
            return $mail->hasCc('owner@example.test')
                && ! $mail->hasBcc('owner@example.test')
                && $mail->envelope()->bcc === [];
        });
    }

    public function test_nothing_is_openly_copied_when_no_cc_address_is_configured(): void
    {
        Mail::fake();
        [$application, $followUp] = $this->pendingDraft();

        $this->actingAs($this->admin)
            ->postJson($this->url('approve', $application, $followUp))
            ->assertOk();

        Mail::assertSent(ApplicationFollowUpMail::class, fn (ApplicationFollowUpMail $mail): bool => $mail->envelope()->cc === []);
    }

    public function test_the_text_part_is_the_body_verbatim(): void
    {
        [, $followUp] = $this->pendingDraft();
        $followUp->update(['recipients' => ['pat@example.com']]);

        $text = (new ApplicationFollowUpMail($followUp))->render();
        $plain = view('mail.follow-up.text', ['body' => $followUp->body])->render();

        $this->assertStringContainsString('Hi Pat &amp; Sam,<br>', $text);
        $this->assertSame("Hi Pat & Sam,\nThanks for your time.\n", $plain);
    }

    public function test_approve_queues_the_send_off_the_request_and_claims_the_follow_up(): void
    {
        Queue::fake();
        [$application, $followUp] = $this->pendingDraft();

        $this->actingAs($this->admin)
            ->postJson($this->url('approve', $application, $followUp))
            ->assertOk()
            ->assertJsonPath('follow_up_history.0.status', 'sending');

        Queue::assertPushed(SendApplicationFollowUpJob::class, fn (SendApplicationFollowUpJob $job): bool => $job->followUpId === $followUp->id);
        $this->assertSame(FollowUpStatus::Sending, $followUp->fresh()->status);
    }

    public function test_a_second_approve_is_refused_and_sends_nothing_more(): void
    {
        Mail::fake();
        [$application, $followUp] = $this->pendingDraft();

        $this->actingAs($this->admin)->postJson($this->url('approve', $application, $followUp))->assertOk();
        $this->actingAs($this->admin)
            ->postJson($this->url('approve', $application, $followUp))
            ->assertConflict()
            ->assertJsonPath('message', 'This follow-up is no longer waiting for review.');

        Mail::assertSent(ApplicationFollowUpMail::class, 1);
    }

    public function test_approving_while_sending_is_refused(): void
    {
        Queue::fake();
        [$application, $followUp] = $this->pendingDraft();
        $followUp->update(['status' => FollowUpStatus::Sending]);

        $this->actingAs($this->admin)->postJson($this->url('approve', $application, $followUp))->assertConflict();

        Queue::assertNothingPushed();
    }

    public function test_approving_with_no_contacts_is_refused_and_the_draft_stays_pending(): void
    {
        Mail::fake();
        [$application, $followUp] = $this->pendingDraft();
        $application->contacts()->delete();

        $this->actingAs($this->admin)
            ->postJson($this->url('approve', $application, $followUp))
            ->assertConflict()
            ->assertJsonPath('message', 'Add a contact before sending this follow-up.');

        $this->assertSame(FollowUpStatus::Pending, $followUp->fresh()->status);
        $this->assertNull($followUp->fresh()->recipients);
        Mail::assertNothingSent();
    }

    public function test_a_transport_failure_marks_it_failed_and_approving_again_retries(): void
    {
        [$application, $followUp] = $this->pendingDraft();

        Mail::shouldReceive('send')->once()->andThrow(new TransportException('Connection to smtp refused.'));

        $this->actingAs($this->admin)
            ->postJson($this->url('approve', $application, $followUp))
            ->assertOk()
            ->assertJsonPath('follow_up_history.0.status', 'failed')
            ->assertJsonPath('follow_up_history.0.failure_reason', 'Connection to smtp refused.');

        $this->assertSame(FollowUpStatus::Failed, $followUp->fresh()->status);

        Mail::swap(new MailFake(new MailManager($this->app)));

        $this->actingAs($this->admin)
            ->postJson($this->url('approve', $application, $followUp))
            ->assertOk()
            ->assertJsonPath('follow_up_history.0.status', 'sent')
            ->assertJsonPath('follow_up_history.0.failure_reason', null);

        Mail::assertSent(ApplicationFollowUpMail::class, 1);
        $this->assertSame(FollowUpStatus::Sent, $followUp->fresh()->status);
    }

    public function test_the_job_failing_outright_marks_the_follow_up_failed(): void
    {
        [, $followUp] = $this->pendingDraft();
        $followUp->update(['status' => FollowUpStatus::Sending, 'recipients' => ['pat@example.com']]);

        (new SendApplicationFollowUpJob($followUp->id))->failed(new RuntimeException('Worker died.'));

        $this->assertSame(FollowUpStatus::Failed, $followUp->fresh()->status);
        $this->assertSame('Worker died.', $followUp->fresh()->failure_reason);
    }

    public function test_the_job_sends_only_a_follow_up_still_claimed_as_sending(): void
    {
        Mail::fake();
        [, $followUp] = $this->pendingDraft();

        (new SendApplicationFollowUpJob($followUp->id))->handle(app(ApplicationFollowUpService::class));

        Mail::assertNothingSent();
        $this->assertSame(FollowUpStatus::Pending, $followUp->fresh()->status);
    }

    public function test_discarding_sends_nothing_and_the_application_can_become_due_again(): void
    {
        Mail::fake();
        [$application, $followUp] = $this->pendingDraft();

        $this->actingAs($this->admin)
            ->postJson($this->url('discard', $application, $followUp))
            ->assertOk()
            ->assertJsonPath('pending_follow_up', null);

        $followUp->refresh();
        $this->assertSame(FollowUpStatus::Discarded, $followUp->status);
        $this->assertSame(FollowUpDiscardReason::Manual, $followUp->discard_reason);
        $this->assertNotNull($followUp->discarded_at);
        Mail::assertNothingSent();
        Mail::assertNothingQueued();

        $this->assertTrue(app(ApplicationFollowUpService::class)->isDue($application));
    }

    public function test_a_follow_up_from_another_application_is_not_found(): void
    {
        Mail::fake();
        [, $followUp] = $this->pendingDraft();
        $other = Application::factory()->withContact()->create();

        $this->actingAs($this->admin)->putJson($this->url('update', $other, $followUp), ['subject' => 'S', 'body' => 'B'])->assertNotFound();
        $this->actingAs($this->admin)->postJson($this->url('approve', $other, $followUp))->assertNotFound();
        $this->actingAs($this->admin)->postJson($this->url('discard', $other, $followUp))->assertNotFound();

        $this->assertSame(FollowUpStatus::Pending, $followUp->fresh()->status);
        $this->assertSame('Checking in', $followUp->fresh()->subject);
        Mail::assertNothingSent();
    }

    public function test_sent_failed_and_discarded_follow_ups_cannot_be_edited_or_discarded(): void
    {
        [$application] = $this->pendingDraft();

        foreach (['sent', 'failed', 'discarded'] as $state) {
            $followUp = ApplicationFollowUp::factory()->{$state}()->create(['application_id' => $application->id, 'subject' => 'Original']);

            $this->actingAs($this->admin)
                ->putJson($this->url('update', $application, $followUp), ['subject' => 'Changed', 'body' => 'Changed'])
                ->assertConflict();
            $this->actingAs($this->admin)
                ->postJson($this->url('discard', $application, $followUp))
                ->assertConflict();

            $this->assertSame('Original', $followUp->fresh()->subject, $state);
        }
    }

    public function test_the_discussion_page_shows_the_cap(): void
    {
        $application = Application::factory()->followUpsEnabled()->withContact()->create(['status' => ApplicationStatus::Applied]);
        $this->entry($application, now()->subDays(30));

        foreach ([21, 14, 7] as $days) {
            ApplicationFollowUp::factory()->sent(now()->subDays($days))->create(['application_id' => $application->id]);
        }

        $this->actingAs($this->admin)
            ->get(route('admin.employment.applications.show', $application))
            ->assertInertia(fn (Assert $page) => $page
                ->where('followUps.sent_since_status_change', 3)
                ->where('followUps.max_per_status', 3)
                ->has('followUps.follow_up_history', 3));
    }

    /**
     * @return array{0: Application, 1: ApplicationFollowUp}
     */
    private function pendingDraft(): array
    {
        $application = Application::factory()
            ->followUpsEnabled()
            ->withContact(['email' => 'pat@example.com', 'name' => 'Pat'])
            ->withContact(['email' => 'sam@example.com', 'name' => 'Sam'])
            ->create(['status' => ApplicationStatus::Applied]);
        $this->entry($application, now()->subDays(8));

        $followUp = ApplicationFollowUp::factory()->pending()->create([
            'application_id' => $application->id,
            'subject' => 'Checking in',
            'body' => "Hi Pat & Sam,\nThanks for your time.",
        ]);

        return [$application, $followUp];
    }

    private function entry(Application $application, Carbon $occurredAt): void
    {
        ApplicationStatusUpdate::factory()->create([
            'application_id' => $application->id,
            'status' => ApplicationStatus::Applied,
            'occurred_at' => $occurredAt,
            'created_at' => $occurredAt,
        ]);
    }

    private function url(string $action, Application $application, ApplicationFollowUp $followUp): string
    {
        return route("admin.employment.applications.follow-ups.{$action}", [$application, $followUp]);
    }
}
