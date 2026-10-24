<?php

namespace App\Services\Mcp\Tools\CompanyIntel;

use App\Models\Company;
use App\Models\CompanyInsight;
use App\Models\RoleIntel;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('get-intel-context')]
#[Description('Load the job being researched, the company already attached to it (with the insights already recorded), what is known about the role so far, and any companies the user has rejected for this role. Call this first.')]
class GetIntelContextTool extends CompanyIntelTool
{
    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        if ($response = $this->guard()) {
            return $response;
        }

        $roleIntel = $this->roleIntel();
        $roleIntel->loadMissing(['application', 'company.insights']);
        $application = $roleIntel->application;

        return Response::structured([
            'job' => [
                'title' => $application?->knownPosition(),
                'company_name' => $application?->knownCompanyName(),
                'location' => $application?->location,
                'description' => $application?->job_description,
            ],
            'company' => $roleIntel->company !== null ? $this->companyPayload($roleIntel->company) : null,
            'role' => collect(RoleIntel::FINDING_FIELDS)
                ->mapWithKeys(fn (string $field): array => [$field => $roleIntel->{$field}])
                ->all(),
            'rejected_companies' => $roleIntel->rejected_companies ?? [],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function companyPayload(Company $company): array
    {
        return [
            ...$company->only(Company::PROFILE_FIELDS),
            'website_domain' => $company->website_domain,
            'insights' => $company->insights
                ->map(fn (CompanyInsight $insight): array => [
                    'category' => $insight->category->value,
                    'title' => $insight->title,
                    'source_url' => $insight->source_url,
                ])
                ->all(),
        ];
    }
}
