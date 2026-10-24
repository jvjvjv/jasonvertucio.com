<?php

namespace App\Services\Mcp\Tools\CompanyIntel;

use App\Enums\CompanyInsightCategory;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('add-company-insight')]
#[Description('Record one finding about the attached company that would help someone applying or interviewing there. Record each finding separately and cite its source when there is one. The company must be recorded with save-company-profile first.')]
class AddCompanyInsightTool extends CompanyIntelTool
{
    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'category' => $schema->string()
                ->enum(array_map(fn (CompanyInsightCategory $category): string => $category->value, CompanyInsightCategory::cases()))
                ->required(),
            'title' => $schema->string()->description('A short headline for the finding.')->required(),
            'content' => $schema->string()->description('The finding, in Markdown.')->required(),
            'source_url' => $schema->string()->description('Where the finding came from, when known.'),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        if ($response = $this->guard()) {
            return $response;
        }

        $company = $this->roleIntel()->company;

        if ($company === null) {
            return Response::error('No company is attached to this role yet. Record it with save-company-profile first.');
        }

        $category = CompanyInsightCategory::tryFrom((string) $request->get('category'));
        $title = $this->text($request->get('title'));
        $content = $this->text($request->get('content'), null);

        if ($category === null || $title === null || $content === null) {
            return Response::error('An insight needs a valid category, a title and content.');
        }

        $sourceUrl = $this->text($request->get('source_url'), 2048);

        if ($sourceUrl !== null && ! preg_match('#^https?://#i', $sourceUrl)) {
            $sourceUrl = null;
        }

        $insight = $company->insights()->create([
            'category' => $category,
            'title' => $title,
            'content' => $content,
            'source_url' => $sourceUrl,
        ]);

        return Response::structured(['success' => true, 'insight_id' => $insight->id]);
    }
}
