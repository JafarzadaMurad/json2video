<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'price_monthly',
        'max_render_minutes',
        'max_video_duration',
        'max_resolution',
        'rate_limit_per_minute',
        'has_watermark',
        'has_priority_queue',
        'has_webhook',
        'has_templates',
        'storage_days',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'price_monthly' => 'decimal:2',
        'has_watermark' => 'boolean',
        'has_priority_queue' => 'boolean',
        'has_webhook' => 'boolean',
        'has_templates' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
