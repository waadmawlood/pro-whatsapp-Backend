<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Http\Responses\ApiResponse;
use App\Models\LoginAttempt;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;
use Spatie\Permission\PermissionRegistrar;

class AuthController extends Controller
{
    public function login(LoginRequest $request, AuditLogger $audit): JsonResponse
    {
        $email = strtolower($request->validated('email'));
        $user = User::query()->with('company')->where('email', $email)->first();

        $attempt = [
            'company_id' => $user?->company_id,
            'user_id' => $user?->id,
            'email' => $email,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'created_at' => now(),
        ];

        if (! $user || ! Hash::check($request->validated('password'), $user->password)) {
            LoginAttempt::create($attempt + ['successful' => false, 'failure_reason' => 'invalid_credentials']);

            return ApiResponse::error(__('Invalid credentials.'), 401);
        }

        if (! $user->is_active) {
            LoginAttempt::create($attempt + ['successful' => false, 'failure_reason' => 'disabled']);

            return ApiResponse::error(__('Your account is disabled.'), 403);
        }

        if (! $user->company?->is_active) {
            LoginAttempt::create($attempt + ['successful' => false, 'failure_reason' => 'company_disabled']);

            return ApiResponse::error(__('Company account is disabled.'), 403);
        }

        LoginAttempt::create($attempt + ['successful' => true]);

        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
            'last_seen_at' => now(),
        ])->save();

        app(PermissionRegistrar::class)->setPermissionsTeamId($user->company_id);

        $token = $user->createToken($request->validated('device_name') ?? 'api')->plainTextToken;

        $audit->log('auth.login', $user, sprintf('%s logged in', $user->name));

        $user->load(['roles', 'permissions', 'company']);

        return ApiResponse::success([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => new UserResource($user),
        ]);
    }

    public function logout(Request $request, AuditLogger $audit): JsonResponse
    {
        $audit->log('auth.logout', $request->user(), sprintf('%s logged out', $request->user()->name));

        $accessToken = $request->user()->currentAccessToken();

        if ($accessToken instanceof PersonalAccessToken) {
            $accessToken->delete();
        } elseif ($request->bearerToken()) {
            PersonalAccessToken::findToken($request->bearerToken())?->delete();
        }

        Auth::guard('sanctum')->forgetUser();

        return ApiResponse::success(null, __('Logged out.'));
    }

    public function logoutAll(Request $request): JsonResponse
    {
        $request->user()->tokens()->delete();

        return ApiResponse::success(null, __('Logged out from all devices.'));
    }

    public function me(Request $request): JsonResponse
    {
        return ApiResponse::resource(
            new UserResource($request->user()->load(['roles', 'permissions', 'company']))
        );
    }

    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->safe()->except(['password']);

        if ($request->filled('password')) {
            $data['password'] = $request->validated('password');
        }

        $user->update($data);

        return ApiResponse::resource(new UserResource($user->fresh(['roles', 'permissions', 'company'])));
    }

    public function sessions(Request $request): JsonResponse
    {
        $current = $request->user()->currentAccessToken();

        $sessions = $request->user()->tokens()->latest()->get()->map(fn ($token) => [
            'id' => $token->id,
            'name' => $token->name,
            'last_used_at' => $token->last_used_at,
            'created_at' => $token->created_at,
            'is_current' => $current && $token->id === $current->id,
        ]);

        return ApiResponse::success($sessions);
    }

    public function revokeSession(Request $request, int $token): JsonResponse
    {
        $request->user()->tokens()->where('id', $token)->delete();

        return ApiResponse::success(null, __('Session revoked.'));
    }
}
