<?php

namespace App\Support;

use App\Models\Company;
use App\Models\CompanyInsight;
use App\Models\RoleIntel;

/**
 * The shapes the Company Intel pages and endpoints send to the browser.
 */
class CompanyIntelPayload
{
    /**
     * Everything the intel page shows beside the chat. Also the body of the
     * `state` endpoint the page polls while a run is in progress.
     *
     * @return array<string, mixed>
     */
    public function roleIntel(RoleIntel $roleIntel): array
    {
        $roleIntel->loadMissing(['company.insights', 'application', 'conversation.aiSystem']);
        $application = $roleIntel->application;

        return [
            'id' => $roleIntel->id,
            'status' => $roleIntel->status->value,
            'failure_reason' => $roleIntel->failure_reason,
            'application' => $application !== null ? [
                'id' => $application->id,
                'company_name' => $application->knownCompanyName(),
                'position' => $application->knownPosition(),
                'status' => $application->status->value,
            ] : null,
            'company' => $roleIntel->company !== null ? $this->company($roleIntel->company) : null,
            'role' => collect(RoleIntel::FINDING_FIELDS)
                ->mapWithKeys(fn (string $field): array => [$field => $roleIntel->{$field}])
                ->all(),
            'ai_system_name' => $roleIntel->conversation?->aiSystem?->name,
            'updated_at' => $roleIntel->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function company(Company $company): array
    {
        return [
            'id' => $company->id,
            'name' => $company->name,
            'website_url' => $company->website_url,
            'website_domain' => $company->website_domain,
            'headquarters' => $company->headquarters,
            'employee_count' => $company->employee_count,
            'revenue' => $company->revenue,
            'industry' => $company->industry,
            'founded_year' => $company->founded_year,
            'ownership' => $company->ownership,
            'ticker' => $company->ticker,
            'summary' => $company->summary,
            'researched_at' => $company->researched_at?->toIso8601String(),
            'insights' => collect($company->insightsByCategory())
                ->map(fn (array $insights, string $category): array => [
                    'category' => $category,
                    'label' => $insights[0]->category->label(),
                    'items' => array_map(fn (CompanyInsight $insight): array => [
                        'id' => $insight->id,
                        'title' => $insight->title,
                        'content' => $insight->content,
                        'source_url' => $insight->source_url,
                    ], $insights),
                ])
                ->values()
                ->all(),
        ];
    }
}
