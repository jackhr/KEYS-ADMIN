<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminApiToken;
use App\Models\AdminUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AccountSettingsController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $admin = $this->resolveAdmin($request);
        $token = $this->resolveToken($request);

        return response()->json([
            'success' => true,
            'data' => [
                'user' => $this->adminPayload($admin),
                'session' => [
                    'token_created_at' => $token?->created_at?->toIso8601String(),
                    'token_last_used_at' => $token?->last_used_at?->toIso8601String(),
                    'token_expires_at' => $token?->expires_at?->toIso8601String(),
                ],
            ],
        ]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $admin = $this->resolveAdmin($request);

        $payload = $request->validate([
            'username' => [
                'required',
                'string',
                'min:2',
                'max:120',
                Rule::unique('admin_users', 'username')->ignore($admin->id),
            ],
            'email' => [
                'nullable',
                'string',
                'max:190',
                'email:rfc',
                Rule::unique('admin_users', 'email')->ignore($admin->id),
            ],
        ]);

        $admin->forceFill([
            'username' => trim($payload['username']),
            'email' => $this->normalizeEmail($payload['email'] ?? null),
        ])->save();

        return response()->json([
            'success' => true,
            'message' => 'Account profile updated.',
            'data' => [
                'user' => $this->adminPayload($admin),
            ],
        ]);
    }

    public function updatePassword(Request $request): JsonResponse
    {
        $admin = $this->resolveAdmin($request);
        $token = $this->resolveToken($request);

        $payload = $request->validate([
            'current_password' => ['required', 'string', 'min:6', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'max:255', 'confirmed'],
        ]);

        if (! Hash::check($payload['current_password'], $admin->password_hash)) {
            throw ValidationException::withMessages([
                'current_password' => ['Current password is incorrect.'],
            ]);
        }

        $admin->forceFill([
            'password_hash' => Hash::make($payload['password']),
        ])->save();

        if ($token !== null) {
            AdminApiToken::query()
                ->where('admin_user_id', $admin->id)
                ->where('id', '!=', $token->id)
                ->delete();
        } else {
            AdminApiToken::query()
                ->where('admin_user_id', $admin->id)
                ->delete();
        }

        return response()->json([
            'success' => true,
            'message' => 'Password updated.',
        ]);
    }

    private function resolveAdmin(Request $request): AdminUser
    {
        /** @var AdminUser|null $admin */
        $admin = $request->attributes->get('adminUser');

        if ($admin === null) {
            abort(401, 'Not authenticated.');
        }

        return $admin;
    }

    private function resolveToken(Request $request): ?AdminApiToken
    {
        /** @var AdminApiToken|null $token */
        $token = $request->attributes->get('adminToken');

        return $token;
    }

    /** @return array<string, mixed> */
    private function adminPayload(AdminUser $admin): array
    {
        return [
            'id' => $admin->id,
            'username' => $admin->username,
            'email' => $admin->email,
            'role' => $admin->role,
            'active' => (bool) $admin->active,
            'last_login_at' => $admin->last_login_at?->toIso8601String(),
        ];
    }

    private function normalizeEmail(?string $email): ?string
    {
        if ($email === null) {
            return null;
        }

        $normalized = strtolower(trim($email));

        return $normalized !== '' ? $normalized : null;
    }
}

