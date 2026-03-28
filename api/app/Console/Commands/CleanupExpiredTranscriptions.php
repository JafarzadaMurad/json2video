<?php

namespace App\Console\Commands;

use App\Models\TranscribeJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CleanupExpiredTranscriptions extends Command
{
    protected $signature = 'transcribe:cleanup';
    protected $description = 'Delete expired SRT files from transcription jobs';

    public function handle(): int
    {
        $expiredJobs = TranscribeJob::where('status', TranscribeJob::STATUS_DONE)
            ->where('expires_at', '<', now())
            ->get();

        if ($expiredJobs->isEmpty()) {
            return Command::SUCCESS;
        }

        $deletedCount = 0;

        foreach ($expiredJobs as $job) {
            // Delete SRT file
            if ($job->srt_path && file_exists($job->srt_path)) {
                unlink($job->srt_path);
            }

            // Update job (remove URLs, keep record)
            $job->update([
                'srt_path' => null,
                'srt_url' => null,
                'status' => 'expired',
            ]);

            $deletedCount++;
        }

        if ($deletedCount > 0) {
            $this->info("Cleaned up {$deletedCount} expired SRT files.");
            Log::info("Transcribe cleanup: deleted {$deletedCount} SRT files.");
        }

        return Command::SUCCESS;
    }
}
