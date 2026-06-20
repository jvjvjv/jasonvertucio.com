<?php

namespace App\Services\Mcp\Tools\TargetedResume;

use App\Services\TargetedResumeService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Jvjvjv\CodeTalker\Support\ToolContext;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('save-tailored-resume')]
#[Description('Save the finalized tailored resume, generate DOCX and PDF, and mark the conversation completed. Call this when the user approves the resume.')]
class SaveTailoredResumeTool extends AuthorizedResumeTool
{
    public function __construct(
        ToolContext $context,
        private TargetedResumeService $targetedResumeService,
    ) {
        parent::__construct($context);
    }

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'tailored_content' => $schema->string()->required(),
            'fit_score' => $schema->integer()->min(1)->max(100),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        if ($response = $this->guard()) {
            return $response;
        }

        $tailoredContent = (string) ($request->get('tailored_content') ?? '');

        if ($tailoredContent === '') {
            return Response::error('tailored_content must not be empty.');
        }

        $fitScore = $request->get('fit_score') !== null ? (int) $request->get('fit_score') : null;

        $targetedResume = $this->targetedResumeService->saveTailoredResume(
            $this->context->conversation,
            $tailoredContent,
            $fitScore,
        );

        return Response::structured([
            'success' => true,
            'targeted_resume_id' => $targetedResume->id,
            'status' => $targetedResume->status->value,
            '_page_reload' => true,
        ]);
    }
}
