<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TranscribeJob extends Model
{
    use HasUuids;

    protected $table = 'transcribe_jobs';

    protected $fillable = [
        'user_id',
        'status',
        'src_url',
        'src_type',
        'language',
        'language_confidence',
        'segments',
        'srt_path',
        'srt_url',
        'uploaded_path',
        'error_message',
        'worker_id',
        'expires_at',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'language_confidence' => 'decimal:2',
        'segments' => 'integer',
        'expires_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    const STATUS_QUEUED = 'queued';
    const STATUS_PROCESSING = 'processing';
    const STATUS_DONE = 'done';
    const STATUS_FAILED = 'failed';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
