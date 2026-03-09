<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'plan_id',
        'plan_expires_at',
        'storage_days_override',
        'is_admin',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'plan_expires_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function isPlanExpired(): bool
    {
        return $this->plan_expires_at && $this->plan_expires_at->isPast();
    }

    public function daysUntilExpiry(): ?int
    {
        if (!$this->plan_expires_at)
            return null;
        return max(0, (int) now()->diffInDays($this->plan_expires_at, false));
    }

    public function getStorageDays(): int
    {
        return $this->storage_days_override ?? $this->plan?->storage_days ?? 3;
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function apiKeys(): HasMany
    {
        return $this->hasMany(ApiKey::class);
    }

    public function renderJobs(): HasMany
    {
        return $this->hasMany(RenderJob::class);
    }
}

