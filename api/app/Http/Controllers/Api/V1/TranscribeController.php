<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateTranscribeRequest;
use App\Models\TranscribeJob;
use App\Services\TranscribeDispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TranscribeController extends Controller
{
    public function __construct(
        private TranscribeDispatcher $dispatcher
    ) {
    }

    /**
     * POST /api/v1/transcribe — Create a new transcription job
     */
    public function store(CreateTranscribeRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        // Detect if source is video or audio based on extension
        $extension = strtolower(pathinfo(parse_url($validated['src'], PHP_URL_PATH), PATHINFO_EXTENSION));
        $videoExtensions = ['mp4', 'webm', 'mov', 'avi', 'mkv', 'flv', 'wmv'];
        $srcType = in_array($extension, $videoExtensions) ? 'video' : 'audio';

        // Create the transcription job
        $job = TranscribeJob::create([
            'user_id' => $user->id,
            'status' => TranscribeJob::STATUS_QUEUED,
            'src_url' => $validated['src'],
            'src_type' => $srcType,
            'language' => $validated['language'] ?? null,
        ]);

        // Dispatch to Redis queue for Python worker
        $this->dispatcher->dispatch($job);

        return response()->json([
            'job_id' => $job->id,
            'status' => $job->status,
            'src_type' => $srcType,
            'created_at' => $job->created_at->toIso8601String(),
        ], 202);
    }

    /**
     * GET /api/v1/transcribe/{job_id} — Get transcription job status
     */
    public function show(Request $request, string $jobId): JsonResponse
    {
        $job = TranscribeJob::where('id', $jobId)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $response = [
            'job_id' => $job->id,
            'status' => $job->status,
            'src_type' => $job->src_type,
            'created_at' => $job->created_at->toIso8601String(),
        ];

        if ($job->status === TranscribeJob::STATUS_DONE) {
            $response['language'] = $job->language;
            $response['language_confidence'] = (float) $job->language_confidence;
            $response['segments'] = $job->segments;
            $response['srt_url'] = $job->srt_url;
            $response['completed_at'] = $job->completed_at?->toIso8601String();
            $response['expires_at'] = $job->expires_at?->toIso8601String();
        }

        if ($job->status === TranscribeJob::STATUS_FAILED) {
            $response['error'] = $job->error_message;
        }

        return response()->json($response);
    }
}
