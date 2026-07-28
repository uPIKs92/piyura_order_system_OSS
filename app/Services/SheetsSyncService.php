<?php

namespace App\Services;

use App\Models\Order;
use App\Models\SheetsSyncOutbox;
use App\Support\TenantSettings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SheetsSyncService
{
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
        $batch = SheetsSyncOutbox::withoutGlobalScopes()
            ->where('status', 'pending')
            ->orderBy('created_at')
            ->limit(config('google.batch_size', 50))
            ->get();

        $sent = 0;

        foreach ($batch as $record) {
            if (! $this->sheets->isReadyForSync((int) $record->tenant_id)) {
                continue;
            }

            try {
                $this->appendPayload($record);
                $record->update(['status' => 'sent', 'sent_at' => now()]);
                $sent++;
            } catch (\Throwable $e) {
                $this->markFailed($record, $e->getMessage());
            }
        }

        return $sent;
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

        $this->sheets->ensureHeaderRow((int) $record->tenant_id, $spreadsheetId, $tab, $headers);

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
