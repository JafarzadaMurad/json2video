<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class RenderJob extends Model
{
    use HasUuids;

    protected $table = 'render_jobs';

    protected $fillable = [
        'user_id',
        'status',
        'payload',
        'payload_hash',
        'resolution',
        'quality',
        'progress',
        'output_path',
        'output_url',
        'thumbnail_path',
        'duration_seconds',
        'file_size_bytes',
        'error_message',
        'error_code',
        'worker_id',
        'metadata',
        'webhook_url',
        'webhook_sent_at',
        'expires_at',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'metadata' => 'array',
        'progress' => 'integer',
        'duration_seconds' => 'decimal:2',
        'file_size_bytes' => 'integer',
        'webhook_sent_at' => 'datetime',
        'expires_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    // ─── Status constants ────────────────────────
    const STATUS_QUEUED = 'queued';
    const STATUS_PROCESSING = 'processing';
    const STATUS_DONE = 'done';
    const STATUS_FAILED = 'failed';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_EXPIRED = 'expired';

    // ─── Relationships ───────────────────────────
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ─── Helpers ─────────────────────────────────
    public function isFinished(): bool
    {
        return in_array($this->status, [
            self::STATUS_DONE,
            self::STATUS_FAILED,
            self::STATUS_CANCELLED,
            self::STATUS_EXPIRED,
        ]);
    }

    public function markAsProcessing(string $workerId): void
    {
        $this->update([
            'status' => self::STATUS_PROCESSING,
            'worker_id' => $workerId,
            'started_at' => now(),
        ]);
    }

    public function markAsDone(string $outputPath, string $outputUrl, ?string $thumbnailPath, float $durationSeconds, int $fileSizeBytes): void
    {
        $storageDays = $this->user->getStorageDays();

        $this->update([
            'status' => self::STATUS_DONE,
            'progress' => 100,
            'output_path' => $outputPath,
            'output_url' => $outputUrl,
            'thumbnail_path' => $thumbnailPath,
            'duration_seconds' => $durationSeconds,
            'file_size_bytes' => $fileSizeBytes,
            'completed_at' => now(),
            'expires_at' => now()->addDays($storageDays),
        ]);
    }

    public function markAsFailed(string $errorMessage, string $errorCode = 'RENDER_ERROR'): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'error_message' => $errorMessage,
            'error_code' => $errorCode,
            'completed_at' => now(),
        ]);
    }

    /**
     * Generate the payload hash for caching/dedup
     */
    public static function hashPayload(array $payload): string
    {
        return hash('sha256', json_encode($payload));
    }
}
