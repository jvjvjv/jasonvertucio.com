<?php

namespace App\Services\Mcp\Tools\TargetedResume;

use App\Contracts\Mcp\AiToolHandlerContract;
use App\Enums\TargetedResumeStatus;
use App\Models\AiConversation;

class MarkAppliedTool implements AiToolHandlerContract {
    public function __construct(private AiConversation $conversation) {
    }

    public function name(): string {
        return 'mark_applied';
    }

    public function description(): string {
        return 'Mark this job as applied with today\'s date. Only call this when the candidate confirms they have submitted an application. Does nothing if an applied date is already recorded.';
    }

    public function schema(): array {
        return [
            'type' => 'object',
            'properties' => (object) [],
            'required' => [],
        ];
    }

    public function handle(array $input): array {
        $targetedResume = $this->conversation->targetedResume;

        if ($targetedResume === null) {
            return ['error' => 'No finalized resume found for this conversation. Save the tailored resume first.'];
        }

        if ($targetedResume->applied_at !== null) {
            return [
                'success' => true,
                'already_applied' => true,
                'applied_at' => $targetedResume->applied_at->toDateString(),
            ];
        }

        $targetedResume->update([
            'status' => TargetedResumeStatus::Applied,
            'applied_at' => now(),
        ]);

        return [
            'success' => true,
            'applied_at' => now()->toDateString(),
            '_page_reload' => true,
        ];
    }
}
