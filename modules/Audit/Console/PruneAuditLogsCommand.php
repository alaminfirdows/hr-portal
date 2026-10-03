<?php

namespace Modules\Audit\Console;

use Illuminate\Console\Command;
use Modules\Audit\Models\AuditLog;

final class PruneAuditLogsCommand extends Command
{
    protected $signature = 'audit:prune {--days=730 : Keep entries newer than this many days}';

    protected $description = 'Delete audit log entries older than the retention period';

    public function handle(): int
    {
        $count = AuditLog::query()->where('created_at', '<', now()->subDays((int) $this->option('days')))->delete();

        $this->info("Pruned {$count} audit entries.");

        return self::SUCCESS;
    }
}
