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
    ) {
    }

    /**
     * Execute the job.
     */
    public function handle(AiMemoryService $memoryService): void
    {
        $memoryService->processCompletedConversation($this->conversation);
    }
}
