<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Template;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TemplateController extends Controller
{
    /**
     * GET /api/v1/templates — List user's templates
     */
    public function index(Request $request): JsonResponse
    {
        $templates = Template::where('user_id', $request->user()->id)
            ->orWhere('is_public', true)
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'data' => $templates->map(fn($t) => [
                'id' => $t->id,
                'name' => $t->name,
                'description' => $t->description,
                'category' => $t->category,
                'preview_url' => $t->preview_url,
                'is_public' => $t->is_public,
                'is_owner' => $t->user_id === $request->user()->id,
                'created_at' => $t->created_at->toIso8601String(),
            ]),
            'pagination' => [
                'current_page' => $templates->currentPage(),
                'last_page' => $templates->lastPage(),
                'total' => $templates->total(),
            ],
        ]);
    }

    /**
     * GET /api/v1/templates/{id} — Get template detail with payload
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $template = Template::where(function ($q) use ($request) {
            $q->where('user_id', $request->user()->id)
                ->orWhere('is_public', true);
        })->findOrFail($id);

        return response()->json([
            'id' => $template->id,
            'name' => $template->name,
            'description' => $template->description,
            'category' => $template->category,
            'payload' => $template->payload,
            'preview_url' => $template->preview_url,
            'is_public' => $template->is_public,
            'created_at' => $template->created_at->toIso8601String(),
        ]);
    }

    /**
     * POST /api/v1/templates — Create a new template
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'sometimes|string|max:1000',
            'category' => 'sometimes|string|max:100',
            'payload' => 'required|array',
            'payload.scenes' => 'required|array|min:1',
            'is_public' => 'sometimes|boolean',
        ]);

        $template = Template::create([
            'user_id' => $request->user()->id,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'category' => $validated['category'] ?? 'general',
            'payload' => json_encode($validated['payload']),
            'is_public' => $validated['is_public'] ?? false,
        ]);

        return response()->json([
            'message' => 'Template created',
            'id' => $template->id,
            'name' => $template->name,
        ], 201);
    }

    /**
     * PUT /api/v1/templates/{id} — Update a template
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $template = Template::where('user_id', $request->user()->id)
            ->findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'sometimes|string|max:1000',
            'category' => 'sometimes|string|max:100',
            'payload' => 'sometimes|array',
            'is_public' => 'sometimes|boolean',
        ]);

        if (isset($validated['payload'])) {
            $validated['payload'] = json_encode($validated['payload']);
        }

        $template->update($validated);

        return response()->json([
            'message' => 'Template updated',
            'id' => $template->id,
        ]);
    }

    /**
     * DELETE /api/v1/templates/{id} — Delete a template
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $template = Template::where('user_id', $request->user()->id)
            ->findOrFail($id);

        $template->delete();

        return response()->json([
            'message' => 'Template deleted',
            'id' => $id,
        ]);
    }

    /**
     * POST /api/v1/templates/{id}/render — Render a video from template
     */
    public function render(Request $request, string $id): JsonResponse
    {
        $template = Template::where(function ($q) use ($request) {
            $q->where('user_id', $request->user()->id)
                ->orWhere('is_public', true);
        })->findOrFail($id);

        $payload = is_array($template->payload)
            ? $template->payload
            : json_decode($template->payload, true);

        // Merge any overrides from the request
        $overrides = $request->input('overrides', []);
        if (!empty($overrides)) {
            $payload = array_replace_recursive($payload, $overrides);
        }

        $user = $request->user();
        $payloadHash = \App\Models\RenderJob::hashPayload($payload);

        // Create render job from template payload
        $job = \App\Models\RenderJob::create([
            'user_id' => $user->id,
            'status' => \App\Models\RenderJob::STATUS_QUEUED,
            'payload' => $payload,
            'payload_hash' => $payloadHash,
            'resolution' => $payload['resolution'] ?? 'full-hd',
            'quality' => $payload['quality'] ?? 'high',
        ]);

        // Increment template usage count
        $template->increment('usage_count');

        // Dispatch to renderer
        app(\App\Services\RenderDispatcher::class)->dispatch($job);

        return response()->json([
            'job_id' => $job->id,
            'status' => $job->status,
            'template_id' => $template->id,
            'created_at' => $job->created_at->toIso8601String(),
        ], 202);
    }
}
