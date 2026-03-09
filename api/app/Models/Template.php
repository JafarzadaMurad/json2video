<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Template extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'name',
        'description',
        'category',
        'payload',
        'variables',
        'preview_url',
        'thumbnail_path',
        'is_public',
        'usage_count',
    ];

    protected $casts = [
        'payload' => 'array',
        'variables' => 'array',
        'is_public' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
