<?php

namespace App\Services;

use App\Models\RenderJob;
use App\Models\WebhookConfig;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WebhookService
{
    /**
     * Send webhook notification when a render job status changes.
     * Called from the job status check endpoint or scheduled task.
     */
    public static function notify(RenderJob $job): void
    {
        // Check direct webhook_url on the job
        $webhookUrl = $job->webhook_url;

        // If no direct URL, check user's webhook config
        if (!$webhookUrl) {
            $config = WebhookConfig::where('user_id', $job->user_id)
                ->where('is_active', true)
                ->first();

            if ($config) {
                $webhookUrl = $config->url;
            }
        }

        if (!$webhookUrl) {
            return;
        }

        $payload = [
            'event' => 'render.' . $job->status,
            'job_id' => $job->id,
            'status' => $job->status,
            'progress' => $job->progress,
            'timestamp' => now()->toIso8601String(),
        ];

        if ($job->status === RenderJob::STATUS_DONE) {
            $payload['url'] = $job->output_url;
            $payload['duration'] = (float) $job->duration_seconds;
            $payload['size_bytes'] = (int) $job->file_size_bytes;
        }

        if ($job->status === RenderJob::STATUS_FAILED) {
            $payload['error'] = $job->error_message;
            $payload['error_code'] = $job->error_code;
        }

        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'X-Webhook-Event' => 'render.' . $job->status,
                    'X-Webhook-Signature' => hash_hmac('sha256', json_encode($payload), config('app.key')),
                ])
                ->post($webhookUrl, $payload);

            Log::info("Webhook sent to {$webhookUrl}", [
                'job_id' => $job->id,
                'status' => $response->status(),
            ]);

        } catch (\Exception $e) {
            Log::warning("Webhook failed for job {$job->id}: {$e->getMessage()}");
        }
    }
}
