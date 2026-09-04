<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(User::class);
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:100',
        ]);

        return response()->json(
            User::where('tenant_id', auth()->user()->tenant_id)
                ->orderBy('id')
                ->paginate((int) ($validated['per_page'] ?? 20))
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => [
                'required',
                'email',
                Rule::unique('users', 'email')->where('tenant_id', $request->user()->tenant_id),
            ],
            'password' => 'required|string|min:8',
            'role' => ['sometimes', Rule::enum(UserRole::class)],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $validated['password'] = Hash::make($validated['password']);
        $validated['role'] = $validated['role'] ?? UserRole::Staff->value;
        $validated['is_active'] = $validated['is_active'] ?? true;
        $validated['tenant_id'] = $request->user()->tenant_id;

        $user = User::create($validated);

        return response()->json($user, 201);
    }

    public function show(User $user): JsonResponse
    {
        return response()->json($user);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => [
                'sometimes',
                'email',
                Rule::unique('users', 'email')
                    ->where('tenant_id', $request->user()->tenant_id)
                    ->ignore($user),
            ],
            'password' => 'sometimes|string|min:8',
            'role' => ['sometimes', Rule::enum(UserRole::class)],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if ($user->id === $request->user()->id
            && (isset($validated['role']) || isset($validated['is_active']))) {
            return response()->json([
                'message' => 'You cannot change your own role or active status.',
            ], 422);
        }

        $removesActiveOwner = $user->isOwner()
            && $user->is_active
            && ((isset($validated['role']) && $validated['role'] !== UserRole::Owner->value)
                || (isset($validated['is_active']) && ! $validated['is_active']));

        if ($removesActiveOwner) {
            $activeOwners = User::query()
                ->where('tenant_id', $user->tenant_id)
                ->where('role', UserRole::Owner->value)
                ->where('is_active', true)
                ->count();

            if ($activeOwners <= 1) {
                return response()->json([
                    'message' => 'Cannot demote or deactivate the last active owner.',
                ], 422);
            }
        }

        if (isset($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        }

        $user->update($validated);

        if (isset($validated['password'])
            || (isset($validated['is_active']) && ! $validated['is_active'])) {
            DB::table('sessions')->where('user_id', $user->id)->delete();
        }

        return response()->json($user);
    }

    public function destroy(User $user): JsonResponse
    {
        if ($user->isOwner()) {
            return response()->json(['message' => 'Cannot delete owner.'], 403);
        }

        $user->delete();

        return response()->json(['message' => 'Deleted.'], 200);
    }
}
