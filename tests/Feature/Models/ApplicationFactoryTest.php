<?php

namespace Tests\Feature\Models;

use App\Enums\ApplicationStatus;
use App\Enums\FollowUpDiscardReason;
use App\Enums\FollowUpStatus;
use App\Models\AiConversation;
use App\Models\Application;
use App\Models\ApplicationFollowUp;
use App\Models\ApplicationStatusUpdate;
use App\Models\CoverLetter;
use App\Models\JobUrl;
use App\Models\ResumeVersion;
use App\Models\TargetedResume;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ApplicationFactoryTest extends TestCase
{
    use DatabaseTransactions;

    public function test_default_state_is_a_draft_with_no_targeted_resume_and_no_conversation(): void
    {
        $application = Application::factory()->create()->fresh();

        $this->assertSame(ApplicationStatus::Draft, $application->status);
        $this->assertNull($application->targeted_resume_id);
        $this->assertNull($application->ai_conversation_id);
        $this->assertNull($application->job_url_id);
        $this->assertNull($application->targetedResume);
        $this->assertNull($application->conversation);
        $this->assertNotNull($application->resume_version_id);
        $this->assertNotSame('', $application->company_name);
        $this->assertNotSame('', $application->position);
        $this->assertNotSame('', $application->job_description);
        $this->assertSame(0, $application->statusUpdates()->count());
        $this->assertNull($application->deleted_at);
    }

    public function test_status_defaults_to_draft_when_not_given(): void
    {
        $application = Application::create([
            'resume_version_id' => ResumeVersion::factory()->create()->id,
            'company_name' => 'Acme Corp',
            'position' => 'Engineer',
            'job_description' => 'Build things.',
        ]);

        $this->assertSame(ApplicationStatus::Draft, $application->status);
        $this->assertSame('draft', $application->fresh()->getRawOriginal('status'));
    }

    public function test_applied_state_sets_the_status_and_records_an_applied_history_entry(): void
    {
        $application = Application::factory()->applied()->create()->fresh();

        $this->assertSame(ApplicationStatus::Applied, $application->status);
        $this->assertCount(1, $application->statusUpdates);
        $this->assertSame(ApplicationStatus::Applied, $application->statusUpdates->first()->status);
        $this->assertTrue($application->hasBeenApplied());
        $this->assertNull($application->targeted_resume_id);
        $this->assertNull($application->ai_conversation_id);
    }

    public function test_applied_state_dates_the_entry_when_given_a_date(): void
    {
        $application = Application::factory()->applied(Carbon::parse('2026-02-03 09:30:00'))->create();

        $this->assertSame('2026-02-03 09:30:00', $application->statusUpdates()->sole()->occurred_at->toDateTimeString());
    }

    public function test_passed_state_sets_the_status_without_history(): void
    {
        $application = Application::factory()->passed()->create()->fresh();

        $this->assertSame(ApplicationStatus::Passed, $application->status);
        $this->assertSame(0, $application->statusUpdates()->count());
        $this->assertFalse($application->hasBeenApplied());
    }

    public function test_with_targeted_resume_state_attaches_a_document_tailored_from_the_same_resume_version(): void
    {
        $application = Application::factory()->withTargetedResume()->create()->fresh();

        $this->assertInstanceOf(TargetedResume::class, $application->targetedResume);
        $this->assertNotNull($application->targetedResume->tailored_data);
        $this->assertSame($application->resume_version_id, $application->targetedResume->resume_version_id);
        $this->assertSame(ApplicationStatus::Draft, $application->status);
        $this->assertNull($application->ai_conversation_id);
    }

    public function test_with_conversation_state_attaches_a_targeted_resume_session(): void
    {
        $application = Application::factory()->withConversation()->create()->fresh();

        $this->assertInstanceOf(AiConversation::class, $application->conversation);
        $this->assertSame('targeted-resume', $application->conversation->feature);
        $this->assertNull($application->targeted_resume_id);
        $this->assertSame(ApplicationStatus::Draft, $application->status);
    }

    public function test_states_compose(): void
    {
        $application = Application::factory()->withTargetedResume()->withConversation()->applied()->create()->fresh();

        $this->assertNotNull($application->targeted_resume_id);
        $this->assertNotNull($application->ai_conversation_id);
        $this->assertSame(ApplicationStatus::Applied, $application->status);
        $this->assertSame(1, $application->statusUpdates()->count());
    }

    public function test_resume_version_and_job_url_relations_resolve(): void
    {
        $version = ResumeVersion::factory()->create();
        $jobUrl = JobUrl::factory()->create();
        $application = Application::factory()->create([
            'resume_version_id' => $version->id,
            'job_url_id' => $jobUrl->id,
        ])->fresh();

        $this->assertTrue($application->resumeVersion->is($version));
        $this->assertTrue($application->jobUrl->is($jobUrl));
        $this->assertTrue($jobUrl->applications->contains($application));
        $this->assertTrue($version->applications->contains($application));
    }

    public function test_cover_letters_relation_resolves(): void
    {
        $application = Application::factory()->create();
        $other = Application::factory()->create();

        $letter = $this->coverLetter($application);
        $this->coverLetter($other);
        $this->coverLetter(null);

        $this->assertSame([$letter->id], $application->coverLetters()->pluck('id')->all());
        $this->assertTrue($letter->application->is($application));
    }

    public function test_status_updates_are_ordered_by_occurred_at_and_latest_is_the_most_recent(): void
    {
        $application = Application::factory()->create();

        $third = $this->statusUpdate($application, ApplicationStatus::Interviewed, '2026-03-20 10:00:00');
        $first = $this->statusUpdate($application, ApplicationStatus::Applied, '2026-03-01 10:00:00');
        $second = $this->statusUpdate($application, ApplicationStatus::Interviewing, '2026-03-10 10:00:00');
        $this->statusUpdate(Application::factory()->create(), ApplicationStatus::Offered, '2026-04-01 10:00:00');

        $application = $application->fresh();

        $this->assertSame([$first->id, $second->id, $third->id], $application->statusUpdates->pluck('id')->all());
        $this->assertTrue($application->latestStatusUpdate->is($third));
        $this->assertTrue($first->application->is($application));
    }

    public function test_latest_status_update_is_null_without_history(): void
    {
        $this->assertNull(Application::factory()->create()->latestStatusUpdate);
    }

    public function test_targeted_resume_reaches_its_application(): void
    {
        $application = Application::factory()->withTargetedResume()->create();
        Application::factory()->withTargetedResume()->create();

        $this->assertTrue($application->targetedResume->application->is($application));
        $this->assertNull(TargetedResume::factory()->create()->application);
    }

    public function test_conversation_reaches_its_application_and_its_targeted_resume_through_it(): void
    {
        $application = Application::factory()->withTargetedResume()->withConversation()->create();
        Application::factory()->withTargetedResume()->withConversation()->create();
        $withoutDocument = Application::factory()->withConversation()->create();

        $conversation = AiConversation::query()->findOrFail($application->ai_conversation_id);

        $this->assertTrue($conversation->application->is($application));
        $this->assertInstanceOf(TargetedResume::class, $conversation->targetedResume);
        $this->assertSame($application->targeted_resume_id, $conversation->targetedResume->id);

        $eagerLoaded = AiConversation::query()
            ->with('targetedResume')
            ->whereKey([$application->ai_conversation_id, $withoutDocument->ai_conversation_id])
            ->get()
            ->keyBy('id');

        $this->assertSame($application->targeted_resume_id, $eagerLoaded[$application->ai_conversation_id]->targetedResume->id);
        $this->assertNull($eagerLoaded[$withoutDocument->ai_conversation_id]->targetedResume);
    }

    public function test_for_conversation_finds_the_application_of_a_session(): void
    {
        $application = Application::factory()->withConversation()->create();
        $orphan = AiConversation::factory()->create();

        $this->assertTrue(Application::forConversation($application->conversation)->is($application));
        $this->assertNull(Application::forConversation($orphan));
    }

    public function test_soft_delete_hides_the_application_from_default_queries(): void
    {
        $application = Application::factory()->create();

        $application->delete();

        $this->assertNull(Application::query()->find($application->id));
        $this->assertFalse(Application::query()->whereKey($application->id)->exists());
        $this->assertNotNull(Application::withTrashed()->find($application->id)?->deleted_at);
        $this->assertSoftDeleted('applications', ['id' => $application->id]);
    }

    public function test_a_targeted_resume_cannot_belong_to_two_applications(): void
    {
        $application = Application::factory()->withTargetedResume()->create();

        $this->expectException(QueryException::class);

        Application::factory()->create(['targeted_resume_id' => $application->targeted_resume_id]);
    }

    public function test_a_conversation_cannot_belong_to_two_applications(): void
    {
        $application = Application::factory()->withConversation()->create();

        $this->expectException(QueryException::class);

        Application::factory()->create(['ai_conversation_id' => $application->ai_conversation_id]);
    }

    public function test_many_applications_may_have_neither_a_targeted_resume_nor_a_conversation(): void
    {
        $applications = Application::factory()->count(3)->create();

        $this->assertCount(3, $applications);
        $this->assertSame(3, Application::query()->whereKey($applications->pluck('id'))->whereNull('targeted_resume_id')->whereNull('ai_conversation_id')->count());
    }

    public function test_follow_ups_are_off_by_default_and_the_follow_ups_enabled_state_turns_them_on(): void
    {
        $this->assertFalse(Application::factory()->create()->fresh()->follow_ups_enabled);
        $this->assertTrue(Application::factory()->followUpsEnabled()->create()->fresh()->follow_ups_enabled);
    }

    public function test_with_contact_state_adds_a_contact_with_a_lower_cased_email(): void
    {
        $application = Application::factory()->withContact(['email' => 'Pat@Example.COM', 'name' => 'Pat Lee'])->create();

        $contact = $application->contacts()->sole();

        $this->assertSame('pat@example.com', $contact->email);
        $this->assertSame('Pat Lee', $contact->name);
        $this->assertTrue($contact->application->is($application));
    }

    public function test_with_contact_state_composes_for_several_contacts(): void
    {
        $application = Application::factory()->withContact()->withContact()->create();

        $this->assertSame(2, $application->contacts()->count());
    }

    public function test_follow_up_factory_states_persist_their_status(): void
    {
        $application = Application::factory()->create();
        $sentAt = Carbon::parse('2026-03-01 10:00:00');

        $pending = ApplicationFollowUp::factory()->pending()->create(['application_id' => $application->id])->fresh();
        $sent = ApplicationFollowUp::factory()->sent($sentAt, ['pat@example.com'])->create(['application_id' => $application->id])->fresh();
        $failed = ApplicationFollowUp::factory()->failed('Timed out.')->create(['application_id' => $application->id])->fresh();
        $discarded = ApplicationFollowUp::factory()->discarded(FollowUpDiscardReason::Superseded)->create(['application_id' => $application->id])->fresh();

        $this->assertSame(FollowUpStatus::Pending, $pending->status);
        $this->assertNull($pending->recipients);
        $this->assertNotNull($pending->drafted_at);

        $this->assertSame(FollowUpStatus::Sent, $sent->status);
        $this->assertSame(['pat@example.com'], $sent->recipients);
        $this->assertSame('2026-03-01 10:00:00', $sent->sent_at->toDateTimeString());

        $this->assertSame(FollowUpStatus::Failed, $failed->status);
        $this->assertSame('Timed out.', $failed->failure_reason);

        $this->assertSame(FollowUpStatus::Discarded, $discarded->status);
        $this->assertSame(FollowUpDiscardReason::Superseded, $discarded->discard_reason);
        $this->assertNotNull($discarded->discarded_at);

        $this->assertSame([$pending->id, $sent->id, $failed->id, $discarded->id], $application->followUps()->pluck('id')->all());
        $this->assertTrue($application->pendingFollowUp->is($pending));
    }

    public function test_pending_follow_up_is_null_without_a_pending_draft(): void
    {
        $application = Application::factory()->create();
        ApplicationFollowUp::factory()->sent()->create(['application_id' => $application->id]);

        $this->assertNull($application->pendingFollowUp);
    }

    public function test_contacts_and_follow_ups_are_deleted_with_a_hard_deleted_application(): void
    {
        $application = Application::factory()->withContact()->create();
        ApplicationFollowUp::factory()->create(['application_id' => $application->id]);

        $application->forceDelete();

        $this->assertDatabaseMissing('application_contacts', ['application_id' => $application->id]);
        $this->assertDatabaseMissing('application_follow_ups', ['application_id' => $application->id]);
    }

    private function statusUpdate(Application $application, ApplicationStatus $status, string $occurredAt): ApplicationStatusUpdate
    {
        return ApplicationStatusUpdate::factory()->create([
            'application_id' => $application->id,
            'status' => $status,
            'occurred_at' => Carbon::parse($occurredAt),
        ]);
    }

    private function coverLetter(?Application $application): CoverLetter
    {
        return CoverLetter::create([
            'resume_version_id' => $application?->resume_version_id ?? ResumeVersion::factory()->create()->id,
            'application_id' => $application?->id,
            'company_name' => 'Acme Corp',
            'position' => 'Engineer',
            'date' => '2026-03-01',
            'greeting' => 'Hello,',
            'message_body' => 'Body.',
        ]);
    }
}
