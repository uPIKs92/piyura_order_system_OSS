<?php

namespace App\Services;

use App\Enums\StockMovementType;
use App\Models\ProductUnit;
use App\Models\StockMovement;
use App\Models\StockReceipt;
use App\Models\StockReceiptLine;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use App\Support\TenantProductUnitGuard;
use Illuminate\Support\Facades\DB;

class InventoryService
{
    public function __construct(private StockMovementService $stockMovement) {}

    /**
     * @return array{count: int, items: Collection<int, array<string, mixed>>}
     */
    public function lowStockAlerts(): array
    {
        $items = ProductUnit::query()
            ->whereHas('product', fn ($q) => $q->where('is_active', true))
            ->whereColumn('stok', '<=', 'min_stok')
            ->with('product.category')
            ->orderBy('stok')
            ->get()
            ->map(fn (ProductUnit $unit) => [
                'product_unit_id' => $unit->id,
                'product_id' => $unit->product_id,
                'product_name' => $unit->product?->nama,
                'category_name' => $unit->product?->category?->nama,
                'satuan' => $unit->satuan,
                'stok' => $unit->stok,
                'min_stok' => $unit->min_stok,
                'is_out_of_stock' => $unit->stok === 0,
            ]);

        return [
            'count' => $items->count(),
            'items' => $items->values(),
        ];
    }

    public function movements(array $filters = []): LengthAwarePaginator
    {
        $query = StockMovement::query()
            ->with(['productUnit.product', 'creator:id,name'])
            ->latest('created_at');

        if (! empty($filters['product_unit_id'])) {
            $query->where('product_unit_id', $filters['product_unit_id']);
        }

        if (! empty($filters['product_id'])) {
            $query->whereHas('productUnit', fn ($q) => $q->where('product_id', $filters['product_id']));
        }

        if (! empty($filters['from'])) {
            $query->whereDate('created_at', '>=', $filters['from']);
        }

        if (! empty($filters['to'])) {
            $query->whereDate('created_at', '<=', $filters['to']);
        }

        return $query->paginate((int) ($filters['per_page'] ?? 50));
    }

    public function restock(ProductUnit $unit, User $user, int $quantity, ?string $notes = null): StockMovement
    {
        return $this->stockMovement->increment(
            $unit,
            $quantity,
            StockMovementType::Restock,
            $user,
            $notes,
        );
    }

    /**
     * @param  array{supplier_name?: string, notes?: string, received_at?: string, items: array<int, array{product_unit_id: int, quantity: int, unit_cost?: float}>}  $data
     */
    public function receiveBatch(User $user, array $data): StockReceipt
    {
        return DB::transaction(function () use ($user, $data) {
            $receipt = StockReceipt::create([
                'tenant_id' => $user->tenant_id,
                'receipt_no' => $this->generateReceiptNo(),
                'supplier_name' => $data['supplier_name'] ?? null,
                'notes' => $data['notes'] ?? null,
                'received_at' => $data['received_at'] ?? now(),
                'created_by' => $user->id,
            ]);

            foreach ($data['items'] as $line) {
                $unit = TenantProductUnitGuard::findForTenant($user->tenant_id, (int) $line['product_unit_id']);

                StockReceiptLine::create([
                    'stock_receipt_id' => $receipt->id,
                    'product_unit_id' => $unit->id,
                    'quantity' => $line['quantity'],
                    'unit_cost' => $line['unit_cost'] ?? null,
                ]);

                $this->stockMovement->increment(
                    $unit,
                    (int) $line['quantity'],
                    StockMovementType::Receive,
                    $user,
                    $data['notes'] ?? null,
                    $receipt,
                );
            }

            return $receipt->load('lines.productUnit.product');
        });
    }

    private function generateReceiptNo(): string
    {
        $date = now()->format('Ymd');
        $prefix = "RCV-{$date}-";
        $last = StockReceipt::where('receipt_no', 'like', $prefix.'%')
            ->orderByDesc('id')
            ->value('receipt_no');

        $seq = $last ? ((int) substr($last, -3)) + 1 : 1;

        return $prefix.str_pad((string) $seq, 3, '0', STR_PAD_LEFT);
    }
}
