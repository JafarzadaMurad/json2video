<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\WebhookConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebhookController extends Controller
{
    /**
     * GET /api/v1/webhooks — Get current webhook config
     */
    public function show(Request $request): JsonResponse
    {
        $config = WebhookConfig::where('user_id', $request->user()->id)->first();

        if (!$config) {
            return response()->json([
                'message' => 'No webhook configured',
                'webhook' => null,
            ]);
        }

        return response()->json([
            'webhook' => [
                'url' => $config->url,
                'events' => $config->events,
                'is_active' => $config->is_active,
                'created_at' => $config->created_at->toIso8601String(),
            ],
        ]);
    }

    /**
     * POST /api/v1/webhooks — Create or update webhook config
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'url' => 'required|url|max:500',
            'events' => 'sometimes|array',
            'events.*' => 'string|in:render.done,render.failed,render.processing',
            'is_active' => 'sometimes|boolean',
        ]);

        $config = WebhookConfig::updateOrCreate(
            ['user_id' => $request->user()->id],
            [
                'url' => $validated['url'],
                'events' => json_encode($validated['events'] ?? ['render.done', 'render.failed']),
                'is_active' => $validated['is_active'] ?? true,
            ]
        );

        return response()->json([
            'message' => 'Webhook configured successfully',
            'webhook' => [
                'url' => $config->url,
                'events' => $config->events,
                'is_active' => $config->is_active,
            ],
        ], 201);
    }

    /**
     * DELETE /api/v1/webhooks — Remove webhook config
     */
    public function destroy(Request $request): JsonResponse
    {
        WebhookConfig::where('user_id', $request->user()->id)->delete();

        return response()->json([
            'message' => 'Webhook removed successfully',
        ]);
    }
}
