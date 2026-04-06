<?php

namespace App\Console\Commands;

use App\Models\TranscribeJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CleanupExpiredTranscriptions extends Command
{
    protected $signature = 'transcribe:cleanup';
    protected $description = 'Delete expired SRT files and uploaded source files from transcription jobs';

    public function handle(): int
    {
        $deletedCount = 0;

        // 1. Clean up expired done jobs (SRT + uploaded files)
        $expiredJobs = TranscribeJob::where('status', TranscribeJob::STATUS_DONE)
            ->where('expires_at', '<', now())
            ->get();

        foreach ($expiredJobs as $job) {
            $this->cleanupJobFiles($job);

            $job->update([
                'srt_path' => null,
                'srt_url' => null,
                'uploaded_path' => null,
                'status' => 'expired',
            ]);

            $deletedCount++;
        }

        // 2. Clean up uploaded files on completed jobs (done with uploaded_path still set)
        $completedWithUploads = TranscribeJob::where('status', TranscribeJob::STATUS_DONE)
            ->whereNotNull('uploaded_path')
            ->where('expires_at', '>=', now())
            ->get();

        foreach ($completedWithUploads as $job) {
            if ($job->uploaded_path && file_exists($job->uploaded_path)) {
                @unlink($job->uploaded_path);
            }
            $job->update(['uploaded_path' => null]);
        }

        // 3. Clean up uploaded files on failed jobs older than 1 hour
        $oldFailedJobs = TranscribeJob::where('status', TranscribeJob::STATUS_FAILED)
            ->whereNotNull('uploaded_path')
            ->where('created_at', '<', now()->subHour())
            ->get();

        foreach ($oldFailedJobs as $job) {
            if ($job->uploaded_path && file_exists($job->uploaded_path)) {
                @unlink($job->uploaded_path);
            }
            $job->update(['uploaded_path' => null]);
        }

        if ($deletedCount > 0) {
            $this->info("Cleaned up {$deletedCount} expired SRT files.");
            Log::info("Transcribe cleanup: deleted {$deletedCount} SRT files.");
        }

        return Command::SUCCESS;
    }

    private function cleanupJobFiles(TranscribeJob $job): void
    {
        // Delete SRT file
        if ($job->srt_path && file_exists($job->srt_path)) {
            @unlink($job->srt_path);
        }

        // Delete uploaded source file
        if ($job->uploaded_path && file_exists($job->uploaded_path)) {
            @unlink($job->uploaded_path);
        }
    }
}
