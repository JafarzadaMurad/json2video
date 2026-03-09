<?php

namespace App\Http\Middleware;

use App\Models\UsageLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrackUsage
{
    /**
     * Log API usage for analytics and billing.
     * Runs AFTER the response is sent to avoid slowing requests.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Only log for authenticated users
        $user = $request->user();
        if (!$user) {
            return $response;
        }

        try {
            UsageLog::create([
                'user_id' => $user->id,
                'api_key_id' => $request->attributes->get('api_key_id'),
                'endpoint' => $request->path(),
                'method' => $request->method(),
                'status_code' => $response->getStatusCode(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        } catch (\Exception $e) {
            // Don't fail the request if usage logging fails
            \Illuminate\Support\Facades\Log::debug('Usage log failed: ' . $e->getMessage());
        }

        return $response;
    }
}
