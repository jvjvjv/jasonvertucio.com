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

#[Name('save-cover-letter')]
#[Description('Save the finalized cover letter and generate DOCX and PDF. Call this when the user approves the cover letter.')]
class SaveCoverLetterTool extends AuthorizedResumeTool
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
            'cover_letter_content' => $schema->string()->required(),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        if ($response = $this->guard()) {
            return $response;
        }

        $content = (string) ($request->get('cover_letter_content') ?? '');

        if ($content === '') {
            return Response::error('cover_letter_content must not be empty.');
        }

        $coverLetter = $this->targetedResumeService->saveCoverLetter($this->context->conversation, $content);

        return Response::structured([
            'success' => true,
            'cover_letter_id' => $coverLetter->id,
            '_page_reload' => true,
        ]);
    }
}
