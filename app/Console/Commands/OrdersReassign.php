<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\User;
use Illuminate\Console\Command;

class OrdersReassign extends Command
{
    protected $signature = 'orders:reassign {--from=} {--to=}';

    protected $description = 'Reassign orders from one user to another';

    public function handle(): int
    {
        $from = $this->option('from');
        $to = $this->option('to');

        if (! $from || ! $to) {
            $this->error('Both --from and --to are required.');

            return self::FAILURE;
        }

        $count = Order::where('user_id', $from)->update(['user_id' => $to]);
        User::where('id', $from)->update(['is_active' => false]);

        $this->info("Reassigned {$count} orders from user {$from} to {$to}. Staff deactivated.");

        return self::SUCCESS;
    }
}
