<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function __construct(private ReportService $reportService) {}

    public function daily(Request $request): JsonResponse
    {
        return response()->json($this->reportService->daily($request->query('date')));
    }

    public function dailyTrend(Request $request): JsonResponse
    {
        $request->validate([
            'from' => 'required|date',
            'to' => 'required|date|after_or_equal:from',
        ]);

        return response()->json($this->reportService->dailyTrend($request->from, $request->to));
    }

    public function staff(Request $request): JsonResponse
    {
        $request->validate([
            'staff_id' => 'required',
            'from' => 'required|date',
            'to' => 'required|date|after_or_equal:from',
        ]);

        if ($request->staff_id === 'all') {
            return response()->json($this->reportService->allStaff($request->from, $request->to));
        }

        $request->validate(['staff_id' => 'integer|exists:users,id']);

        return response()->json($this->reportService->byStaff(
            (int) $request->staff_id,
            $request->from,
            $request->to
        ));
    }

    public function product(Request $request): JsonResponse
    {
        $request->validate([
            'product_id' => 'required',
            'from' => 'required|date',
            'to' => 'required|date|after_or_equal:from',
        ]);

        if ($request->product_id === 'all') {
            return response()->json($this->reportService->allProducts($request->from, $request->to));
        }

        $request->validate(['product_id' => 'integer|exists:products,id']);

        return response()->json($this->reportService->byProduct(
            (int) $request->product_id,
            $request->from,
            $request->to
        ));
    }

    public function status(): JsonResponse
    {
        return response()->json($this->reportService->byStatus());
    }

    public function tax(Request $request): JsonResponse
    {
        $year = (int) $request->query('year', now()->year);

        return response()->json($this->reportService->taxReport($year));
    }
}
