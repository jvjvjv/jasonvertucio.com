<?php

namespace App\Listeners;

use App\Events\UserSecurityMethodRemoved;
use App\Mail\SecurityMethodRemovedMail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Mail;

class AlertUserOfSecurityDowngrade implements ShouldQueue
{
    public function handle(UserSecurityMethodRemoved $event): void
    {
        Mail::to($event->user->email)->queue(
            new SecurityMethodRemovedMail($event->user->name, $event->kind)
        );
    }
}
