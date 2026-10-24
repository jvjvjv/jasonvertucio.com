<?php

namespace App\Services\Mcp\Tools\CompanyIntel;

use App\Models\Company;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\DB;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('save-company-profile')]
#[Description('Record the hiring company and attach it to this role. Always include the company website: it is how the same company is recognised across applications. If the company is already known it is reused and only the fields you send are updated. Call again later to fill in fields you find afterwards.')]
class SaveCompanyProfileTool extends CompanyIntelTool
{
    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->description('The company\'s usual name.')->required(),
            'website' => $schema->string()->description('The company\'s own website, e.g. https://acme.com.'),
            'headquarters' => $schema->string()->description('City and country of the headquarters.'),
            'employee_count' => $schema->string()->description('Headcount or range, e.g. "1,001–5,000".'),
            'revenue' => $schema->string()->description('Annual revenue with its year, e.g. "$1.2B (FY2025)".'),
            'industry' => $schema->string(),
            'founded_year' => $schema->integer()->min(1600)->max((int) date('Y')),
            'ownership' => $schema->string()->enum(Company::OWNERSHIP_TYPES),
            'ticker' => $schema->string()->description('Stock ticker, when public.'),
            'summary' => $schema->string()->description('Two or three sentences on what the company does.'),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        if ($response = $this->guard()) {
            return $response;
        }

        $name = $this->text($request->get('name'));
        $website = $this->webUrl($this->text($request->get('website'), 2048));

        if ($name === null) {
            return Response::error('A company name is required.');
        }

        $roleIntel = $this->roleIntel();

        if ($roleIntel->hasRejected($name, $website)) {
            return Response::error("The user rejected {$name} as the company for this role. Do not attach it again; research the correct company instead.");
        }

        $attributes = $this->profileAttributes($request, $name, $website);
        $domain = Company::normalizeDomain($website);

        return DB::transaction(function () use ($roleIntel, $name, $website, $domain, $attributes): Response|ResponseFactory {
            $company = $roleIntel->company;

            if ($company !== null && $domain !== null && $company->website_domain !== null && $company->website_domain !== $domain) {
                return Response::error("{$company->name} is already attached to this role. If it is the wrong company, the user must mark it wrong before another can be attached.");
            }

            $company ??= Company::findMatching($name, $website);
            $created = $company === null;
            $company ??= new Company;

            if ($domain !== null && $company->website_domain === null) {
                $owner = Company::query()->where('website_domain', $domain)->whereKeyNot($company->getKey())->first();

                if ($owner !== null) {
                    return Response::error("Another company record ({$owner->name}) already uses {$domain}.");
                }

                $company->website_domain = $domain;
            }

            $company->fill($attributes);
            $company->researched_at = now();
            $company->save();

            $roleIntel->company()->associate($company);
            $roleIntel->save();

            return Response::structured([
                'success' => true,
                'company_id' => $company->id,
                'created' => $created,
                'updated' => array_keys($attributes),
            ]);
        });
    }

    /**
     * The website as an http(s) URL, a bare domain given a scheme. Anything
     * else (a `javascript:` URL, say) is dropped: the page renders it as a
     * link.
     */
    private function webUrl(?string $website): ?string
    {
        if ($website === null) {
            return null;
        }

        if (! preg_match('#^[a-z][a-z0-9+.-]*:#i', $website)) {
            $website = "https://{$website}";
        }

        $scheme = strtolower((string) parse_url($website, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https'], true) && Company::normalizeDomain($website) !== null ? $website : null;
    }

    /**
     * Only the fields sent are changed, so a later call can fill gaps
     * without erasing what was recorded before.
     *
     * @return array<string, mixed>
     */
    private function profileAttributes(Request $request, string $name, ?string $website): array
    {
        $attributes = ['name' => $name];

        if ($website !== null) {
            $attributes['website_url'] = $website;
        }

        foreach (['headquarters', 'employee_count', 'revenue', 'industry'] as $field) {
            if (($value = $this->text($request->get($field))) !== null) {
                $attributes[$field] = $value;
            }
        }

        if (($ticker = $this->text($request->get('ticker'), 20)) !== null) {
            $attributes['ticker'] = $ticker;
        }

        if (($summary = $this->text($request->get('summary'), null)) !== null) {
            $attributes['summary'] = $summary;
        }

        $foundedYear = $request->get('founded_year');

        if (is_numeric($foundedYear) && (int) $foundedYear >= 1600 && (int) $foundedYear <= (int) date('Y')) {
            $attributes['founded_year'] = (int) $foundedYear;
        }

        $ownership = $this->text($request->get('ownership'));

        if ($ownership !== null && in_array(strtolower($ownership), Company::OWNERSHIP_TYPES, true)) {
            $attributes['ownership'] = strtolower($ownership);
        }

        return $attributes;
    }
}
