<?php

namespace App\Services\Mcp\Tools\TargetedResume;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('update-fit-assessment')]
#[Description('Persist the fit score, fit summary, company name, and job title to the conversation. Call this after Step 4.')]
class UpdateFitAssessmentTool extends AuthorizedResumeTool
{
    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'fit_score' => $schema->integer()->min(1)->max(100)->required(),
            'fit_summary' => $schema->string()->required(),
            'company_name' => $schema->string(),
            'job_title' => $schema->string(),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        if ($response = $this->guard()) {
            return $response;
        }

        $conversation = $this->context->conversation;
        $context = $conversation?->context ?? [];
        $updates = [];

        $fitScore = $request->get('fit_score') !== null ? (int) $request->get('fit_score') : null;

        if ($fitScore !== null && $fitScore >= 1 && $fitScore <= 100) {
            $updates['fit_score'] = $fitScore;
        }

        if ($request->get('fit_summary') !== null && $request->get('fit_summary') !== '') {
            $updates['fit_summary'] = (string) $request->get('fit_summary');
        }

        if ($request->get('company_name') !== null && $request->get('company_name') !== '') {
            $updates['company_name'] = (string) $request->get('company_name');
        }

        if ($request->get('job_title') !== null && $request->get('job_title') !== '') {
            $updates['job_title'] = (string) $request->get('job_title');
        }

        if ($updates !== [] && $conversation !== null) {
            $conversation->update(['context' => array_merge($context, $updates)]);
            $conversation->refresh();
        }

        return Response::structured(['success' => true, 'updated' => array_keys($updates)]);
    }
}
