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

        // Delete in bounded batches (by id subquery) so the first run on a
        // large table does not hold a long lock; same retention semantics.
        $deleted = 0;
        $batchSize = 1000;

        do {
            $ids = Activity::query()
                ->where('created_at', '<', $cutoff)
                ->where(function ($query) {
                    $query->whereNull('properties->important')
                        ->orWhere('properties->important', '!=', true);
                })
                ->limit($batchSize)
                ->pluck('id');

            if ($ids->isEmpty()) {
                break;
            }

            $deleted += Activity::query()->whereIn('id', $ids)->delete();
        } while ($ids->count() >= $batchSize);

        $this->info("Deleted {$deleted} activity log records older than {$cutoff->toDateString()}.");

        return self::SUCCESS;
    }
}
