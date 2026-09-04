<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Expense;
use App\Models\Order;
use App\Models\OrderReturn;
use App\Models\Product;
use App\Models\ReturnItem;
use App\Models\SheetsSyncOutbox;
use App\Models\StockMovement;
use App\Models\StockReceipt;
use App\Support\TenantSettings;
use Illuminate\Support\Facades\DB;

class TenantImportResetService
{
    /**
     * @return array{orders: int, returns: int, products: int, categories: int, stock_movements: int, stock_receipts: int, outbox: int, expenses: int}
     */
    public function resetBusinessData(int $tenantId): array
    {
        app()->instance('currentTenantId', $tenantId);

        $deleted = DB::transaction(function () use ($tenantId) {
            $orderIds = Order::withTrashed()
                ->where('tenant_id', $tenantId)
                ->pluck('id');

            $returnIds = OrderReturn::query()
                ->where('tenant_id', $tenantId)
                ->pluck('id');

            $returnItemsDeleted = 0;
            $returnsDeleted = 0;
            if ($returnIds->isNotEmpty()) {
                $returnItemsDeleted = ReturnItem::query()
                    ->whereIn('return_id', $returnIds)
                    ->delete();

                $returnsDeleted = OrderReturn::query()
                    ->whereIn('id', $returnIds)
                    ->delete();
            }

            $ordersDeleted = Order::withTrashed()
                ->where('tenant_id', $tenantId)
                ->forceDelete();

            $stockMovementsDeleted = StockMovement::query()
                ->where('tenant_id', $tenantId)
                ->delete();

            $stockReceiptsDeleted = StockReceipt::query()
                ->where('tenant_id', $tenantId)
                ->delete();

            $outboxDeleted = SheetsSyncOutbox::query()
                ->where('tenant_id', $tenantId)
                ->delete();

            $expensesDeleted = Expense::query()
                ->where('tenant_id', $tenantId)
                ->delete();

            $productsDeleted = Product::withTrashed()
                ->where('tenant_id', $tenantId)
                ->forceDelete();

            $categoriesDeleted = Category::withTrashed()
                ->where('tenant_id', $tenantId)
                ->forceDelete();

            TenantSettings::for($tenantId)->bumpSheetsImportEpoch();

            return [
                'orders' => $ordersDeleted,
                'return_items' => $returnItemsDeleted,
                'returns' => $returnsDeleted,
                'products' => $productsDeleted,
                'categories' => $categoriesDeleted,
                'stock_movements' => $stockMovementsDeleted,
                'stock_receipts' => $stockReceiptsDeleted,
                'outbox' => $outboxDeleted,
                'expenses' => $expensesDeleted,
            ];
        });

        OrderService::bumpAggregatesVersion($tenantId);

        return $deleted;
    }
}
