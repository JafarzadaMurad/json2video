<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UsageLog extends Model
{
    protected $fillable = [
        'user_id',
        'api_key_id',
        'endpoint',
        'method',
        'status_code',
        'render_job_id',
        'render_duration_seconds',
        'file_size_bytes',
        'ip_address',
        'user_agent',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function renderJob()
    {
        return $this->belongsTo(RenderJob::class, 'render_job_id');
    }
}
