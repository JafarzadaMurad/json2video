<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateMovieRequest;
use App\Models\RenderJob;
use App\Services\RenderDispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MovieController extends Controller
{
    public function __construct(
        private RenderDispatcher $dispatcher
    ) {
    }

    /**
     * POST /api/v1/movies — Create a new render job
     */
    public function store(CreateMovieRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        // Calculate payload hash for potential caching
        $payloadHash = RenderJob::hashPayload($validated);

        // Check if identical job was recently completed (cache hit)
        $cachedJob = RenderJob::where('payload_hash', $payloadHash)
            ->where('user_id', $user->id)
            ->where('status', RenderJob::STATUS_DONE)
            ->where('expires_at', '>', now())
            ->first();

        if ($cachedJob) {
            return response()->json([
                'job_id' => $cachedJob->id,
                'status' => $cachedJob->status,
                'url' => $cachedJob->output_url,
                'cached' => true,
                'created_at' => $cachedJob->created_at->toIso8601String(),
            ], 200);
        }

        // Create the render job
        $job = RenderJob::create([
            'user_id' => $user->id,
            'status' => RenderJob::STATUS_QUEUED,
            'payload' => $validated,
            'payload_hash' => $payloadHash,
            'resolution' => $validated['resolution'] ?? 'full-hd',
            'quality' => $validated['quality'] ?? 'high',
            'webhook_url' => $validated['webhook_url'] ?? null,
        ]);

        // Dispatch to Redis queue for Python worker
        $this->dispatcher->dispatch($job);

        return response()->json([
            'job_id' => $job->id,
            'status' => $job->status,
            'created_at' => $job->created_at->toIso8601String(),
        ], 202);
    }

    /**
     * GET /api/v1/movies/{job_id} — Get job status
     */
    public function show(Request $request, string $jobId): JsonResponse
    {
        $job = RenderJob::where('id', $jobId)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $response = [
            'job_id' => $job->id,
            'status' => $job->status,
            'progress' => $job->progress,
            'created_at' => $job->created_at->toIso8601String(),
        ];

        if ($job->status === RenderJob::STATUS_DONE) {
            $response['url'] = $job->output_url;
            $response['duration'] = (float) $job->duration_seconds;
            $response['size_mb'] = $job->file_size_bytes ? round($job->file_size_bytes / (1024 * 1024), 2) : null;
            $response['completed_at'] = $job->completed_at?->toIso8601String();
            $response['expires_at'] = $job->expires_at?->toIso8601String();
        }

        if ($job->status === RenderJob::STATUS_FAILED) {
            $response['error'] = $job->error_message;
            $response['error_code'] = $job->error_code;
        }

        if ($job->status === RenderJob::STATUS_EXPIRED) {
            $response['message'] = 'Video has expired and files have been deleted. You can re-render by submitting the same payload.';
        }

        return response()->json($response);
    }

    /**
     * GET /api/v1/movies — List all jobs
     */
    public function index(Request $request): JsonResponse
    {
        $query = RenderJob::where('user_id', $request->user()->id);

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        // Date filters
        if ($request->has('date_from')) {
            $query->where('created_at', '>=', $request->input('date_from'));
        }
        if ($request->has('date_to')) {
            $query->where('created_at', '<=', $request->input('date_to'));
        }

        // Sorting
        $sortBy = $request->input('sort_by', 'created_at');
        $sortDir = $request->input('sort_dir', 'desc');
        $allowedSorts = ['created_at', 'status', 'duration_seconds', 'file_size_bytes'];
        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortDir === 'asc' ? 'asc' : 'desc');
        }

        $limit = min((int) $request->input('limit', 20), 100);
        $jobs = $query->paginate($limit);

        return response()->json([
            'data' => $jobs->map(fn($job) => [
                'job_id' => $job->id,
                'status' => $job->status,
                'progress' => $job->progress,
                'resolution' => $job->resolution,
                'duration' => (float) $job->duration_seconds,
                'size_mb' => $job->file_size_bytes ? round($job->file_size_bytes / (1024 * 1024), 2) : null,
                'url' => $job->output_url,
                'created_at' => $job->created_at->toIso8601String(),
                'completed_at' => $job->completed_at?->toIso8601String(),
                'expires_at' => $job->expires_at?->toIso8601String(),
            ]),
            'pagination' => [
                'current_page' => $jobs->currentPage(),
                'last_page' => $jobs->lastPage(),
                'per_page' => $jobs->perPage(),
                'total' => $jobs->total(),
            ],
        ]);
    }

    /**
     * DELETE /api/v1/movies/{job_id} — Delete a job and its files
     */
    public function destroy(Request $request, string $jobId): JsonResponse
    {
        $job = RenderJob::where('id', $jobId)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        // Delete files from disk
        if ($job->output_path && file_exists($job->output_path)) {
            unlink($job->output_path);
        }
        if ($job->thumbnail_path && file_exists($job->thumbnail_path)) {
            unlink($job->thumbnail_path);
        }

        $job->delete();

        return response()->json([
            'message' => 'Job and associated files deleted successfully',
            'job_id' => $jobId,
        ]);
    }
}
