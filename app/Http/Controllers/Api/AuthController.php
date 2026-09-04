<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use App\Support\LoginTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
            'device_name' => 'nullable|string|max:255',
        ]);

        $key = 'login_attempts:'.$request->ip().':'.$request->email.':'.intdiv(now()->timestamp, 300);
        $attempts = cache()->get($key, 0);

        if ($attempts >= 5) {
            throw ValidationException::withMessages([
                'email' => ['Too many login attempts. Please try again in 5 minutes.'],
            ]);
        }

        $tenant = LoginTenant::resolve($request);

        $userQuery = User::query()->where('email', $request->email);

        if ($tenant) {
            $userQuery->where('tenant_id', $tenant->id);
        }

        $user = $userQuery->with('tenant')->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            cache()->put($key, $attempts + 1, now()->addMinutes(10));
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if (isset($user->is_active) && ! $user->is_active) {
            throw ValidationException::withMessages([
                'email' => ['This account has been deactivated.'],
            ]);
        }

        if ($user->tenant?->is_suspended) {
            throw ValidationException::withMessages([
                'email' => ['This business account is suspended.'],
            ]);
        }

        cache()->forget($key);

        Auth::login($user);
        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        return response()->json([
            'user' => $this->formatUser($user),
            'tenant' => $user->tenant?->toBrandingArray(),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->json(['message' => 'Logged out successfully.']);
    }

    public function user(Request $request): JsonResponse
    {
        $user = $request->user()->load('tenant');

        return response()->json([
            'user' => $this->formatUser($user),
            'tenant' => $user->tenant?->toBrandingArray(),
        ]);
    }

    public function sendResetLink(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $request->validate([
            'email' => [
                'required',
                'email',
                Rule::exists('users', 'email')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
        ]);

        if (! $request->user()->isOwner()) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $user = User::query()
            ->where('tenant_id', $tenantId)
            ->where('email', $request->email)
            ->first();

        if (! $user || ! $user->isStaff()) {
            return response()->json(['message' => 'Can only reset staff passwords.'], 422);
        }

        $token = Password::broker()->createToken($user);
        $user->sendPasswordResetNotification($token);

        return response()->json(['message' => 'If the account exists, a reset link has been sent.']);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'token' => 'required|string',
            'email' => 'required|email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $tenant = Tenant::query()->orderBy('id')->first();
        if (! $tenant) {
            return response()->json(['message' => 'Unable to reset password.'], 400);
        }

        $user = User::query()
            ->where('tenant_id', $tenant->id)
            ->where('email', $request->email)
            ->first();

        if (! $user || ! Password::broker()->tokenExists($user, $request->token)) {
            return response()->json(['message' => 'Unable to reset password.'], 400);
        }

        $user->forceFill([
            'password' => Hash::make($request->password),
        ])->setRememberToken(Str::random(60));
        $user->save();

        Password::broker()->deleteToken($user);
        $user->tokens()->delete();
        DB::table('sessions')->where('user_id', $user->id)->delete();

        return response()->json(['message' => 'Your password has been reset.']);
    }

    private function formatUser(User $user): array
    {
        return $user->only('id', 'name', 'email', 'role', 'tenant_id');
    }
}
