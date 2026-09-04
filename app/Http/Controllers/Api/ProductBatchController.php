<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProductBatch;
use App\Services\BatchService;
use App\Support\ExpirySettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class ProductBatchController extends Controller
{
    public function __construct(private BatchService $batches) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_unit_id' => 'nullable|integer',
            'product_id' => 'nullable|integer',
            'filter' => 'nullable|string|in:all,expiring,expired',
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:100',
        ]);

        $filter = $validated['filter'] ?? 'all';
        $today = today(config('app.timezone'));
        $alertLimit = $today->copy()->addDays(ExpirySettings::alertDays())->toDateString();

        $batches = ProductBatch::query()
            ->where('qty', '>', 0)
            ->with('productUnit.product.category')
            ->when(! empty($validated['product_unit_id']), fn ($q) => $q->where('product_unit_id', $validated['product_unit_id']))
            ->when(! empty($validated['product_id']), fn ($q) => $q->whereHas('productUnit', fn ($u) => $u->where('product_id', $validated['product_id'])))
            ->when($filter === 'expired', fn ($q) => $q->whereNotNull('expired_at')->whereDate('expired_at', '<', $today->toDateString()))
            ->when($filter === 'expiring', fn ($q) => $q
                ->whereNotNull('expired_at')
                ->whereDate('expired_at', '>=', $today->toDateString())
                ->whereDate('expired_at', '<=', $alertLimit))
            ->orderByRaw('expired_at IS NULL ASC')
            ->orderBy('expired_at')
            ->orderBy('id')
            ->paginate((int) ($validated['per_page'] ?? 20));

        return response()->json($batches->through(fn (ProductBatch $batch) => $this->present($batch)));
    }

    public function writeOff(Request $request, ProductBatch $productBatch): JsonResponse
    {
        $validated = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        try {
            $this->batches->writeOff($productBatch, $request->user(), $validated['reason'] ?? null);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($this->present($productBatch->fresh()));
    }

    private function present(ProductBatch $batch): array
    {
        return [
            'id' => $batch->id,
            'product_unit_id' => $batch->product_unit_id,
            'product_id' => $batch->productUnit?->product_id,
            'product_name' => $batch->productUnit?->product?->nama,
            'category_name' => $batch->productUnit?->product?->category?->nama,
            'satuan' => $batch->productUnit?->satuan,
            'batch_no' => $batch->batch_no,
            'expired_at' => $batch->expired_at?->toDateString(),
            'qty' => (int) $batch->qty,
        ];
    }
}
