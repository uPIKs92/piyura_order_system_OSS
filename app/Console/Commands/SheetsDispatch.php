<?php

namespace App\Console\Commands;

use App\Services\SheetsSyncService;
use Illuminate\Console\Command;

class SheetsDispatch extends Command
{
    protected $signature = 'sheets:dispatch';

    protected $description = 'Dispatch pending Google Sheets sync outbox records';

    public function handle(SheetsSyncService $sheetsSync): int
    {
        $sent = $sheetsSync->dispatchPending();
        $this->info("Dispatched {$sent} records.");

        return self::SUCCESS;
    }
}
