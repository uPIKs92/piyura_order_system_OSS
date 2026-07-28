<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\OrdersImportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ProcessOrdersImportJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $path,
        public int $userId,
        public string $mapping = 'auto',
    ) {}

    public function handle(OrdersImportService $importService): void
    {
        $user = User::findOrFail($this->userId);

        if ($user->tenant_id) {
            app()->instance('currentTenantId', $user->tenant_id);
        }

        $mapping = $this->mapping === 'auto'
            ? $importService->detectMapping($this->path)
            : $this->mapping;

        $result = $importService->importFile($this->path, $user, $mapping);

        if ($result['errors']) {
            $report = $importService->writeErrorReport($result['errors']);
            Log::info('Orders import completed with errors', [
                'report' => $report,
                'summary' => $result,
            ]);
        }
    }
}
