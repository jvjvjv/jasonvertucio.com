<?php

namespace App\Listeners;

use App\Events\UserSecurityMethodRemoved;
use App\Models\SecurityAuditLogEntry;
use Illuminate\Contracts\Queue\ShouldQueue;

class LogSecurityAuditEntry implements ShouldQueue
{
    public function handle(UserSecurityMethodRemoved $event): void
    {
        SecurityAuditLogEntry::create([
            'user_id' => $event->user->id,
            'kind' => $event->kind,
            'removed_at' => $event->removedAt,
        ]);
    }
}
