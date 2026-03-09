<?php

namespace App\Console\Commands;

use App\Models\RenderJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CleanupExpiredVideos extends Command
{
    protected $signature = 'videos:cleanup';
    protected $description = 'Delete expired video files and update job statuses';

    public function handle(): int
    {
        $expiredJobs = RenderJob::where('status', RenderJob::STATUS_DONE)
            ->where('expires_at', '<', now())
            ->get();

        if ($expiredJobs->isEmpty()) {
            $this->info('No expired videos found.');
            return Command::SUCCESS;
        }

        $deletedCount = 0;
        $freedBytes = 0;

        foreach ($expiredJobs as $job) {
            // Delete video file
            if ($job->output_path && file_exists($job->output_path)) {
                $freedBytes += filesize($job->output_path);
                unlink($job->output_path);
            }

            // Delete thumbnail
            if ($job->thumbnail_path && file_exists($job->thumbnail_path)) {
                unlink($job->thumbnail_path);
            }

            // Update job status
            $job->update([
                'status' => RenderJob::STATUS_EXPIRED,
                'output_path' => null,
                'output_url' => null,
                'thumbnail_path' => null,
            ]);

            $deletedCount++;
        }

        $freedMB = round($freedBytes / (1024 * 1024), 2);
        $this->info("Cleaned up {$deletedCount} expired videos. Freed {$freedMB} MB.");
        Log::info("Video cleanup: deleted {$deletedCount} videos, freed {$freedMB} MB.");

        return Command::SUCCESS;
    }
}
