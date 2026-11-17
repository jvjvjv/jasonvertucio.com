<?php

namespace App\Jobs;

use App\Enums\FollowUpStatus;
use App\Mail\ApplicationFollowUpMail;
use App\Models\ApplicationFollowUp;
use App\Services\ApplicationFollowUpService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Sends one approved follow-up. It is the queue boundary, so the mail itself
 * is sent synchronously here.
 *
 * One try and no automatic retry: a transient failure is marked failed and
 * the admin retries by approving again, so a send can never go out twice.
 * Only a follow-up still claimed as `sending` is sent.
 */
class SendApplicationFollowUpJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 120;

    public bool $failOnTimeout = true;

    public function __construct(public int $followUpId) {}

    public function handle(ApplicationFollowUpService $followUps): void
    {
        $followUp = ApplicationFollowUp::query()->with('application')->find($this->followUpId);

        if ($followUp === null || $followUp->status !== FollowUpStatus::Sending) {
            return;
        }

        try {
            Mail::send(new ApplicationFollowUpMail($followUp));
        } catch (Throwable $exception) {
            Log::error('Application follow-up could not be sent.', [
                'follow_up_id' => $followUp->id,
                'application_id' => $followUp->application_id,
                'error' => $exception->getMessage(),
            ]);

            $followUps->markFailed($followUp, $exception->getMessage());

            return;
        }

        $followUps->markSent($followUp);
    }

    public function failed(?Throwable $exception): void
    {
        $followUp = ApplicationFollowUp::query()->find($this->followUpId);

        if ($followUp === null || $followUp->status !== FollowUpStatus::Sending) {
            return;
        }

        $reason = $exception !== null && str_contains($exception::class, 'Timeout')
            ? 'Sending timed out.'
            : ($exception?->getMessage() ?: 'Sending failed.');

        app(ApplicationFollowUpService::class)->markFailed($followUp, $reason);
    }
}
