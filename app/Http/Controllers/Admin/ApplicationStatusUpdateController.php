<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreApplicationStatusUpdateRequest;
use App\Http\Requests\UpdateApplicationStatusUpdateRequest;
use App\Models\Application;
use App\Models\ApplicationStatusUpdate;
use App\Services\ApplicationService;
use App\Support\ApplicationPayload;
use Illuminate\Http\JsonResponse;

/**
 * An application's status history. Every action answers with the
 * application's status as it stands afterwards.
 */
class ApplicationStatusUpdateController extends Controller
{
    public function __construct(
        private ApplicationService $applicationService,
        private ApplicationPayload $payload,
    ) {}

    /**
     * Add a history entry and move the application to its status.
     */
    public function store(StoreApplicationStatusUpdateRequest $request, Application $application): JsonResponse
    {
        $this->applicationService->addStatusUpdate(
            $application,
            $request->status(),
            $request->validated('notes'),
            $request->date('occurred_at'),
        );

        return response()->json($this->payload->statusState($application));
    }

    /**
     * Edit an entry's notes and date.
     */
    public function update(
        UpdateApplicationStatusUpdateRequest $request,
        Application $application,
        ApplicationStatusUpdate $statusUpdate,
    ): JsonResponse {
        $this->applicationService->updateStatusUpdate(
            $application,
            $statusUpdate,
            $request->validated('notes'),
            $request->date('occurred_at'),
        );

        return response()->json($this->payload->statusState($application));
    }

    /**
     * Delete an entry; the application falls back to its latest remaining
     * entry's status.
     */
    public function destroy(Application $application, ApplicationStatusUpdate $statusUpdate): JsonResponse
    {
        $this->applicationService->deleteStatusUpdate($application, $statusUpdate);

        return response()->json($this->payload->statusState($application));
    }
}
