<?php

namespace App\Console\Commands;

use App\Models\AiConversation;
use Illuminate\Console\Command;

class RegenerateChatHashes extends Command
{
    protected $signature = 'chat-hash:regenerate';

    protected $description = 'Regenerate chat hashes for all conversations using the current algorithm';

    public function handle(): int
    {
        $updated = 0;

        AiConversation::query()
            ->chunkById(100, function ($conversations) use (&$updated): void {
                foreach ($conversations as $conversation) {
                    $conversation->generateChatHash();
                    $updated++;
                }
            });

        $this->info("Updated {$updated} conversation(s).");

        return self::SUCCESS;
    }
}
