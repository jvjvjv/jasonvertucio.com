<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ApplicationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\ApplyApplicationRequest;
use App\Http\Requests\StoreApplicationRequest;
use App\Http\Requests\UpdateApplicationRequest;
use App\Models\AiConversation;
use App\Models\Application;
use App\Models\CoverLetter;
use App\Models\ResumeVersion;
use App\Models\TargetedResume;
use App\Services\ApplicationService;
use App\Support\AnalysisSystemGuard;
use App\Support\ApplicationPayload;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Jvjvjv\CodeTalker\Models\AiSystem;

class ApplicationController extends Controller
{
    private const string USES_TARGETED_RESUME_ANY = 'any';

    private const string USES_TARGETED_RESUME_YES = 'yes';

    private const string USES_TARGETED_RESUME_NO = 'no';

    public function __construct(
        private ApplicationService $applicationService,
        private ApplicationPayload $payload,
    ) {}

    /**
     * List every tracked job, most recently active first, with optional
     * status, targeted-resume and search filters.
     */
    public function index(Request $request): InertiaResponse
    {
        $statuses = $this->requestedStatuses($request);
        $usesTargetedResume = $this->requestedTargetedResumeFilter($request);
        $search = trim((string) $request->string('search'));

        $applications = Application::query()
            ->with([
                'resumeVersion:id,version',
                'latestStatusUpdate',
                'conversation' => fn ($conversation) => $conversation
                    ->withLastMessageAt()
                    ->withCount(['messages' => fn ($messages) => $messages->where('role', '!=', 'system')]),
            ])
            ->when($statuses !== [], fn (Builder $query) => $query->whereIn('status', $statuses))
            ->when($usesTargetedResume === self::USES_TARGETED_RESUME_YES, fn (Builder $query) => $query->whereNotNull('targeted_resume_id'))
            ->when($usesTargetedResume === self::USES_TARGETED_RESUME_NO, fn (Builder $query) => $query->whereNull('targeted_resume_id'))
            ->when($search !== '', fn (Builder $query) => $query->where(function (Builder $matching) use ($search): void {
                $matching->where('company_name', 'LIKE', '%'.$search.'%')
                    ->orWhere('position', 'LIKE', '%'.$search.'%')
                    ->orWhereHas('conversation.messages', function (Builder $messages) use ($search): void {
                        $messages->where('role', '!=', 'system')
                            ->where('content', 'LIKE', '%'.$search.'%');
                    });
            }))
            ->get()
            ->sortByDesc(fn (Application $application): int => $this->lastActivityAt($application)?->getTimestamp() ?? 0)
            ->values()
            ->map(fn (Application $application): array => $this->listRow($application));

        return Inertia::render('resume/applications/Index', [
            'applications' => $applications,
            'allStatuses' => array_map(
                fn (ApplicationStatus $status): array => ['value' => $status->value, 'label' => ucfirst($status->value)],
                ApplicationStatus::cases(),
            ),
            'filters' => [
                'statuses' => $statuses,
                'uses_targeted_resume' => $usesTargetedResume,
                'search' => $search,
            ],
            'ghostedAfterDays' => (int) config('resume.ghosted_after_days'),
        ]);
    }

    /**
     * Show the New Session form.
     */
    public function create(): InertiaResponse
    {
        return Inertia::render('resume/applications/Create', [
            'systems' => $this->payload->selectableSystems(),
            'defaultSystemId' => AiSystem::defaultForFeature('targeted-resume')?->id,
            'coverLetterDefaultId' => AiSystem::defaultForFeature('cover-letter')?->id,
            'resumeVersions' => $this->payload->resumeVersions(),
            'currentResumeVersionId' => ResumeVersion::current()->value('id'),
        ]);
    }

    /**
     * Record a job: either with an AI session that analyzes it, or as
     * already applied to with a main resume and no session.
     */
    public function store(StoreApplicationRequest $request, AnalysisSystemGuard $guard): JsonResponse
    {
        if ($request->wantsAnalysis()) {
            $refusal = $guard->refusal();

            if ($refusal !== null) {
                return response()->json(['error' => $refusal], 422);
            }

            $application = $this->applicationService->createForAnalysis(
                system: AiSystem::findOrFail($request->validated('ai_system_id')),
                jobDescription: $request->validated('job_description'),
                companyName: $request->validated('company_name'),
                position: $request->validated('job_title'),
                location: $request->validated('job_location'),
                jobUrlId: $request->validated('job_url_id'),
            );
        } else {
            $application = $this->applicationService->createApplied(
                jobDescription: $request->validated('job_description'),
                companyName: $request->validated('company_name'),
                position: $request->validated('job_title'),
                location: $request->validated('job_location'),
                jobUrlId: $request->validated('job_url_id'),
                resumeVersionId: $request->integer('resume_version_id') ?: null,
                occurredAt: $request->date('occurred_at'),
            );
        }

        return response()->json([
            'application_id' => $application->id,
            'redirect' => route('admin.resume.applications.show', $application),
        ]);
    }

    /**
     * Show an application's Discussion page: its chat (when it has an AI
     * session), job details, status history and documents.
     */
    public function show(Application $application): InertiaResponse
    {
        $application->load([
            'resumeVersion:id,version',
            'jobUrl:id,url',
            'statusUpdates',
            'targetedResume.resumeVersion:id,version',
            'conversation.aiSystem',
            'conversation.messages',
        ]);

        $conversation = $application->conversation;
        $messages = $conversation?->messages->where('role', '!=', 'system')->values() ?? collect();

        $shouldAutoStart = $conversation !== null
            && $messages->where('role', 'assistant')->isEmpty()
            && (bool) data_get($conversation->context, 'auto_start_pending', false);

        return Inertia::render('resume/applications/Show', [
            'application' => [
                'id' => $application->id,
                'status' => $application->status->value,
                'company_name' => $application->company_name,
                'position' => $application->position,
                'location' => $application->location,
                'job_description' => $application->job_description,
                'job_url' => $application->jobUrl?->url,
                'fit_score' => $application->fit_score,
                'fit_summary' => $application->fit_summary,
                'resume_version' => $application->resumeVersion ? [
                    'id' => $application->resumeVersion->id,
                    'version' => $application->resumeVersion->version,
                ] : null,
                'status_updates' => $this->payload->statusUpdates($application),
                'allowed_next_statuses' => $this->payload->allowedNextStatuses($application),
                'has_applied' => $application->statusUpdates->contains('status', ApplicationStatus::Applied),
            ],
            'conversation' => $conversation ? [
                'id' => $conversation->id,
                'status' => $conversation->status->value,
                'title' => $conversation->title,
                'context' => $conversation->context,
                'ai_system_id' => $conversation->aiSystem?->id,
                'ai_system_name' => $conversation->aiSystem?->name,
                'usage' => $this->payload->usage($conversation),
            ] : null,
            'messages' => $messages
                ->map(fn ($message): array => [
                    'role' => $message->role,
                    'content' => $message->content,
                    'metadata' => $message->metadata,
                    'created_at' => $message->created_at?->toIso8601String(),
                ])
                ->all(),
            'targetedResume' => $this->targetedResumeCard($application->targetedResume),
            'coverLetter' => $this->coverLetterCard($application->coverLetters()->latest()->first()),
            'shouldAutoStart' => $shouldAutoStart,
            'resumeVersions' => $this->payload->resumeVersions(),
            'currentResumeVersionId' => ResumeVersion::current()->value('id'),
            'systems' => $this->payload->selectableSystems(),
            'defaultSystemId' => AiSystem::defaultForFeature('targeted-resume')?->id,
            'ghostedAfterDays' => (int) config('resume.ghosted_after_days'),
        ]);
    }

    /**
     * Update the job details and fit assessment.
     */
    public function update(UpdateApplicationRequest $request, Application $application): JsonResponse
    {
        $this->applicationService->updateDetails($application, $request->details());

        return response()->json([
            'success' => true,
            'message' => 'Application details updated.',
        ]);
    }

    /**
     * Mark an application as applied, recording the resume that was sent.
     */
    public function apply(ApplyApplicationRequest $request, Application $application): JsonResponse
    {
        $this->applicationService->markApplied(
            $application,
            $request->integer('resume_version_id') ?: null,
            $request->date('occurred_at'),
        );

        return response()->json($this->payload->statusState($application));
    }

    /**
     * Pass on a job that has not been applied to.
     */
    public function pass(Application $application): JsonResponse
    {
        $this->applicationService->pass($application);

        return response()->json([
            'success' => true,
            'status' => $application->status->value,
            'redirect' => route('admin.resume.applications.index'),
        ]);
    }

    /**
     * Soft-delete an application and its AI session.
     */
    public function destroy(Application $application): RedirectResponse
    {
        $this->applicationService->delete($application);

        return redirect()->route('admin.resume.applications.index')
            ->with('success', 'Application deleted.');
    }

    /**
     * The statuses to filter by: every recognised value among `status[]`,
     * or a single scalar `status`.
     *
     * @return array<int, string>
     */
    private function requestedStatuses(Request $request): array
    {
        $known = array_column(ApplicationStatus::cases(), 'value');

        return array_values(array_unique(array_filter(
            Arr::wrap($request->input('status', [])),
            fn (mixed $status): bool => is_string($status) && in_array($status, $known, true),
        )));
    }

    private function requestedTargetedResumeFilter(Request $request): string
    {
        $value = $request->input('uses_targeted_resume');

        return in_array($value, [self::USES_TARGETED_RESUME_YES, self::USES_TARGETED_RESUME_NO], true)
            ? $value
            : self::USES_TARGETED_RESUME_ANY;
    }

    /**
     * When the application was last active: the later of its session's last
     * non-system message and its own last change. Reads only what the list
     * query eager-loaded.
     */
    private function lastActivityAt(Application $application): ?CarbonInterface
    {
        $lastMessageAt = $application->conversation?->last_message_at;
        $updatedAt = $application->updated_at;

        if ($lastMessageAt === null || $updatedAt === null) {
            return $lastMessageAt ?? $updatedAt;
        }

        return $lastMessageAt->greaterThan($updatedAt) ? $lastMessageAt : $updatedAt;
    }

    /**
     * @return array{
     *     id: int,
     *     company_name: string,
     *     position: string,
     *     location: ?string,
     *     status: string,
     *     fit_score: ?int,
     *     resume_version: ?string,
     *     targeted_resume_id: ?int,
     *     has_conversation: bool,
     *     messages_count: ?int,
     *     usage: ?array{input_tokens: ?int, output_tokens: ?int, total_tokens: ?int, cost_usd: ?float, synced_at: ?string},
     *     latest_status_update: ?array{status: string, occurred_at: ?string},
     *     last_activity_at: ?string
     * }
     */
    private function listRow(Application $application): array
    {
        $conversation = $application->conversation;
        $latestStatusUpdate = $application->latestStatusUpdate;

        return [
            'id' => $application->id,
            'company_name' => $application->company_name,
            'position' => $application->position,
            'location' => $application->location,
            'status' => $application->status->value,
            'fit_score' => $application->fit_score,
            'resume_version' => $application->resumeVersion?->version,
            'targeted_resume_id' => $application->targeted_resume_id,
            'has_conversation' => $conversation !== null,
            'messages_count' => $conversation?->messages_count,
            'usage' => $conversation instanceof AiConversation ? $this->payload->usage($conversation) : null,
            'latest_status_update' => $latestStatusUpdate ? [
                'status' => $latestStatusUpdate->status->value,
                'occurred_at' => $latestStatusUpdate->occurred_at?->toDateString(),
            ] : null,
            'last_activity_at' => $this->lastActivityAt($application)?->diffForHumans(),
        ];
    }

    /**
     * @return array{id: int, title: ?string, tailored_content: ?string, docx_path: bool, pdf_path: bool, resume_version: ?string}|null
     */
    private function targetedResumeCard(?TargetedResume $targetedResume): ?array
    {
        if ($targetedResume === null) {
            return null;
        }

        return [
            'id' => $targetedResume->id,
            'title' => $targetedResume->title,
            'tailored_content' => data_get($targetedResume->tailored_data, 'markdown')
                ?? data_get($targetedResume->tailored_data, 'content'),
            'docx_path' => (bool) $targetedResume->docx_path,
            'pdf_path' => (bool) $targetedResume->pdf_path,
            'resume_version' => $targetedResume->resumeVersion?->version,
        ];
    }

    /**
     * @return array{id: int, company_name: ?string, position: ?string, docx_path: bool, pdf_path: bool}|null
     */
    private function coverLetterCard(?CoverLetter $coverLetter): ?array
    {
        if ($coverLetter === null) {
            return null;
        }

        return [
            'id' => $coverLetter->id,
            'company_name' => $coverLetter->company_name,
            'position' => $coverLetter->position,
            'docx_path' => $coverLetter->docxExists(),
            'pdf_path' => $coverLetter->pdfExists(),
        ];
    }
}
