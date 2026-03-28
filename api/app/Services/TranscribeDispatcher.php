<?php

namespace App\Services;

use App\Models\TranscribeJob;
use Illuminate\Support\Facades\Redis;

class TranscribeDispatcher
{
    /**
     * Dispatch a transcription job to the Redis queue for the Python worker.
     */
    public function dispatch(TranscribeJob $job): void
    {
        $message = json_encode([
            'job_id' => $job->id,
            'job_type' => 'transcribe',
            'user_id' => $job->user_id,
            'src_url' => $job->src_url,
            'created_at' => $job->created_at->toIso8601String(),
        ]);

        // Push to the transcribe queue that Python worker listens to
        Redis::rpush('transcribe:jobs', $message);
    }
}
