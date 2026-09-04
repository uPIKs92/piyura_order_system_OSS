<?php

namespace App\Http\Controllers\Api;

use App\Enums\ExpenseCategory;
use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExpenseController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:100',
            'from' => 'sometimes|date',
            'to' => 'sometimes|date',
        ]);

        $from = $validated['from'] ?? now()->toDateString();
        $to = $validated['to'] ?? now()->toDateString();

        return response()->json(
            Expense::query()
                ->whereDate('expense_date', '>=', $from)
                ->whereDate('expense_date', '<=', $to)
                ->orderByDesc('expense_date')
                ->orderByDesc('id')
                ->paginate((int) ($validated['per_page'] ?? 20))
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'expense_date' => 'required|date|before_or_equal:today',
            'amount' => 'required|numeric|gt:0',
            'category' => ['required', 'string', 'in:'.implode(',', ExpenseCategory::values())],
            'note' => 'nullable|string|max:255',
        ]);

        $expense = Expense::create($validated + ['user_id' => $request->user()->id]);

        OrderService::bumpAggregatesVersion($expense->tenant_id);

        return response()->json($expense->fresh(), 201);
    }

    public function update(Request $request, Expense $expense): JsonResponse
    {
        $validated = $request->validate([
            'expense_date' => 'sometimes|date|before_or_equal:today',
            'amount' => 'sometimes|numeric|gt:0',
            'category' => ['sometimes', 'string', 'in:'.implode(',', ExpenseCategory::values())],
            'note' => 'nullable|string|max:255',
        ]);

        $expense->update($validated);

        OrderService::bumpAggregatesVersion($expense->tenant_id);

        return response()->json($expense->fresh());
    }

    public function destroy(Expense $expense): JsonResponse
    {
        $tenantId = $expense->tenant_id;
        $expense->delete();

        OrderService::bumpAggregatesVersion($tenantId);

        return response()->json(['message' => 'Pengeluaran berhasil dihapus.']);
    }
}
