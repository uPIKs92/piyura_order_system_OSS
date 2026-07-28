<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Spatie\Activitylog\Models\Activity;

class ActivityLogCleanRetention extends Command
{
    protected $signature = 'activitylog:clean-retention {--days=90 : Delete logs older than this many days}';

    protected $description = 'Delete activity logs older than retention period, keeping important entries';

    public function handle(): int
    {
        $cutoff = now()->subDays((int) $this->option('days'));

        $deleted = Activity::query()
            ->where('created_at', '<', $cutoff)
            ->where(function ($query) {
                $query->whereNull('properties->important')
                    ->orWhere('properties->important', '!=', true);
            })
            ->delete();

        $this->info("Deleted {$deleted} activity log records older than {$cutoff->toDateString()}.");

        return self::SUCCESS;
    }
}
