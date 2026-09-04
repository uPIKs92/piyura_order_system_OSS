<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Spatie\Activitylog\Models\Activity;

class ActivityLogController extends Controller
{
    public function forOrder(Request $request, Order $order): JsonResponse
    {
        $this->authorize('view', $order);

        $logs = Activity::query()
            ->where(function ($query) use ($order) {
                $query->where(function ($q) use ($order) {
                    $q->where('subject_type', Order::class)
                        ->where('subject_id', $order->id);
                })->orWhere(function ($q) use ($order) {
                    $q->where('subject_type', \App\Models\Payment::class)
                        ->whereIn('subject_id', $order->payments()->pluck('id'));
                });
            })
            ->with('causer:id,name,email')
            ->latest()
            ->limit(100)
            ->get()
            ->map(fn (Activity $log) => [
                'id' => $log->id,
                'description' => $log->description,
                'event' => $log->event,
                'properties' => $request->user()->isStaff()
                    ? $this->withoutCostKeys($log->properties)
                    : $log->properties,
                'created_at' => $log->created_at,
                'causer' => $log->causer?->only('id', 'name', 'email'),
            ]);

        return response()->json($logs);
    }

    private function withoutCostKeys(mixed $value): mixed
    {
        if ($value instanceof Collection) {
            $value = $value->all();
        }

        if (! is_array($value)) {
            return $value;
        }

        $filtered = [];

        foreach ($value as $key => $item) {
            if (in_array($key, ['cost_snapshot', 'harga_beli'], true)) {
                continue;
            }

            $filtered[$key] = $this->withoutCostKeys($item);
        }

        return $filtered;
    }
}
