<?php

namespace Tests\Feature;

use App\Enums\AiConversationStatus;
use App\Jobs\ProcessAiMemoryJob;
use App\Models\AiConversation;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AiConversationObserverTest extends TestCase
{
    use DatabaseTransactions;

    public function test_completing_conversation_dispatches_memory_job(): void
    {
        Queue::fake();

        $conversation = AiConversation::factory()->create([
            'status' => AiConversationStatus::Active,
        ]);

        $conversation->update(['status' => AiConversationStatus::Completed]);

        Queue::assertPushed(ProcessAiMemoryJob::class, function ($job) use ($conversation) {
            return $job->conversation->id === $conversation->id;
        });
    }

    public function test_non_completion_status_changes_do_not_dispatch(): void
    {
        Queue::fake();

        $conversation = AiConversation::factory()->create([
            'status' => AiConversationStatus::Active,
        ]);

        $conversation->update(['status' => AiConversationStatus::Pass]);

        Queue::assertNotPushed(ProcessAiMemoryJob::class);
    }

    public function test_updating_non_status_fields_does_not_dispatch(): void
    {
        Queue::fake();

        $conversation = AiConversation::factory()->create([
            'status' => AiConversationStatus::Completed,
        ]);

        $conversation->update(['title' => 'Updated title']);

        Queue::assertNotPushed(ProcessAiMemoryJob::class);
    }
}
