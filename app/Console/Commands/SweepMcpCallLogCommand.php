<?php

namespace App\Console\Commands;

use App\Models\McpCall;
use Illuminate\Console\Command;

/**
 * Bounds the MCP call log.
 *
 * The endpoint is public and unauthenticated, so this table grows with traffic
 * nobody controls. Without a sweep it becomes the largest table in the
 * database and stays that way.
 */
class SweepMcpCallLogCommand extends Command
{
    protected $signature = 'mcp:sweep-call-log';

    protected $description = 'Delete MCP call records past the configured retention period';

    public function handle(): int
    {
        $retentionDays = (int) config('mcp-server.log_retention_days');

        if ($retentionDays < 1) {
            $this->warn('Retention is not a positive number of days; nothing swept.');

            return self::SUCCESS;
        }

        $cutoff = now()->subDays($retentionDays);

        $deleted = McpCall::query()
            ->where('created_at', '<', $cutoff)
            ->delete();

        $this->info("Deleted {$deleted} MCP call record(s) older than {$retentionDays} day(s).");

        return self::SUCCESS;
    }
}
