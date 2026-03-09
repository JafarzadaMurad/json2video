<?php

use App\Http\Controllers\Api\V1\MovieController;
use App\Http\Controllers\Api\V1\TemplateController;
use App\Http\Controllers\Api\V1\WebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| JSON2Video API Routes (v1)
|--------------------------------------------------------------------------
|
| All routes are prefixed with /api/v1/ and protected by api-key middleware.
| Rate limiting and usage tracking are applied to all authenticated routes.
|
*/

Route::middleware(['api-key', 'rate-limit', 'track-usage'])->group(function () {

    // ─── Movies (Render Jobs) ────────────────────
    Route::post('/movies', [MovieController::class, 'store']);
    Route::get('/movies', [MovieController::class, 'index']);
    Route::get('/movies/{jobId}', [MovieController::class, 'show']);
    Route::delete('/movies/{jobId}', [MovieController::class, 'destroy']);

    // ─── Templates ───────────────────────────────
    Route::get('/templates', [TemplateController::class, 'index']);
    Route::post('/templates', [TemplateController::class, 'store']);
    Route::get('/templates/{id}', [TemplateController::class, 'show']);
    Route::put('/templates/{id}', [TemplateController::class, 'update']);
    Route::delete('/templates/{id}', [TemplateController::class, 'destroy']);
    Route::post('/templates/{id}/render', [TemplateController::class, 'render']);

    // ─── Webhooks ────────────────────────────────
    Route::get('/webhooks', [WebhookController::class, 'show']);
    Route::post('/webhooks', [WebhookController::class, 'store']);
    Route::delete('/webhooks', [WebhookController::class, 'destroy']);

    // ─── Account ─────────────────────────────────
    Route::get('/account', function (\Illuminate\Http\Request $request) {
        $user = $request->user();
        $plan = $user->plan;
        $jobCount = $user->renderJobs()->count();
        $doneCount = $user->renderJobs()->where('status', 'done')->count();

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'plan' => $plan ? [
                'name' => $plan->name,
                'max_render_minutes' => $plan->max_render_minutes,
                'max_video_duration' => $plan->max_video_duration,
                'rate_limit_per_minute' => $plan->rate_limit_per_minute,
                'retention_days' => $plan->retention_days,
            ] : null,
            'usage' => [
                'total_jobs' => $jobCount,
                'completed_jobs' => $doneCount,
            ],
        ]);
    });
});
