<?php

namespace App\Observers;

use App\Enums\AiConversationStatus;
use App\Jobs\ProcessAiMemoryJob;
use App\Models\AiConversation;

class AiConversationObserver
{
    /**
     * Handle the AiConversation "updated" event.
     */
    public function updated(AiConversation $aiConversation): void
    {
        if ($aiConversation->wasChanged('status')
            && $aiConversation->status === AiConversationStatus::Completed) {
            dispatch(new ProcessAiMemoryJob($aiConversation));
        }
    }
}
