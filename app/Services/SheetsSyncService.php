<?php

namespace App\Services;

use App\Models\Order;
use App\Models\SheetsSyncOutbox;
use App\Support\TenantSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SheetsSyncService
{
    /** @var array<string, true> header rows already ensured for this run, keyed by tenant|spreadsheet|tab */
    private array $headersEnsured = [];

    public function __construct(private GoogleSheetsService $sheets) {}

    public function pushToOutbox(Order $order): SheetsSyncOutbox
    {
        $order->load(['items', 'payments']);

        return SheetsSyncOutbox::create([
            'tenant_id' => $order->tenant_id,
            'order_id' => $order->id,
            'idempotency_key' => (string) Str::uuid(),
            'payload' => $this->buildPayload($order),
            'status' => 'pending',
            'attempts' => 0,
        ]);
    }

    public function buildPayload(Order $order): array
    {
        return [
            'order_id' => $order->id,
            'invoice_no' => $order->invoice_no,
            'order_date' => $order->order_date?->toDateString(),
            'status' => $order->status->value,
            'subtotal' => (float) $order->subtotal,
            'ppn_amount' => (float) $order->ppn_amount,
            'grand_total' => (float) $order->grand_total,
            'total_paid' => (float) $order->total_paid,
            'items' => $order->items->map(fn ($i) => [
                'product_name' => $i->product_name,
                'quantity' => $i->quantity,
                'price' => (float) $i->price_snapshot,
                'subtotal' => (float) $i->subtotal,
            ])->values()->all(),
            'payments' => $order->payments->map(fn ($p) => [
                'amount' => (float) $p->amount,
                'metode' => $p->metode->value,
                'paid_at' => $p->paid_at?->toIso8601String(),
            ])->values()->all(),
            'created_at' => $order->created_at?->toIso8601String(),
        ];
    }

    public function dispatchPending(): int
    {
        return $this->processRecords(null)['sent'];
    }

    /**
     * @return array{sent: int, failed: int, pending: int, failed_total: int}
     */
    public function dispatchForTenant(int $tenantId): array
    {
        $counts = $this->processRecords($tenantId);

        $counts['pending'] = SheetsSyncOutbox::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('status', 'pending')
            ->count();

        $counts['failed_total'] = SheetsSyncOutbox::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('status', 'failed')
            ->count();

        return $counts;
    }

    public function retry(SheetsSyncOutbox $record): SheetsSyncOutbox
    {
        $record->update([
            'status' => 'pending',
            'attempts' => 0,
            'last_error' => null,
        ]);

        return $record->fresh();
    }

    /**
     * Atomically claim a batch of pending outbox rows, then append them.
     *
     * Claim semantics: rows move pending -> processing inside a transaction
     * using a locking read, so the scheduled `sheets:dispatch` runner and a
     * manual owner dispatch can never append the same row twice. Rows left in
     * 'processing' by a crashed runner are reclaimed once they exceed the
     * stale threshold. Claimed rows are released back to 'pending' (or moved
     * to 'failed' per the attempt budget) when the append fails or the tenant
     * is not ready for sync.
     *
     * Dedup semantics: the idempotency_key is never written to the sheet, so
     * sheet-side dedup by key is impossible with the current layout. Instead,
     * before appending we check the outbox itself for an already-sent row of
     * the same order with an identical payload fingerprint — an ambiguous
     * failure (append delivered, status update lost) therefore resolves to
     * "already delivered" and the row is marked sent without re-appending.
     * Distinct status transitions produce distinct payloads and still append
     * normally.
     *
     * @param  int|null  $tenantId  null claims across all tenants
     * @return array{sent: int, failed: int}
     */
    private function processRecords(?int $tenantId): array
    {
        $batch = $this->claimBatch($tenantId);

        $sent = 0;
        $failed = 0;

        foreach ($batch as $record) {
            if (! $this->sheets->isReadyForSync((int) $record->tenant_id)) {
                $record->update(['status' => 'pending']);
                continue;
            }

            try {
                if ($this->alreadyDelivered($record)) {
                    $record->update(['status' => 'sent', 'sent_at' => now(), 'last_error' => null]);
                    $sent++;

                    continue;
                }

                $this->appendPayload($record);
                $record->update(['status' => 'sent', 'sent_at' => now()]);
                $sent++;
            } catch (\Throwable $e) {
                $this->markFailed($record, $e->getMessage());
                $failed++;
            }
        }

        return ['sent' => $sent, 'failed' => $failed];
    }

    /**
     * Claim up to the batch size of pending rows (plus stale 'processing'
     * rows orphaned by a crashed runner) via a guarded, locking update.
     *
     * @return \Illuminate\Support\Collection<int, SheetsSyncOutbox>
     */
    private function claimBatch(?int $tenantId)
    {
        return DB::transaction(function () use ($tenantId) {
            $staleCutoff = now()->subMinutes((int) config('google.processing_stale_minutes', 10));

            $claimable = SheetsSyncOutbox::withoutGlobalScopes()
                ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
                ->where(function ($q) use ($staleCutoff) {
                    $q->where('status', 'pending')
                        ->orWhere(function ($stale) use ($staleCutoff) {
                            $stale->where('status', 'processing')
                                ->where('updated_at', '<', $staleCutoff);
                        });
                })
                ->orderBy('created_at')
                ->limit((int) config('google.batch_size', 50))
                ->lockForUpdate()
                ->get();

            if ($claimable->isEmpty()) {
                return $claimable;
            }

            SheetsSyncOutbox::withoutGlobalScopes()
                ->whereIn('id', $claimable->pluck('id'))
                ->update(['status' => 'processing']);

            // Re-read so the claimed models carry 'processing' as their
            // original state; later model updates that release the row back
            // to 'pending'/'sent'/'failed' are then persisted as dirty.
            return SheetsSyncOutbox::withoutGlobalScopes()
                ->whereIn('id', $claimable->pluck('id'))
                ->orderBy('created_at')
                ->get();
        });
    }

    /**
     * True when an already-sent row exists for the same order with an
     * identical payload fingerprint (see processRecords dedup semantics).
     */
    private function alreadyDelivered(SheetsSyncOutbox $record): bool
    {
        $fingerprint = md5(json_encode($record->payload ?? []));

        return SheetsSyncOutbox::withoutGlobalScopes()
            ->where('tenant_id', $record->tenant_id)
            ->where('order_id', $record->order_id)
            ->where('status', 'sent')
            ->where('id', '!=', $record->id)
            ->get()
            ->contains(fn ($sent) => md5(json_encode($sent->payload ?? [])) === $fingerprint);
    }

    private function appendPayload(SheetsSyncOutbox $record): void
    {
        $settings = TenantSettings::for((int) $record->tenant_id);
        $spreadsheetId = $settings->sheetsSpreadsheetId();
        $tab = $settings->sheetsReportingTab();
        $payload = $record->payload ?? [];

        $headers = [
            'invoice_no', 'order_date', 'status', 'subtotal', 'ppn_amount',
            'grand_total', 'total_paid', 'items_summary', 'synced_at',
        ];

        $cacheKey = $record->tenant_id.'|'.$spreadsheetId.'|'.$tab;
        if (! isset($this->headersEnsured[$cacheKey])) {
            $this->sheets->ensureHeaderRow((int) $record->tenant_id, $spreadsheetId, $tab, $headers);
            $this->headersEnsured[$cacheKey] = true;
        }

        $itemsSummary = collect($payload['items'] ?? [])
            ->map(fn ($i) => ($i['product_name'] ?? '').' x'.($i['quantity'] ?? 0))
            ->implode('; ');

        $this->sheets->appendRow((int) $record->tenant_id, $spreadsheetId, $tab, [
            $payload['invoice_no'] ?? '',
            $payload['order_date'] ?? '',
            $payload['status'] ?? '',
            $payload['subtotal'] ?? 0,
            $payload['ppn_amount'] ?? 0,
            $payload['grand_total'] ?? 0,
            $payload['total_paid'] ?? 0,
            $itemsSummary,
            now()->toIso8601String(),
        ]);
    }

    /**
     * Release a claimed (processing) row back to 'pending' for another
     * attempt, or to 'failed' once the attempt budget is exhausted.
     */
    private function markFailed(SheetsSyncOutbox $record, string $error): void
    {
        $attempts = $record->attempts + 1;
        $status = $attempts >= config('google.retry_attempts', 3) ? 'failed' : 'pending';

        $record->update([
            'attempts' => $attempts,
            'last_error' => $error,
            'status' => $status,
        ]);

        if ($status === 'failed') {
            Log::warning("Sheets sync failed for order #{$record->order_id}: {$error}");
            $this->notifyOwner($record, $error);
        }
    }

    private function notifyOwner(SheetsSyncOutbox $record, string $error): void
    {
        $token = config('google.telegram_bot_token');
        $chatId = config('google.telegram_chat_id');

        if (! $token || ! $chatId) {
            return;
        }

        $record->loadMissing('order');
        $invoiceNo = $record->order?->invoice_no
            ?? ($record->payload['invoice_no'] ?? 'N/A');

        $message = "⚠️ Sheets Sync Gagal: Order #{$record->order_id} ({$invoiceNo}) gagal sinkronisasi setelah {$record->attempts} percobaan. Error: {$error}";

        try {
            Http::timeout(10)->post("https://api.telegram.org/bot{$token}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $message,
            ]);
        } catch (\Throwable $e) {
            Log::error('Telegram notify failed: '.$e->getMessage());
        }
    }
}
