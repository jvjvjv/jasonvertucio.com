<?php

namespace App\Services;

use App\Enums\ApplicationStatus;
use App\Exceptions\ApplicationAlreadyHasConversationException;
use App\Exceptions\ApplicationInPipelineException;
use App\Exceptions\ApplicationStatusUpdateMismatchException;
use App\Exceptions\NonPipelineStatusException;
use App\Exceptions\ResumeVersionUnavailableException;
use App\Exceptions\TargetedResumeAlreadySentException;
use App\Exceptions\TargetedResumeMissingException;
use App\Exceptions\TerminalApplicationException;
use App\Models\Application;
use App\Models\ApplicationStatusUpdate;
use App\Models\ResumeVersion;
use App\Models\TargetedResume;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Jvjvjv\CodeTalker\Enums\AiConversationStatus;
use Jvjvjv\CodeTalker\Models\AiSystem;

/**
 * Owns the lifecycle of a tracked job: creating it, moving it through its
 * statuses, keeping its status history, and attaching or discarding the
 * documents built for it.
 */
class ApplicationService
{
    public function __construct(
        private TargetedResumeService $targetedResumeService,
    ) {}

    /**
     * Record a job and start its AI session, as one unit: a failure leaves
     * neither behind. The application is a draft tailored from the current
     * resume version.
     *
     * @throws ResumeVersionUnavailableException when no current resume version exists
     */
    public function createForAnalysis(
        AiSystem $system,
        string $jobDescription,
        ?string $companyName = null,
        ?string $position = null,
        ?string $location = null,
        ?string $jobUrlId = null,
    ): Application {
        return DB::transaction(function () use ($system, $jobDescription, $companyName, $position, $location, $jobUrlId): Application {
            $application = $this->newApplication(
                $this->resolveResumeVersion(null),
                ApplicationStatus::Draft,
                $jobDescription,
                $companyName,
                $position,
                $location,
                $jobUrlId,
            );

            $this->targetedResumeService->startConversation($system, $application);

            return $application;
        });
    }

    /**
     * Record a job that was applied to with a main resume: no AI session, the
     * chosen resume version (the current one by default) and a first
     * `applied` history entry, dated now unless a date is given.
     *
     * @throws ResumeVersionUnavailableException when the chosen version does not exist, or none was chosen and there is no current one
     */
    public function createApplied(
        string $jobDescription,
        ?string $companyName = null,
        ?string $position = null,
        ?string $location = null,
        ?string $jobUrlId = null,
        ?int $resumeVersionId = null,
        ?CarbonInterface $occurredAt = null,
    ): Application {
        return DB::transaction(function () use ($jobDescription, $companyName, $position, $location, $jobUrlId, $resumeVersionId, $occurredAt): Application {
            $application = $this->newApplication(
                $this->resolveResumeVersion($resumeVersionId),
                ApplicationStatus::Draft,
                $jobDescription,
                $companyName,
                $position,
                $location,
                $jobUrlId,
            );

            $this->addStatusUpdate($application, ApplicationStatus::Applied, null, $occurredAt);

            return $application;
        });
    }

    /**
     * Attach a new AI session to an application that has none. Its status
     * and status history are left as they are. A session that was deleted
     * from the AI Conversations admin counts as none, and is replaced.
     *
     * @throws ApplicationAlreadyHasConversationException
     */
    public function beginAnalysis(Application $application, AiSystem $system): Application
    {
        return DB::transaction(function () use ($application, $system): Application {
            $locked = Application::query()->lockForUpdate()->findOrFail($application->id);

            if ($locked->conversation()->exists()) {
                throw new ApplicationAlreadyHasConversationException;
            }

            $this->targetedResumeService->startConversation($system, $application);

            return $application;
        });
    }

    /**
     * Mark an application as applied. An application with a targeted resume
     * used that resume, so any resume version given is ignored; otherwise
     * the chosen version (the current one by default) is recorded as the
     * resume that was sent. No placeholder targeted resume is ever created.
     *
     * @throws TerminalApplicationException
     * @throws ResumeVersionUnavailableException when a chosen version does not exist
     */
    public function markApplied(
        Application $application,
        ?int $resumeVersionId = null,
        ?CarbonInterface $occurredAt = null,
        ?string $notes = null,
    ): Application {
        return DB::transaction(function () use ($application, $resumeVersionId, $occurredAt, $notes): Application {
            $this->assertNotTerminal($application);

            if ($application->targeted_resume_id === null) {
                $resumeVersion = $resumeVersionId !== null
                    ? $this->resolveResumeVersion($resumeVersionId)
                    : ResumeVersion::current()->first();

                if ($resumeVersion !== null) {
                    $application->resume_version_id = $resumeVersion->id;
                    $application->unsetRelation('resumeVersion');
                }
            }

            $this->addStatusUpdate($application, ApplicationStatus::Applied, $notes, $occurredAt);

            return $application;
        });
    }

    /**
     * Pass on a job. The session, when there is one, is marked passed too so
     * the generic AI Conversations admin keeps showing it; nothing reads that
     * back.
     *
     * @throws ApplicationInPipelineException when the application has already been applied to
     */
    public function pass(Application $application): Application
    {
        if ($application->status->isPipeline()) {
            throw new ApplicationInPipelineException;
        }

        return DB::transaction(function () use ($application): Application {
            $application->update(['status' => ApplicationStatus::Passed]);
            $application->conversation?->update(['status' => AiConversationStatus::Pass]);

            return $application;
        });
    }

    /**
     * Update the job details. Only the keys present are touched. `title` is
     * the AI session's title and is ignored for an application without one.
     *
     * @param  array{
     *     title?: ?string,
     *     company_name?: ?string,
     *     position?: ?string,
     *     location?: ?string,
     *     job_description?: ?string,
     *     job_url_id?: ?string,
     *     fit_score?: ?int,
     *     fit_summary?: ?string
     * }  $details
     */
    public function updateDetails(Application $application, array $details): Application
    {
        return DB::transaction(function () use ($application, $details): Application {
            foreach (['location', 'job_url_id', 'fit_score', 'fit_summary'] as $column) {
                if (array_key_exists($column, $details)) {
                    $application->{$column} = $details[$column];
                }
            }

            if (array_key_exists('job_description', $details)) {
                $application->job_description = (string) $details['job_description'];
            }

            if (array_key_exists('job_url_id', $details)) {
                $application->unsetRelation('jobUrl');
            }

            return $this->targetedResumeService->updateConversationMetadata($application, $details);
        });
    }

    /**
     * Add a status-history entry and move the application to its status.
     * Which status may follow which is advisory ({@see ApplicationStatus::allowedNext()}
     * drives the choices offered); only a terminal application refuses.
     *
     * @throws NonPipelineStatusException
     * @throws TerminalApplicationException
     */
    public function addStatusUpdate(
        Application $application,
        ApplicationStatus $status,
        ?string $notes = null,
        ?CarbonInterface $occurredAt = null,
    ): ApplicationStatusUpdate {
        if (! $status->isPipeline()) {
            throw new NonPipelineStatusException;
        }

        $this->assertNotTerminal($application);

        return DB::transaction(function () use ($application, $status, $notes, $occurredAt): ApplicationStatusUpdate {
            $statusUpdate = $application->statusUpdates()->create([
                'status' => $status,
                'notes' => $notes,
                'occurred_at' => $occurredAt ?? now(),
            ]);

            $application->status = $status;
            $application->save();
            $application->unsetRelation('statusUpdates')->unsetRelation('latestStatusUpdate');

            return $statusUpdate;
        });
    }

    /**
     * Edit an entry's notes and date. Its status, and the application's, are
     * left alone.
     *
     * @throws ApplicationStatusUpdateMismatchException
     */
    public function updateStatusUpdate(
        Application $application,
        ApplicationStatusUpdate $statusUpdate,
        ?string $notes,
        CarbonInterface $occurredAt,
    ): ApplicationStatusUpdate {
        $this->assertBelongsTo($application, $statusUpdate);

        $statusUpdate->update([
            'notes' => $notes,
            'occurred_at' => $occurredAt,
        ]);

        $application->unsetRelation('statusUpdates')->unsetRelation('latestStatusUpdate');

        return $statusUpdate;
    }

    /**
     * Delete an entry. The application falls back to its latest remaining
     * entry's status, or to `draft` when none remain.
     *
     * @throws ApplicationStatusUpdateMismatchException
     */
    public function deleteStatusUpdate(Application $application, ApplicationStatusUpdate $statusUpdate): Application
    {
        $this->assertBelongsTo($application, $statusUpdate);

        return DB::transaction(function () use ($application, $statusUpdate): Application {
            $statusUpdate->delete();

            $application->unsetRelation('statusUpdates')->unsetRelation('latestStatusUpdate');

            $latest = $application->latestStatusUpdate()->first();

            $application->status = $latest?->status ?? ApplicationStatus::Draft;
            $application->save();

            return $application;
        });
    }

    /**
     * Soft-delete an application, and its AI session with it so the AI
     * Conversations admin stays consistent.
     */
    public function delete(Application $application): void
    {
        DB::transaction(function () use ($application): void {
            $application->conversation?->delete();
            $application->delete();
        });
    }

    /**
     * Permanently discard an application's targeted resume and its rendered
     * documents. The application keeps its recorded resume version, status,
     * history, session and cover letter; the session is told, without an
     * agent turn, that the document no longer exists.
     *
     * A targeted resume attached to no application is simply deleted. A
     * deleted application still counts: its resume stays the record of what
     * was sent, so the applied guard looks through the soft delete. The
     * rendered files are unlinked last: a file cannot be rolled back, so
     * everything that can still fail runs before it.
     *
     * @return Application|null the application the resume was detached from
     *
     * @throws TargetedResumeAlreadySentException once the application has an `applied` entry
     * @throws TargetedResumeMissingException when given an application with no targeted resume
     */
    public function discardTargetedResume(Application|TargetedResume $subject): ?Application
    {
        $application = $subject instanceof Application ? $subject : $subject->application()->withTrashed()->first();
        $targetedResume = $subject instanceof TargetedResume ? $subject : $subject->targetedResume()->first();

        if ($targetedResume === null) {
            throw new TargetedResumeMissingException;
        }

        if ($application?->hasBeenApplied()) {
            throw new TargetedResumeAlreadySentException;
        }

        return DB::transaction(function () use ($application, $targetedResume): ?Application {
            $targetedResumeId = $targetedResume->id;

            if ($application !== null) {
                $application->targeted_resume_id = null;
                $application->save();
                $application->unsetRelation('targetedResume');
            }

            if ($application !== null) {
                $this->targetedResumeService->recordResumeDiscardedMessage($application, $targetedResumeId);
            }

            $targetedResume->invalidateDocuments();
            $targetedResume->delete();

            return $application;
        });
    }

    private function newApplication(
        ResumeVersion $resumeVersion,
        ApplicationStatus $status,
        string $jobDescription,
        ?string $companyName,
        ?string $position,
        ?string $location,
        ?string $jobUrlId,
    ): Application {
        return Application::create([
            'resume_version_id' => $resumeVersion->id,
            'job_url_id' => $jobUrlId,
            'company_name' => $this->textOr($companyName, Application::UNKNOWN_COMPANY),
            'position' => $this->textOr($position, Application::UNKNOWN_POSITION),
            'location' => $this->textOr($location, null),
            'job_description' => $jobDescription,
            'status' => $status,
        ]);
    }

    /**
     * @throws ResumeVersionUnavailableException
     */
    private function resolveResumeVersion(?int $resumeVersionId): ResumeVersion
    {
        $resumeVersion = $resumeVersionId !== null
            ? ResumeVersion::query()->find($resumeVersionId)
            : ResumeVersion::current()->first();

        return $resumeVersion ?? throw new ResumeVersionUnavailableException;
    }

    /**
     * @throws TerminalApplicationException
     */
    private function assertNotTerminal(Application $application): void
    {
        if ($application->status->isTerminal()) {
            throw new TerminalApplicationException;
        }
    }

    /**
     * @throws ApplicationStatusUpdateMismatchException
     */
    private function assertBelongsTo(Application $application, ApplicationStatusUpdate $statusUpdate): void
    {
        if ((int) $statusUpdate->application_id !== (int) $application->id) {
            throw new ApplicationStatusUpdateMismatchException;
        }
    }

    private function textOr(?string $value, ?string $fallback): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : $fallback;
    }
}
