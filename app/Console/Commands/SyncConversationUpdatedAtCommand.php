<?php

namespace App\Console\Commands;

use App\Models\AiConversation;
use App\Models\AiConversationMessage;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class SyncConversationUpdatedAtCommand extends Command
{
    protected $signature = 'ai:sync-conversation-updated-at
        {--conversation-id= : Sync only one conversation}
        {--include-system : Include system messages when finding latest timestamp}
        {--chunk=500 : Number of conversations per processing batch}
        {--dry-run : Show what would change without writing}';

    protected $description = 'Sync ai_conversations.updated_at to each conversation\'s latest message timestamp.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $conversationId = $this->option('conversation-id');
        $includeSystem = (bool) $this->option('include-system');
        $chunkSize = max((int) $this->option('chunk'), 1);
        $dryRun = (bool) $this->option('dry-run');

        $query = AiConversation::query()->orderBy('id');

        if ($conversationId !== null) {
            $query->whereKey($conversationId);
        }

        $total = (clone $query)->count();

        if ($total === 0) {
            $this->warn('No conversations matched the provided filters.');

            return self::SUCCESS;
        }

        $processed = 0;
        $changed = 0;
        $skippedNoMessages = 0;

        $query->chunkById($chunkSize, function (Collection $conversations) use (
            &$processed,
            &$changed,
            &$skippedNoMessages,
            $includeSystem,
            $dryRun,
        ): void {
            $conversationIds = $conversations
                ->pluck('id')
                ->filter(fn ($id) => $id !== null)
                ->values()
                ->all();

            if ($conversationIds === []) {
                return;
            }

            $latestTimestamps = AiConversationMessage::query()
                ->selectRaw('ai_conversation_id, MAX(created_at) as latest_created_at')
                ->whereIn('ai_conversation_id', $conversationIds)
                ->when(! $includeSystem, function ($messageQuery): void {
                    $messageQuery->where('role', '!=', 'system');
                })
                ->groupBy('ai_conversation_id')
                ->pluck('latest_created_at', 'ai_conversation_id');

            foreach ($conversations as $conversation) {
                if (! $conversation instanceof AiConversation) {
                    continue;
                }

                $processed++;
                $latestTimestamp = $latestTimestamps->get($conversation->id);

                if ($latestTimestamp === null) {
                    $skippedNoMessages++;

                    continue;
                }

                $latest = (string) $latestTimestamp;

                if ($conversation->updated_at?->toDateTimeString() === $latest) {
                    continue;
                }

                $changed++;

                if ($dryRun) {
                    continue;
                }

                AiConversation::withoutTimestamps(function () use ($conversation, $latest): void {
                    $conversation->forceFill(['updated_at' => $latest])->save();
                });
            }
        });

        $this->info($dryRun ? 'Dry run complete.' : 'Conversation updated_at sync complete.');
        $this->line('Processed: ' . $processed);
        $this->line('Changed: ' . $changed);
        $this->line('Skipped (no messages): ' . $skippedNoMessages);

        return self::SUCCESS;
    }
}
