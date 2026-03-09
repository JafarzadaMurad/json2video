<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebhookConfig extends Model
{
    protected $table = 'webhook_configs';

    protected $fillable = [
        'user_id',
        'url',
        'events',
        'is_active',
    ];

    protected $casts = [
        'events' => 'array',
        'is_active' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
