<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateApplicationFollowUpRequest;
use App\Models\Application;
use App\Models\ApplicationFollowUp;
use App\Services\ApplicationFollowUpService;
use App\Support\ApplicationPayload;
use Illuminate\Http\JsonResponse;

/**
 * Reviewing an application's follow-up drafts: editing, approving (which
 * queues the send) and discarding. Every action answers with the
 * application's contacts and follow-ups as they stand afterwards.
 */
class ApplicationFollowUpController extends Controller
{
    public function __construct(
        private ApplicationFollowUpService $followUps,
        private ApplicationPayload $payload,
    ) {}

    public function update(UpdateApplicationFollowUpRequest $request, Application $application, ApplicationFollowUp $followUp): JsonResponse
    {
        $this->followUps->updateDraft($application, $followUp, $request->validated('subject'), $request->validated('body'));

        return response()->json($this->payload->followUpState($application));
    }

    public function approve(Application $application, ApplicationFollowUp $followUp): JsonResponse
    {
        $this->followUps->approve($application, $followUp);

        return response()->json($this->payload->followUpState($application));
    }

    public function discard(Application $application, ApplicationFollowUp $followUp): JsonResponse
    {
        $this->followUps->discard($application, $followUp);

        return response()->json($this->payload->followUpState($application));
    }
}
