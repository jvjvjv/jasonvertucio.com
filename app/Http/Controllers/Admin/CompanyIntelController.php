<?php

namespace App\Http\Controllers\Admin;

use App\Models\Company;
use App\Models\RoleIntel;
use App\Services\AiSession\SessionSystemSwitcher;
use App\Services\CompanyIntel\CompanyIntelSystems;
use App\Support\CompanyIntelPayload;
use Illuminate\Database\Eloquent\Builder;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * The Company Intel pages: the list of intel sessions and one session's
 * page. Intel is never started here — only from an application.
 */
class CompanyIntelController extends BaseAdminController
{
    public function __construct(
        private CompanyIntelPayload $payload,
    ) {}

    public function index(): InertiaResponse
    {
        $intel = RoleIntel::query()
            ->whereHas('application')
            ->with(['application:id,company_name,position', 'company:id,name', 'conversation.aiSystem:id,name'])
            ->latest('updated_at')
            ->latest('id')
            ->get()
            ->map(fn (RoleIntel $roleIntel): array => [
                'id' => $roleIntel->id,
                'status' => $roleIntel->status->value,
                'company_name' => $roleIntel->company?->name,
                'application' => [
                    'id' => $roleIntel->application->id,
                    'company_name' => $roleIntel->application->knownCompanyName(),
                    'position' => $roleIntel->application->knownPosition(),
                ],
                'ai_system_name' => $roleIntel->conversation?->aiSystem?->name,
                'updated_at' => $roleIntel->updated_at?->toIso8601String(),
            ]);

        return Inertia::render('employment/company-intel/Index', [
            'intel' => $intel,
        ]);
    }

    public function show(RoleIntel $roleIntel, SessionSystemSwitcher $switcher, CompanyIntelSystems $systems): InertiaResponse
    {
        abort_if($roleIntel->application === null, 404);

        $roleIntel->load(['conversation.messages', 'conversation.aiSystem']);
        $conversation = $roleIntel->conversation;
        $messages = $conversation?->messages->where('role', '!=', 'system')->values() ?? collect();

        $rejectedDomains = array_filter(array_column($roleIntel->rejected_companies ?? [], 'domain'));
        $rejectedNames = array_map('mb_strtolower', array_column($roleIntel->rejected_companies ?? [], 'name'));

        return Inertia::render('employment/company-intel/Show', [
            'intel' => $this->payload->roleIntel($roleIntel),
            'conversation' => $conversation !== null ? [
                'id' => $conversation->id,
                'ai_system_id' => $conversation->ai_system_id,
                'ai_system_name' => $conversation->aiSystem?->name,
                'can_switch' => $switcher->canSwitch($conversation, $systems),
            ] : null,
            'messages' => $messages
                ->map(fn ($message): array => [
                    'role' => $message->role,
                    'content' => $message->content,
                    'metadata' => $message->metadata,
                    'created_at' => $message->created_at?->toIso8601String(),
                ])
                ->all(),
            'shouldAutoStart' => (bool) data_get($conversation?->context, 'auto_start_pending', false),
            'companyOptions' => fn () => Company::query()
                ->when($rejectedDomains !== [], fn (Builder $query) => $query->where(
                    fn (Builder $domains) => $domains->whereNull('website_domain')->orWhereNotIn('website_domain', $rejectedDomains),
                ))
                ->orderBy('name')
                ->get(['id', 'name', 'website_domain'])
                ->reject(fn (Company $company): bool => in_array(mb_strtolower($company->name), $rejectedNames, true))
                ->map(fn (Company $company): array => [
                    'id' => $company->id,
                    'name' => $company->name,
                    'website_domain' => $company->website_domain,
                ])
                ->values()
                ->all(),
        ]);
    }
}
