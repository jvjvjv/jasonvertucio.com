<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreApplicationContactRequest;
use App\Http\Requests\UpdateApplicationContactRequest;
use App\Models\Application;
use App\Models\ApplicationContact;
use App\Services\ApplicationFollowUpService;
use App\Support\ApplicationPayload;
use Illuminate\Http\JsonResponse;

/**
 * An application's contacts. Every action answers with the application's
 * contacts and follow-ups as they stand afterwards.
 */
class ApplicationContactController extends Controller
{
    public function __construct(
        private ApplicationFollowUpService $followUps,
        private ApplicationPayload $payload,
    ) {}

    public function store(StoreApplicationContactRequest $request, Application $application): JsonResponse
    {
        $this->followUps->addContact($application, $request->contact());

        return response()->json($this->payload->followUpState($application));
    }

    public function update(UpdateApplicationContactRequest $request, Application $application, ApplicationContact $contact): JsonResponse
    {
        $this->followUps->updateContact($application, $contact, $request->contact());

        return response()->json($this->payload->followUpState($application));
    }

    public function destroy(Application $application, ApplicationContact $contact): JsonResponse
    {
        $this->followUps->deleteContact($application, $contact);

        return response()->json($this->payload->followUpState($application));
    }
}
