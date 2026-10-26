<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RoleIntelStatus;
use App\Exceptions\SessionTurnInProgressException;
use App\Http\Controllers\Controller;
use App\Http\Requests\SwitchSessionSystemRequest;
use App\Models\Application;
use App\Models\RoleIntel;
use App\Services\AiSession\SessionSystemSwitcher;
use App\Services\CompanyIntel\CompanyIntelSystems;
use App\Services\TargetedResumeSystems;
use Illuminate\Http\JsonResponse;
use Jvjvjv\CodeTalker\Models\AiSystem;

/**
 * Moving an Application or Company Intel session to another AI system.
 */
class SessionSystemSwitchController extends Controller
{
    public function __construct(
        private SessionSystemSwitcher $switcher,
        private TargetedResumeSystems $targetedResumeSystems,
        private CompanyIntelSystems $companyIntelSystems,
    ) {}

    /**
     * The systems an application's session may move to.
     */
    public function applicationOptions(Application $application): JsonResponse
    {
        if ($application->conversation === null) {
            return $this->noApplicationSessionResponse();
        }

        return response()->json($this->switcher->options($application->conversation, $this->targetedResumeSystems));
    }

    /**
     * Switch an application's session, refused while a reply is streaming.
     */
    public function switchApplication(SwitchSessionSystemRequest $request, Application $application): JsonResponse
    {
        $conversation = $application->conversation;

        if ($conversation === null) {
            return $this->noApplicationSessionResponse();
        }

        $turnLock = SessionSystemSwitcher::turnLock($conversation->id);

        if (! $turnLock->get()) {
            throw new SessionTurnInProgressException;
        }

        try {
            $this->switcher->switch($conversation, AiSystem::findOrFail($request->integer('ai_system_id')), $this->targetedResumeSystems);
        } finally {
            $turnLock->release();
        }

        return response()->json(['success' => true]);
    }

    /**
     * The systems an intel session may move to.
     */
    public function intelOptions(RoleIntel $roleIntel): JsonResponse
    {
        if ($roleIntel->conversation === null) {
            return $this->noIntelSessionResponse();
        }

        return response()->json($this->switcher->options($roleIntel->conversation, $this->companyIntelSystems));
    }

    /**
     * Switch an intel session, refused while research is queued or running.
     */
    public function switchIntel(SwitchSessionSystemRequest $request, RoleIntel $roleIntel): JsonResponse
    {
        if ($roleIntel->conversation === null) {
            return $this->noIntelSessionResponse();
        }

        if (in_array($roleIntel->status, [RoleIntelStatus::Queued, RoleIntelStatus::Running], true)) {
            throw new SessionTurnInProgressException('Research is still running. Wait for it to finish, then switch.');
        }

        $this->switcher->switch($roleIntel->conversation, AiSystem::findOrFail($request->integer('ai_system_id')), $this->companyIntelSystems);

        return response()->json(['success' => true]);
    }

    private function noApplicationSessionResponse(): JsonResponse
    {
        return response()->json(['message' => 'This application has no AI session. Begin an analysis first.'], 409);
    }

    private function noIntelSessionResponse(): JsonResponse
    {
        return response()->json(['message' => 'This intel has no session.'], 409);
    }
}
