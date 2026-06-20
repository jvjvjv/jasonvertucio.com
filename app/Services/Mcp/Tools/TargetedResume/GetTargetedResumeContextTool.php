<?php

namespace App\Services\Mcp\Tools\TargetedResume;

use App\Models\TargetedResume;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('get-targeted-resume-context')]
#[Description(
    'Load the full context for a targeted resume by conversation ID, company name, or job title. '
    . 'If more than one resume matches, return the candidate list so the user can choose which one to use.'
)]
class GetTargetedResumeContextTool extends AuthorizedResumeTool
{
    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'conversation_id' => $schema->integer()
                ->description('The conversation ID associated with the targeted resume.'),
            'company_name' => $schema->string()
                ->description("A company name to search for among the user's targeted resumes."),
            'job_title' => $schema->string()
                ->description("A job title or resume title to search for among the user's targeted resumes."),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        if ($response = $this->guard()) {
            return $response;
        }

        $matches = $this->buildQuery($request)->get();

        if ($matches->isEmpty()) {
            return Response::error('No targeted resume matched that conversation, company, or job title.');
        }

        if ($matches->count() > 1) {
            return Response::structured([
                'needs_selection' => true,
                'message' => 'More than one targeted resume matched. Ask which one to use and pass a narrower conversation ID, company name, or job title.',
                'matches' => $matches->map(fn (TargetedResume $resume): array => $this->serializeMatch($resume))->all(),
            ]);
        }

        return Response::structured($this->serializeContext($matches->first()));
    }

    private function buildQuery(Request $request): Builder
    {
        $conversationId = $request->get('conversation_id') !== null ? (int) $request->get('conversation_id') : null;
        $companyName = $request->get('company_name') !== null ? trim((string) $request->get('company_name')) : null;
        $jobTitle = $request->get('job_title') !== null ? trim((string) $request->get('job_title')) : null;

        $query = TargetedResume::query()
            ->whereHas('conversation', fn (Builder $builder): Builder => $builder->where('user_id', $this->context->conversation?->user_id))
            ->with('conversation')
            ->orderByDesc('id');

        if ($conversationId !== null) {
            $query->where('ai_conversation_id', $conversationId);
        }

        if ($companyName !== null && $companyName !== '') {
            $query->where('company_name', 'like', '%' . $companyName . '%');
        }

        if ($jobTitle !== null && $jobTitle !== '') {
            $query->where(function (Builder $builder) use ($jobTitle): void {
                $builder
                    ->where('position', 'like', '%' . $jobTitle . '%')
                    ->orWhere('title', 'like', '%' . $jobTitle . '%');
            });
        }

        return $query;
    }

    private function serializeMatch(TargetedResume $resume): array
    {
        return [
            'targeted_resume_id' => $resume->id,
            'conversation_id' => $resume->ai_conversation_id,
            'company_name' => $resume->company_name,
            'position' => $resume->position,
            'title' => $resume->title,
            'status' => $resume->status->value,
            'fit_score' => $resume->fit_score,
        ];
    }

    private function serializeContext(TargetedResume $resume): array
    {
        $coverLetter = $resume->coverLetters()->latest()->first();

        return [
            'job_description' => [
                'company' => $resume->company_name,
                'position' => $resume->position,
                'text' => $resume->job_description,
            ],
            'tailored_resume' => $resume->tailored_data['markdown'] ?? null,
            'cover_letter' => $coverLetter !== null ? [
                'greeting' => $coverLetter->greeting,
                'body' => $coverLetter->message_body,
                'closing' => $coverLetter->closing,
                'signature' => $coverLetter->signature,
            ] : null,
            'meta' => [
                'targeted_resume_id' => $resume->id,
                'conversation_id' => $resume->ai_conversation_id,
                'fit_score' => $resume->fit_score,
                'fit_summary' => $resume->fit_summary,
                'status' => $resume->status->value,
                'title' => $resume->title,
            ],
        ];
    }
}
