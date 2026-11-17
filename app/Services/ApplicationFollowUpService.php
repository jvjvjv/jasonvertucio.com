<?php

namespace App\Services;

use App\Enums\ApplicationStatus;
use App\Enums\FollowUpDiscardReason;
use App\Enums\FollowUpStatus;
use App\Exceptions\ApplicationContactMismatchException;
use App\Exceptions\ApplicationFollowUpMismatchException;
use App\Exceptions\FollowUpHasNoRecipientsException;
use App\Exceptions\FollowUpIncompleteException;
use App\Exceptions\FollowUpNotPendingException;
use App\Jobs\SendApplicationFollowUpJob;
use App\Mail\FollowUpDraftedMail;
use App\Models\Application;
use App\Models\ApplicationContact;
use App\Models\ApplicationFollowUp;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Jvjvjv\CodeTalker\Enums\AiSystemRole;
use Jvjvjv\CodeTalker\Models\AiSystem;
use Jvjvjv\CodeTalker\Models\AiSystemPrompt;
use Jvjvjv\CodeTalker\Services\LaravelAi\AgentFactory;
use Throwable;

/**
 * An application's contacts and the follow-up emails drafted to them: when
 * one is due, drafting it with the `application-follow-up` AI system,
 * approving and sending it, and discarding it when the facts change.
 * Nothing reaches a contact without an explicit approve.
 */
class ApplicationFollowUpService
{
    /**
     * The Application Follow-up prompt's fixed built-in id, after Company
     * Intel's ({@see CompanyIntel\CompanyIntelService::PROMPT_ID_COMPANY_INTEL}).
     * Ids up to 49 are reserved for built-in prompts.
     */
    public const int PROMPT_ID_FOLLOW_UP = 7;

    public const string FEATURE = 'application-follow-up';

    /**
     * How much of the job description the drafting model is shown.
     */
    private const int JOB_DESCRIPTION_LIMIT = 4000;

    /**
     * The subject column's length.
     */
    public const int SUBJECT_LIMIT = 191;

    public const int BODY_LIMIT = 10000;

    public function __construct(
        private AgentFactory $agentFactory,
    ) {}

    /**
     * @param  array{email: string, name?: ?string, role?: ?string, notes?: ?string}  $attributes
     */
    public function addContact(Application $application, array $attributes): ApplicationContact
    {
        $contact = $application->contacts()->create($attributes);

        $application->unsetRelation('contacts');

        return $contact;
    }

    /**
     * @param  array{email?: string, name?: ?string, role?: ?string, notes?: ?string}  $attributes
     *
     * @throws ApplicationContactMismatchException
     */
    public function updateContact(Application $application, ApplicationContact $contact, array $attributes): ApplicationContact
    {
        $this->assertContactBelongsTo($application, $contact);

        $contact->update($attributes);
        $application->unsetRelation('contacts');

        return $contact;
    }

    /**
     * Remove a contact. Follow-ups already sent keep it among their
     * recipients, which are a snapshot.
     *
     * @throws ApplicationContactMismatchException
     */
    public function deleteContact(Application $application, ApplicationContact $contact): void
    {
        $this->assertContactBelongsTo($application, $contact);

        $contact->delete();
        $application->unsetRelation('contacts');
    }

    /**
     * Discard the application's pending draft, if it has one. Called inside
     * the caller's transaction when a status entry is added or follow-ups are
     * switched off.
     */
    public function discardPending(Application $application, FollowUpDiscardReason $reason): int
    {
        $discarded = ApplicationFollowUp::query()
            ->where('application_id', $application->id)
            ->where('status', FollowUpStatus::Pending->value)
            ->update([
                'status' => FollowUpStatus::Discarded->value,
                'discarded_at' => now(),
                'discard_reason' => $reason->value,
                'updated_at' => now(),
            ]);

        $application->unsetRelation('pendingFollowUp')->unsetRelation('followUps');

        return $discarded;
    }

    /**
     * Every application due for a follow-up today.
     *
     * @return Collection<int, Application>
     */
    public function dueApplications(): Collection
    {
        return $this->dueQuery()
            ->with($this->dueRelations())
            ->get()
            ->filter(fn (Application $application): bool => $this->passesCapAndCadence($application))
            ->values();
    }

    /**
     * Whether one application is due for a follow-up now. Reads fresh from
     * the database, so it is safe to call under a row lock.
     */
    public function isDue(Application $application): bool
    {
        $candidate = $this->dueQuery()
            ->with($this->dueRelations())
            ->whereKey($application->getKey())
            ->first();

        return $candidate !== null && $this->passesCapAndCadence($candidate);
    }

    /**
     * How many follow-ups have been sent since the latest status entry was
     * recorded: the count the per-stage maximum applies to.
     */
    public function sentSinceStatusChange(Application $application): int
    {
        $latest = $application->latestStatusUpdate()->first();

        return ApplicationFollowUp::query()
            ->where('application_id', $application->id)
            ->where('status', FollowUpStatus::Sent->value)
            ->when($latest !== null, fn (Builder $query) => $query->where('sent_at', '>', $latest->created_at))
            ->count();
    }

    /**
     * The AI system follow-ups are drafted with: the active chat system that
     * is the feature default. There is no fallback to another feature's
     * default.
     */
    public function system(): ?AiSystem
    {
        $system = AiSystem::defaultForFeature(self::FEATURE);

        if ($system === null || ! $system->is_active || $system->role !== AiSystemRole::Chat) {
            return null;
        }

        return $system;
    }

    /**
     * Draft a follow-up for a due application and store it as pending. No
     * email goes to any contact; the owner is told a draft is waiting.
     *
     * Returns null, logging why, when no follow-up system is configured, the
     * model fails or replies with nothing, or the application stopped being
     * due while the model was writing. Nothing is stored in those cases, so
     * the application is due again on the next run.
     */
    public function draft(Application $application): ?ApplicationFollowUp
    {
        $system = $this->system();

        if ($system === null) {
            Log::warning('Application follow-up not drafted: no active chat AI system is the application-follow-up default.', [
                'application_id' => $application->id,
            ]);

            return null;
        }

        try {
            $agent = $this->agentFactory->forSystem(
                $system,
                instructions: self::instructions(AiSystemPrompt::find(self::PROMPT_ID_FOLLOW_UP)?->content),
                maxTokens: $system->max_tokens,
            );

            $reply = (string) $agent->prompt($this->draftingMessage($application))->text;
        } catch (Throwable $exception) {
            Log::error('Application follow-up drafting failed.', [
                'application_id' => $application->id,
                'ai_system_id' => $system->id,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }

        $parsed = $this->parseDraft($reply, $application);

        if ($parsed === null) {
            Log::error('Application follow-up drafting failed: the AI system replied with no body.', [
                'application_id' => $application->id,
                'ai_system_id' => $system->id,
            ]);

            return null;
        }

        $followUp = DB::transaction(function () use ($application, $system, $parsed): ?ApplicationFollowUp {
            $locked = Application::query()->whereKey($application->getKey())->lockForUpdate()->first();

            if ($locked === null || ! $this->isDue($locked)) {
                return null;
            }

            $followUp = $locked->followUps()->create([
                'status' => FollowUpStatus::Pending,
                'subject' => $parsed['subject'],
                'body' => $parsed['body'],
                'ai_system_id' => $system->id,
                'drafted_at' => now(),
            ]);

            DB::afterCommit(fn () => Mail::to((string) config('resume.follow_ups.notification_email'))
                ->queue(new FollowUpDraftedMail($locked)));

            return $followUp;
        });

        if ($followUp === null) {
            Log::info('Application follow-up draft dropped: the application is no longer due.', [
                'application_id' => $application->id,
            ]);
        }

        $application->unsetRelation('pendingFollowUp')->unsetRelation('followUps');

        return $followUp;
    }

    /**
     * Edit a pending draft's subject and body.
     *
     * @throws ApplicationFollowUpMismatchException
     * @throws FollowUpNotPendingException
     */
    public function updateDraft(Application $application, ApplicationFollowUp $followUp, string $subject, string $body): ApplicationFollowUp
    {
        $this->assertFollowUpBelongsTo($application, $followUp);

        return DB::transaction(function () use ($application, $followUp, $subject, $body): ApplicationFollowUp {
            $locked = ApplicationFollowUp::query()->lockForUpdate()->findOrFail($followUp->id);

            if ($locked->status !== FollowUpStatus::Pending) {
                throw new FollowUpNotPendingException;
            }

            $locked->update(['subject' => trim($subject), 'body' => trim($body)]);
            $application->unsetRelation('pendingFollowUp')->unsetRelation('followUps');

            return $locked;
        });
    }

    /**
     * Approve a pending (or failed) follow-up: record the application's
     * current contacts as its recipients, claim it as `sending` under a row
     * lock, and queue the send once that commits. A second approve finds it
     * already claimed and is refused, so it goes out at most once.
     *
     * @throws ApplicationFollowUpMismatchException
     * @throws FollowUpNotPendingException
     * @throws FollowUpHasNoRecipientsException
     * @throws FollowUpIncompleteException
     */
    public function approve(Application $application, ApplicationFollowUp $followUp): ApplicationFollowUp
    {
        $this->assertFollowUpBelongsTo($application, $followUp);

        return DB::transaction(function () use ($application, $followUp): ApplicationFollowUp {
            $locked = ApplicationFollowUp::query()->lockForUpdate()->findOrFail($followUp->id);

            if (! $locked->status->isApprovable()) {
                throw new FollowUpNotPendingException;
            }

            if (trim($locked->subject) === '' || trim($locked->body) === '') {
                throw new FollowUpIncompleteException;
            }

            $recipients = $application->contacts()->pluck('email')->values()->all();

            if ($recipients === []) {
                throw new FollowUpHasNoRecipientsException;
            }

            $locked->update([
                'status' => FollowUpStatus::Sending,
                'recipients' => $recipients,
                'failure_reason' => null,
            ]);

            SendApplicationFollowUpJob::dispatch($locked->id)->afterCommit();

            $application->unsetRelation('pendingFollowUp')->unsetRelation('followUps');

            return $locked;
        });
    }

    /**
     * Discard a pending draft by hand. Nothing is sent, and the application
     * can become due again on the usual schedule.
     *
     * @throws ApplicationFollowUpMismatchException
     * @throws FollowUpNotPendingException
     */
    public function discard(Application $application, ApplicationFollowUp $followUp): ApplicationFollowUp
    {
        $this->assertFollowUpBelongsTo($application, $followUp);

        return DB::transaction(function () use ($application, $followUp): ApplicationFollowUp {
            $locked = ApplicationFollowUp::query()->lockForUpdate()->findOrFail($followUp->id);

            if ($locked->status !== FollowUpStatus::Pending) {
                throw new FollowUpNotPendingException;
            }

            $locked->update([
                'status' => FollowUpStatus::Discarded,
                'discarded_at' => now(),
                'discard_reason' => FollowUpDiscardReason::Manual,
            ]);

            $application->unsetRelation('pendingFollowUp')->unsetRelation('followUps');

            return $locked;
        });
    }

    /**
     * Record a send that went out.
     */
    public function markSent(ApplicationFollowUp $followUp): void
    {
        $followUp->update([
            'status' => FollowUpStatus::Sent,
            'sent_at' => now(),
            'failure_reason' => null,
        ]);
    }

    /**
     * Record a send that failed. Approving it again retries.
     */
    public function markFailed(ApplicationFollowUp $followUp, string $reason): void
    {
        $followUp->update([
            'status' => FollowUpStatus::Failed,
            'failure_reason' => Str::limit($reason, 1000),
        ]);
    }

    /**
     * The starter content of the Application Follow-up prompt, and the
     * fallback when the prompt row is missing or empty.
     */
    public static function buildFollowUpPrompt(): string
    {
        return <<<'PROMPT'
        You write short follow-up emails for a job seeker who is waiting to hear back about a job application.

        You will be given the job, the application's status history, the people the email goes to (names and roles only) and any follow-ups already sent. Write one polite, specific check-in:

        - Keep it short: three to six sentences.
        - Mention the role and the last step that happened (for example the application, or an interview), using only the facts you are given.
        - Never invent facts, dates, names or events. If you don't know something, leave it out.
        - Don't mention salary or compensation.
        - Don't repeat an earlier follow-up; if one was sent before, acknowledge it lightly and keep this one shorter.
        - If there is exactly one named contact, greet them by first name. Otherwise use a neutral greeting such as "Hello,".
        - Sign off with the job seeker's name.
        - Plain text only: no Markdown, no placeholders like [Name].
        PROMPT;
    }

    /**
     * The site's own output format, appended after the editable prompt so
     * that editing the prompt cannot break parsing.
     */
    public static function outputFormatInstructions(): string
    {
        return <<<'INSTRUCTIONS'
        ## Output format

        Reply with the email only, in exactly this form, with nothing before or after it:

        Subject: <the subject line>

        <the body, ending with the sign-off>
        INSTRUCTIONS;
    }

    /**
     * The drafting instructions: the Application Follow-up prompt, then the
     * output format.
     */
    public static function instructions(?string $promptContent): string
    {
        $prompt = trim((string) $promptContent) !== '' ? $promptContent : self::buildFollowUpPrompt();

        return trim($prompt)."\n\n---\n\n".self::outputFormatInstructions();
    }

    /**
     * The one message the drafting model is given. It names the contacts by
     * name and role only: their email addresses are never sent to the model.
     */
    public function draftingMessage(Application $application): string
    {
        $application->loadMissing(['statusUpdates', 'contacts']);

        $lines = [
            'Today: '.now()->toDateString(),
            'Job seeker: '.config('mail.from.name'),
            '',
            'Company: '.($application->knownCompanyName() ?? 'Unknown'),
            'Position: '.($application->knownPosition() ?? 'Unknown'),
        ];

        if (trim((string) $application->location) !== '') {
            $lines[] = 'Location: '.$application->location;
        }

        $lines[] = '';
        $lines[] = 'Job description:';
        $lines[] = Str::limit(trim((string) $application->job_description), self::JOB_DESCRIPTION_LIMIT, ' [truncated]');
        $lines[] = '';
        $lines[] = 'Status history (oldest first):';

        foreach ($application->statusUpdates as $statusUpdate) {
            $notes = trim((string) $statusUpdate->notes);
            $lines[] = '- '.$statusUpdate->occurred_at->toDateString().': '.$statusUpdate->status->value.($notes !== '' ? " — {$notes}" : '');
        }

        $lines[] = '';
        $lines[] = 'Recipients:';

        foreach ($application->contacts as $contact) {
            $name = trim((string) $contact->name);
            $role = trim((string) $contact->role);
            $lines[] = '- '.($name !== '' ? $name : 'A contact whose name is not known').($role !== '' ? " ({$role})" : '');
        }

        $sent = ApplicationFollowUp::query()
            ->where('application_id', $application->id)
            ->where('status', FollowUpStatus::Sent->value)
            ->orderBy('sent_at')
            ->get();

        $lines[] = '';

        if ($sent->isEmpty()) {
            $lines[] = 'Follow-ups already sent: none.';
        } else {
            $lines[] = 'Follow-ups already sent (oldest first):';

            foreach ($sent as $followUp) {
                $lines[] = '';
                $lines[] = 'Sent '.$followUp->sent_at?->toDateString().' — Subject: '.$followUp->subject;
                $lines[] = $followUp->body;
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Split a reply into its subject and body. The first `Subject:` line is
     * the subject, and what follows it the body; without one, the whole
     * reply is the body under a default subject. A reply with no body is
     * null.
     *
     * @return array{subject: string, body: string}|null
     */
    public function parseDraft(string $reply, Application $application): ?array
    {
        $text = trim(preg_replace('/^```[a-z]*\s*|\s*```$/i', '', trim($reply)) ?? '');
        $subject = null;
        $body = $text;

        if (preg_match('/^[ \t]*\**[ \t]*Subject[ \t]*:\**[ \t]*(.*)$/mi', $text, $match, PREG_OFFSET_CAPTURE)) {
            $subject = trim($match[1][0], " \t*");
            $body = trim(substr($text, $match[0][1] + strlen($match[0][0])));
        }

        if ($body === '') {
            return null;
        }

        if ($subject === null || $subject === '') {
            $subject = self::defaultSubject($application);
        }

        return [
            'subject' => Str::limit($subject, self::SUBJECT_LIMIT - 3),
            'body' => Str::limit($body, self::BODY_LIMIT, ''),
        ];
    }

    /**
     * The subject used when the model gives none.
     */
    public static function defaultSubject(Application $application): string
    {
        $position = $application->knownPosition();
        $company = $application->knownCompanyName();

        return match (true) {
            $position !== null && $company !== null => "Following up: {$position} at {$company}",
            $position !== null => "Following up: {$position}",
            $company !== null => "Following up on my application at {$company}",
            default => 'Following up on my application',
        };
    }

    /**
     * Non-deleted applications whose switch is on, in a follow-up status,
     * with a contact and no draft already open.
     *
     * @return Builder<Application>
     */
    private function dueQuery(): Builder
    {
        $statuses = array_values(array_filter(
            ApplicationStatus::cases(),
            fn (ApplicationStatus $status): bool => $status->receivesFollowUps(),
        ));

        return Application::query()
            ->where('follow_ups_enabled', true)
            ->whereIn('status', array_column($statuses, 'value'))
            ->whereHas('contacts')
            ->whereDoesntHave('followUps', fn (Builder $query) => $query->whereIn('status', [
                FollowUpStatus::Pending->value,
                FollowUpStatus::Sending->value,
            ]));
    }

    /**
     * @return array<string|int, mixed>
     */
    private function dueRelations(): array
    {
        return [
            'latestStatusUpdate',
            'followUps' => fn ($query) => $query->where('status', FollowUpStatus::Sent->value),
        ];
    }

    /**
     * The checks done in PHP, on the relations {@see dueRelations()} loads:
     * fewer than the maximum sent since the latest entry was recorded, and
     * the cadence passed since the later of that entry's date and the last
     * send.
     */
    private function passesCapAndCadence(Application $application): bool
    {
        $latest = $application->latestStatusUpdate;

        if ($latest === null) {
            return false;
        }

        $sent = $application->followUps->filter(fn (ApplicationFollowUp $followUp): bool => $followUp->sent_at !== null);

        $sentSinceStatusChange = $sent->filter(
            fn (ApplicationFollowUp $followUp): bool => $followUp->sent_at->greaterThan($latest->created_at),
        )->count();

        if ($sentSinceStatusChange >= (int) config('resume.follow_ups.max_per_status')) {
            return false;
        }

        /** @var CarbonInterface $anchor */
        $anchor = $sent->max('sent_at');
        $anchor = $anchor !== null && $anchor->greaterThan($latest->occurred_at) ? $anchor : $latest->occurred_at;

        return $anchor->lessThanOrEqualTo(now()->subDays((int) config('resume.follow_ups.cadence_days')));
    }

    /**
     * @throws ApplicationContactMismatchException
     */
    private function assertContactBelongsTo(Application $application, ApplicationContact $contact): void
    {
        if ((int) $contact->application_id !== (int) $application->id) {
            throw new ApplicationContactMismatchException;
        }
    }

    /**
     * @throws ApplicationFollowUpMismatchException
     */
    private function assertFollowUpBelongsTo(Application $application, ApplicationFollowUp $followUp): void
    {
        if ((int) $followUp->application_id !== (int) $application->id) {
            throw new ApplicationFollowUpMismatchException;
        }
    }
}
