<?php

namespace App\Http\Middleware;

use App\Models\AdminApiToken;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateAdminToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $plainToken = $this->extractToken($request);
        $tokenHash = hash('sha256', $plainToken);

        if ($plainToken === '') {
            $this->logFailure($request, 'missing_token');

            return response()->json([
                'success' => false,
                'message' => 'Authentication token is missing.',
            ], 401);
        }

        $token = AdminApiToken::query()
            ->where('token_hash', $tokenHash)
            ->with('adminUser')
            ->first();

        if ($token === null) {
            $this->logFailure($request, 'token_not_found', [
                'token_fingerprint' => substr($tokenHash, 0, 16),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Authentication failed.',
            ], 401);
        }

        if ($token->expires_at === null || $token->expires_at->copy()->setTimezone('UTC')->lte(now('UTC'))) {
            $this->logFailure($request, 'token_expired', [
                'admin_user_id' => $token->admin_user_id,
                'token_id' => $token->id,
                'expires_at' => optional($token->expires_at)->toIso8601String(),
                'now_utc' => now('UTC')->toIso8601String(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Authentication failed.',
            ], 401);
        }

        if ($token->adminUser === null) {
            $this->logFailure($request, 'admin_user_missing', [
                'admin_user_id' => $token->admin_user_id,
                'token_id' => $token->id,
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Authentication failed.',
            ], 401);
        }

        if (! $token->adminUser->active) {
            $this->logFailure($request, 'inactive_admin_user', [
                'admin_user_id' => $token->admin_user_id,
                'token_id' => $token->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Authentication failed.',
            ], 401);
        }

        $token->forceFill([
            'last_used_at' => now('UTC'),
        ])->save();

        $request->attributes->set('adminUser', $token->adminUser);
        $request->attributes->set('adminToken', $token);

        return $next($request);
    }

    private function extractToken(Request $request): string
    {
        $bearer = trim((string) $request->bearerToken());

        if ($bearer !== '') {
            return $bearer;
        }

        return trim((string) $request->header('X-Admin-Token', ''));
    }

    private function logFailure(Request $request, string $reason, array $context = []): void
    {
        if (! (bool) config('admin.log_auth_failures', true)) {
            return;
        }

        Log::warning('Admin API authentication failed.', array_merge([
            'reason' => $reason,
            'path' => $request->path(),
            'method' => $request->method(),
            'ip' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
        ], $context));
    }
}
