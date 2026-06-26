<?php

namespace Wonchoe\GscManager\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Wonchoe\GscManager\Models\GscSearchAnalytic;
use Wonchoe\GscManager\Models\GscSyncLog;

class PruneGscDataCommand extends Command
{
    protected $signature = 'gsc:prune
        {--logs-days=90 : Delete sync logs older than N days}
        {--analytics-days= : Delete analytics rows older than N days (omit = keep all)}
        {--dry-run : Report what would be deleted without deleting}';

    protected $description = 'Prune old GSC sync logs and (optionally) analytics rows so the tables do not grow unbounded.';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $prefix = $dry ? '[dry-run] ' : '';

        $logDays = max(1, (int) $this->option('logs-days'));
        $logQuery = GscSyncLog::where('created_at', '<', Carbon::now()->subDays($logDays));
        $logCount = $logQuery->count();
        if (! $dry) {
            $logQuery->delete();
        }
        $this->info("{$prefix}sync logs older than {$logDays}d: {$logCount}");

        $analyticsDays = $this->option('analytics-days');
        if ($analyticsDays !== null && $analyticsDays !== '') {
            $aDays = max(1, (int) $analyticsDays);
            $aQuery = GscSearchAnalytic::whereNotNull('date')
                ->whereDate('date', '<', Carbon::now()->subDays($aDays)->toDateString());
            $aCount = $aQuery->count();
            if (! $dry) {
                $aQuery->delete();
            }
            $this->info("{$prefix}analytics rows older than {$aDays}d: {$aCount}");
        }

        return self::SUCCESS;
    }
}
