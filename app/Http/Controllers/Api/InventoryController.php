<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\ExpiredStockException;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Services\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InventoryController extends Controller
{
    public function __construct(private InventoryService $inventory) {}

    public function alerts(): JsonResponse
    {
        return response()->json($this->inventory->lowStockAlerts());
    }

    public function expiryAlerts(): JsonResponse
    {
        return response()->json($this->inventory->expiryAlerts());
    }

    public function opname(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'notes' => 'nullable|string|max:2000',
            'items' => 'required|array|min:1|max:200',
            'items.*.product_unit_id' => 'required|integer|distinct|exists:product_units,id',
            'items.*.counted_qty' => 'required|integer|min:0',
        ]);

        try {
            $results = $this->inventory->opname(
                $request->user(),
                $validated['items'],
                $validated['notes'] ?? null,
            );
        } catch (ExpiredStockException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['results' => $results]);
    }

    public function movements(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'product_unit_id' => 'nullable|integer',
            'product_id' => 'nullable|integer',
            'from' => 'nullable|date',
            'to' => 'nullable|date',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        return response()->json($this->inventory->movements($filters));
    }

    public function restock(Request $request, ProductUnit $productUnit): JsonResponse
    {
        abort_unless($request->user()->isOwner(), 403);
        abort_unless(
            $productUnit->product()->where('tenant_id', $request->user()->tenant_id)->exists(),
            404,
        );

        $validated = $request->validate([
            'quantity' => 'required|integer|min:1',
            'notes' => 'nullable|string|max:500',
            'expired_at' => 'nullable|date',
            'batch_no' => 'nullable|string|max:100',
        ]);

        $movement = $this->inventory->restock(
            $productUnit,
            $request->user(),
            $validated['quantity'],
            $validated['notes'] ?? null,
            $validated['expired_at'] ?? null,
            $validated['batch_no'] ?? null,
        );

        return response()->json([
            'movement' => $movement->load('productUnit.product'),
            'unit' => $productUnit->fresh(['product']),
        ]);
    }

    public function receipts(Request $request): JsonResponse
    {
        abort_unless($request->user()->isOwner(), 403);

        $validated = $request->validate([
            'supplier_name' => 'nullable|string|max:255',
            'supplier_id' => [
                'nullable',
                'integer',
                Rule::exists('suppliers', 'id')->where('tenant_id', $request->user()->tenant_id),
            ],
            'notes' => 'nullable|string',
            'received_at' => 'nullable|date',
            'items' => 'required|array|min:1|max:200',
            'items.*.product_unit_id' => 'required|integer|exists:product_units,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_cost' => 'nullable|numeric|min:0',
            'items.*.expired_at' => 'nullable|date',
            'items.*.batch_no' => 'nullable|string|max:100',
        ]);

        $receipt = $this->inventory->receiveBatch($request->user(), $validated);

        return response()->json($receipt, 201);
    }

    public function lookup(Request $request): JsonResponse
    {
        abort_unless($request->user()->isOwner(), 403);

        $validated = $request->validate([
            'barcode' => 'required|string|max:100',
        ]);

        $product = Product::query()
            ->where('barcode', $validated['barcode'])
            ->with(['category', 'units'])
            ->first();

        if (! $product) {
            return response()->json(['message' => 'Produk tidak ditemukan.'], 404);
        }

        return response()->json($product);
    }
}
