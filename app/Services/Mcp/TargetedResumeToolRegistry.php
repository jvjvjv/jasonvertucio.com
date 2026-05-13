<?php

namespace App\Services\Mcp;

use App\Contracts\Mcp\AiToolHandlerContract;
use App\Contracts\Mcp\AiToolRegistryContract;
use App\Contracts\ResumeDataServiceContract;
use App\Models\AiConversation;
use App\Services\AiMemoryService;
use App\Services\Mcp\Tools\TargetedResume\GetJobDescriptionTool;
use App\Services\Mcp\Tools\TargetedResume\GetResumeDataTool;
use App\Services\Mcp\Tools\TargetedResume\GetResumeMemoriesTool;
use App\Services\Mcp\Tools\TargetedResume\SaveCoverLetterTool;
use App\Services\Mcp\Tools\TargetedResume\SaveTailoredResumeTool;
use App\Services\Mcp\Tools\TargetedResume\UpdateFitAssessmentTool;
use App\Services\TargetedResumeService;

class TargetedResumeToolRegistry implements AiToolRegistryContract
{
    /** @var array<string, AiToolHandlerContract> */
    private array $handlers;

    public function __construct(
        AiConversation $conversation,
        ResumeDataServiceContract $resumeDataService,
        AiMemoryService $memoryService,
        TargetedResumeService $targetedResumeService,
    ) {
        $handlers = [
            new GetResumeDataTool($resumeDataService),
            new GetJobDescriptionTool($conversation),
            new GetResumeMemoriesTool($memoryService, $conversation->user_id),
            new UpdateFitAssessmentTool($conversation),
            new SaveTailoredResumeTool($conversation, $targetedResumeService),
            new SaveCoverLetterTool($conversation, $targetedResumeService),
        ];

        foreach ($handlers as $handler) {
            $this->handlers[$handler->name()] = $handler;
        }
    }

    /**
     * @return array<int, array{name: string, description: string, input_schema: array<string, mixed>}>
     */
    public function toApiTools(): array
    {
        $descriptions = [
            'get_resume_data' => 'Load the candidate\'s full resume data (experience, skills, education, projects) before tailoring.',
            'get_job_description' => 'Load the job description and any known job title or company name from the conversation context.',
            'get_resume_memories' => 'Load learned preferences and insights from previous sessions with this user.',
            'update_fit_assessment' => 'Persist the fit score, fit summary, company name, and job title to the conversation. Call this after Step 4.',
            'save_tailored_resume' => 'Save the finalized tailored resume, generate DOCX and PDF, and mark the conversation completed. Call this when the user approves the resume.',
            'save_cover_letter' => 'Save the finalized cover letter and generate DOCX and PDF. Call this when the user approves the cover letter.',
        ];

        return array_values(array_map(
            static fn (AiToolHandlerContract $handler): array => [
                'name' => $handler->name(),
                'description' => $descriptions[$handler->name()] ?? $handler->name(),
                'input_schema' => $handler->schema(),
            ],
            $this->handlers,
        ));
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function dispatch(string $toolName, array $input): array
    {
        if (!isset($this->handlers[$toolName])) {
            return ['error' => "Unknown tool: {$toolName}"];
        }

        return $this->handlers[$toolName]->handle($input);
    }
}
