<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiAuthToken
{
    public function handle(Request $request, Closure $next): Response
    {
        // Build systems and hosting panels sometimes preserve quotes entered
        // around a secret (for example, 'token'). Treat those quotes as
        // configuration syntax instead of part of the credential. This keeps
        // old web bundles working while still comparing the actual token
        // value in constant time.
        $token = $this->normalizeToken($request->header('Api-Token'));

        if (!$token) {
            return response()->json([
                'status' => 401,
                'errors' => __('api_msg.token_missing')
            ], 401);
        }

        $configuredToken = $this->normalizeToken(
            (string) config('installation.api_token', '')
        );

        if ($configuredToken === '' || !hash_equals($configuredToken, $token)) {
            return response()->json([
                'status' => 401,
                'errors' => __('api_msg.invalid_token')
            ], 401);
        }

        return $next($request);
    }

    private function normalizeToken(?string $token): string
    {
        $token = trim((string) $token);
        if (strlen($token) >= 2) {
            $first = $token[0];
            $last = $token[strlen($token) - 1];
            if (($first === "'" || $first === '"') && $first === $last) {
                $token = trim(substr($token, 1, -1));
            }
        }

        return $token;
    }
}
