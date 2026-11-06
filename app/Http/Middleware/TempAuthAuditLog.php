<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TempAuthAuditLog
{
    public function handle(Request $request, Closure $next)
    {
        $authHeader = $request->header('Authorization');
        $tokenPreview = null;
        if ($authHeader && preg_match('/^Bearer\s+(.+)$/i', $authHeader, $m)) {
            $tok = $m[1];
            $parts = explode('.', $tok);
            // Decode the JWT payload (middle segment) without verifying — just for logging.
            $payload = null;
            if (count($parts) === 3) {
                $decoded = base64_decode(strtr($parts[1], '-_', '+/'), true);
                $payload = $decoded ? json_decode($decoded, true) : null;
            }
            $tokenPreview = [
                'len' => strlen($tok),
                'head' => substr($tok, 0, 12) . '…',
                'tail' => '…' . substr($tok, -8),
                'claim_sub' => $payload['sub'] ?? null,
                'claim_iat' => isset($payload['iat']) ? date('c', $payload['iat']) : null,
                'claim_exp' => isset($payload['exp']) ? date('c', $payload['exp']) : null,
            ];
        }

        $response = $next($request);

        // Resolve the authenticated user (if any) AFTER the middleware chain ran.
        $authedUserId = null;
        try {
            $authedUserId = optional(\Illuminate\Support\Facades\Auth::guard('api')->user())->id;
        } catch (\Throwable $e) {}

        Log::channel('single')->info('AUTH_AUDIT', [
            'time'   => now()->toIso8601String(),
            'ip'     => $request->ip(),
            'method' => $request->method(),
            'path'   => $request->path(),
            'agent'  => substr($request->userAgent() ?? '', 0, 80),
            'status' => $response->getStatusCode(),
            'authed_user_id' => $authedUserId,
            'token'  => $tokenPreview,
            'body_keys' => is_array($request->all()) ? array_keys($request->all()) : null,
        ]);

        return $response;
    }
}
