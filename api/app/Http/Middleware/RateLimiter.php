<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class RateLimiter
{
    /**
     * Rate limit API requests based on user's plan.
     * Uses sliding window with Redis/cache backend.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (!$user) {
            return $next($request);
        }

        $plan = $user->plan;
        $maxPerMinute = $plan?->rate_limit_per_minute ?? 10;

        $key = "rate_limit:{$user->id}";
        $current = (int) Cache::get($key, 0);

        if ($current >= $maxPerMinute) {
            return response()->json([
                'error' => 'Rate limit exceeded',
                'error_code' => 'RATE_LIMIT_EXCEEDED',
                'limit' => $maxPerMinute,
                'retry_after' => 60,
            ], 429)->withHeaders([
                        'X-RateLimit-Limit' => $maxPerMinute,
                        'X-RateLimit-Remaining' => 0,
                        'Retry-After' => 60,
                    ]);
        }

        Cache::put($key, $current + 1, now()->addMinute());

        $response = $next($request);

        return $response->withHeaders([
            'X-RateLimit-Limit' => $maxPerMinute,
            'X-RateLimit-Remaining' => max(0, $maxPerMinute - $current - 1),
        ]);
    }
}
