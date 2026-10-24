<?php

namespace App\Http\Controllers\Admin;

use App\Enums\IntelStartMode;
use App\Enums\RoleIntelStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\CompanyIntelChatRequest;
use App\Http\Requests\RejectIntelCompanyRequest;
use App\Http\Requests\RetryCompanyIntelRequest;
use App\Http\Requests\StartCompanyIntelRequest;
use App\Models\Application;
use App\Models\RoleIntel;
use App\Services\CompanyIntel\CompanyIntelService;
use App\Services\CompanyIntel\CompanyIntelSystems;
use App\Support\CompanyIntelPayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Jvjvjv\CodeTalker\Models\AiSystem;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The JSON side of Company Intel: starting it for an application, the intel
 * session's chat, polling its state, retrying, and correcting the company.
 */
class CompanyIntelSessionController extends Controller
{
    public function __construct(
        private CompanyIntelService $companyIntel,
        private CompanyIntelSystems $systems,
        private CompanyIntelPayload $payload,
    ) {}

    /**
     * The systems the admin may choose from when there is no default.
     */
    public function eligibleSystems(): JsonResponse
    {
        $default = $this->systems->default();

        return response()->json([
            'default' => $default !== null ? ['id' => $default->id, 'name' => $default->name, 'model' => $default->model] : null,
            'systems' => $this->systems->eligible()
                ->map(fn (AiSystem $system): array => ['id' => $system->id, 'name' => $system->name, 'model' => $system->model])
                ->values(),
        ]);
    }

    public function start(StartCompanyIntelRequest $request, Application $application): JsonResponse
    {
        $roleIntel = $this->companyIntel->startWith($application, $request->validated('ai_system_id'), $request->mode());

        return response()->json([
            'role_intel_id' => $roleIntel->id,
            'status' => $roleIntel->status->value,
            'redirect' => route('admin.employment.company-intel.show', $roleIntel),
        ], 201);
    }

    public function state(RoleIntel $roleIntel): JsonResponse
    {
        return response()->json($this->payload->roleIntel($roleIntel));
    }

    public function retry(RetryCompanyIntelRequest $request, RoleIntel $roleIntel): JsonResponse
    {
        $roleIntel = $this->companyIntel->retry($roleIntel, $request->mode());

        return response()->json(['status' => $roleIntel->status->value]);
    }

    public function wrongCompany(RejectIntelCompanyRequest $request, RoleIntel $roleIntel): JsonResponse
    {
        $message = $this->companyIntel->rejectCompany(
            $roleIntel,
            $request->validated('company_id'),
            $request->validated('hint'),
        );

        return response()->json([
            'message' => $message,
            'intel' => $this->payload->roleIntel($roleIntel->fresh()),
        ]);
    }

    /**
     * Stream one turn of the intel session: the pending one (no message), or
     * the admin's follow-up. Refused while a turn is running, or while the
     * first turn is waiting in the queue.
     */
    public function chat(CompanyIntelChatRequest $request, RoleIntel $roleIntel): StreamedResponse|JsonResponse
    {
        $message = $request->validated('message');
        $context = $roleIntel->conversation?->context ?? [];
        $hasMessage = $message !== null && trim($message) !== '';

        if ($roleIntel->conversation === null) {
            return response()->json(['message' => 'This intel has no session.'], 409);
        }

        if ($roleIntel->status === RoleIntelStatus::Running
            || ($roleIntel->status === RoleIntelStatus::Queued && ! ($context['auto_start_pending'] ?? false))) {
            return response()->json(['message' => 'Research is still running. Wait for it to finish.'], 409);
        }

        if (! $hasMessage && ! ($context['auto_start_pending'] ?? false)) {
            return response()->json(['message' => 'Type a message to continue the research.'], 422);
        }

        return response()->stream(function () use ($roleIntel, $message): void {
            set_time_limit(0);

            echo 'data: '.json_encode(['type' => 'status', 'message' => 'Researching...'])."\n\n";
            $this->flush();

            try {
                foreach ($this->companyIntel->runTurn($roleIntel, $message) as $chunk) {
                    echo $chunk;
                    $this->flush();
                }
            } catch (\Throwable $e) {
                Log::error('company-intel.chat: stream failed', [
                    'role_intel_id' => $roleIntel->id,
                    'error' => $e->getMessage(),
                ]);
                echo 'data: '.json_encode(['type' => 'error', 'message' => 'Stream failed unexpectedly.'])."\n\n";
                echo "data: [DONE]\n\n";
                $this->flush();
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    private function flush(): void
    {
        if (ob_get_level() > 0) {
            ob_flush();
        }

        flush();
    }
}
