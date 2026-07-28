<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DraftAutosave;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DraftController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'draft_data' => 'required|array|max:100',
        ]);

        $draft = DraftAutosave::updateOrCreate(
            ['user_id' => $request->user()->id, 'is_restored' => false],
            ['draft_data' => $validated['draft_data']]
        );

        return response()->json($draft);
    }

    public function restore(Request $request): JsonResponse
    {
        $draft = DraftAutosave::where('user_id', $request->user()->id)
            ->where('is_restored', false)
            ->latest()
            ->first();

        if (! $draft) {
            return response()->json(['draft_data' => null]);
        }

        return response()->json(['draft_data' => $draft->draft_data]);
    }

    public function destroy(Request $request): JsonResponse
    {
        DraftAutosave::where('user_id', $request->user()->id)->delete();

        return response()->json(['message' => 'Draft cleared.']);
    }
}
