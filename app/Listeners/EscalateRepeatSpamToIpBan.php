<?php

namespace App\Listeners;

use App\Enums\IpBanSource;
use App\Events\CommentMarkedAsSpam;
use App\Models\Comment;
use App\Models\IpBan;
use Illuminate\Contracts\Queue\ShouldQueue;

class EscalateRepeatSpamToIpBan implements ShouldQueue
{
    public function handle(CommentMarkedAsSpam $event): void
    {
        $config = config('comments.spam_escalation');
        $ip = $event->comment->ip_address;

        if (! $config['enabled'] || blank($ip)) {
            return;
        }

        if (IpBan::query()->where('ip', $ip)->exists()) {
            return;
        }

        $windowHours = $config['window_hours'];

        $spamCount = Comment::query()
            ->where('ip_address', $ip)
            ->where('is_spam', true)
            ->where('created_at', '>=', now()->subHours($windowHours))
            ->count();

        if ($spamCount < $config['threshold']) {
            return;
        }

        IpBan::create([
            'ip' => $ip,
            'source' => IpBanSource::SpamEscalation,
            'banned_method' => 'ESCALATION',
            'banned_url' => 'ESCALATION',
            'banned_body' => "Repeat spam: {$spamCount} spam comments in {$windowHours}h",
        ]);
    }
}
