<?php

namespace App\Jobs;

use App\Models\AiConversation;
use App\Services\AiMemoryService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessAiMemoryJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 30;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public AiConversation $conversation,
        protected ?string $userId = null,
        protected ?string $visitorEmail = null,
    ) {
    }

    /**
     * Execute the job with user identity for memory scoping.
     */
    public function handle(AiMemoryService $memoryService): void
    {
        // Derive user identity from conversation if not explicitly provided
        $userId = $this->userId ?? $this->conversation->user_id;
        $visitorEmail = $this->visitorEmail ?? $this->conversation->visitor_email;

        $memoryService->processCompletedConversation(
            $this->conversation,
            $userId,
            $visitorEmail
        );
    }
}
