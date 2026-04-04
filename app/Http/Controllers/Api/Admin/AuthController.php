<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminApiToken;
use App\Models\AdminUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'username' => ['required', 'string', 'min:2', 'max:120'],
            'password' => ['required', 'string', 'min:6', 'max:255'],
        ]);

        $admin = AdminUser::query()
            ->where('username', $credentials['username'])
            ->where('active', 1)
            ->first();

        if ($admin === null || ! Hash::check($credentials['password'], $admin->password_hash)) {
            throw ValidationException::withMessages([
                'username' => ['Invalid username or password.'],
            ]);
        }

        $plainToken = bin2hex(random_bytes(24));
        $ttlHours = max(1, (int) config('admin.token_ttl_hours', 12));
        $expiresAt = now('UTC')->addHours($ttlHours);

        AdminApiToken::query()->create([
            'admin_user_id' => $admin->id,
            'token_hash' => hash('sha256', $plainToken),
            'expires_at' => $expiresAt,
        ]);

        $admin->forceFill([
            'last_login_at' => now(),
        ])->save();

        return response()->json([
            'success' => true,
            'message' => 'Logged in successfully.',
            'data' => [
                'token' => $plainToken,
                'expires_at' => $expiresAt,
                'user' => $this->adminPayload($admin),
            ],
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        /** @var AdminUser|null $admin */
        $admin = $request->attributes->get('adminUser');

        if ($admin === null) {
            return response()->json([
                'success' => false,
                'message' => 'Not authenticated.',
            ], 401);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'user' => $this->adminPayload($admin),
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        /** @var AdminApiToken|null $token */
        $token = $request->attributes->get('adminToken');

        if ($token !== null) {
            $token->delete();
        }

        return response()->json([
            'success' => true,
            'message' => 'Logged out.',
        ]);
    }

    private function adminPayload(AdminUser $admin): array
    {
        return [
            'id' => $admin->id,
            'username' => $admin->username,
            'email' => $admin->email,
            'role' => $admin->role,
            'active' => (bool) $admin->active,
            'last_login_at' => $admin->last_login_at,
        ];
    }
}
