<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateTranscribeRequest;
use App\Models\TranscribeJob;
use App\Services\TranscribeDispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class TranscribeController extends Controller
{
    public function __construct(
        private TranscribeDispatcher $dispatcher
    ) {
    }

    /**
     * POST /api/v1/transcribe — Create a new transcription job
     * Accepts either JSON with "src" URL or multipart/form-data with "file" upload.
     */
    public function store(CreateTranscribeRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        // Determine source: uploaded file or URL
        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $extension = $file->getClientOriginalExtension();
            $filename = uniqid('transcribe_') . '.' . $extension;

            // Store in public storage for worker to download via URL
            $path = $file->storeAs('uploads/transcribe', $filename, 'public');
            $srcUrl = url('storage/' . $path);
            $uploadedPath = storage_path('app/public/' . $path);
        } else {
            $srcUrl = $validated['src'];
            $uploadedPath = null;
        }

        // Detect if source is video or audio based on extension
        $extension = strtolower(pathinfo(parse_url($srcUrl, PHP_URL_PATH), PATHINFO_EXTENSION));
        $videoExtensions = ['mp4', 'webm', 'mov', 'avi', 'mkv', 'flv', 'wmv'];
        $srcType = in_array($extension, $videoExtensions) ? 'video' : 'audio';

        // Create the transcription job
        $job = TranscribeJob::create([
            'user_id' => $user->id,
            'status' => TranscribeJob::STATUS_QUEUED,
            'src_url' => $srcUrl,
            'src_type' => $srcType,
            'language' => $validated['language'] ?? null,
            'uploaded_path' => $uploadedPath,
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
