<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiKeyAuth
{
    /**
     * Authenticate requests using X-API-Key header.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $rawKey = $request->header('X-API-Key');

        if (!$rawKey) {
            return response()->json([
                'error' => 'API key is required',
                'error_code' => 'AUTH_MISSING_KEY',
            ], 401);
        }

        $keyHash = hash('sha256', $rawKey);
        $apiKey = ApiKey::where('key_hash', $keyHash)->first();

        if (!$apiKey) {
            return response()->json([
                'error' => 'Invalid API key',
                'error_code' => 'AUTH_INVALID_KEY',
            ], 401);
        }

        if (!$apiKey->isValid()) {
            return response()->json([
                'error' => 'API key is expired or disabled',
                'error_code' => 'AUTH_KEY_INACTIVE',
            ], 403);
        }

        // Update last used timestamp
        $apiKey->update(['last_used_at' => now()]);

        // Set the authenticated user on the request
        $request->setUserResolver(fn() => $apiKey->user);
        $request->attributes->set('api_key', $apiKey);

        return $next($request);
    }
}
