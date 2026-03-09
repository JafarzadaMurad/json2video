<?php

namespace App\Services;

use App\Models\RenderJob;
use Illuminate\Support\Facades\Redis;

class RenderDispatcher
{
    /**
     * Dispatch a render job to the Redis queue for the Python worker.
     */
    public function dispatch(RenderJob $job): void
    {
        $message = json_encode([
            'job_id' => $job->id,
            'user_id' => $job->user_id,
            'payload' => $job->payload,
            'resolution' => $job->resolution,
            'quality' => $job->quality,
            'webhook_url' => $job->webhook_url,
            'created_at' => $job->created_at->toIso8601String(),
        ]);

        // Push to the render queue that Python worker listens to
        Redis::rpush('render:jobs', $message);
    }
}
