<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Cleanup expired videos every hour
Schedule::command('videos:cleanup')->hourly();

// Downgrade expired plan users daily at midnight
Schedule::command('plans:expire')->daily();

