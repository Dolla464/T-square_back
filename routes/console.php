<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Remove stale video chunk folders every day at 02:00
Schedule::command('chunks:cleanup')->dailyAt('02:00');

// Attendance automation
// Activate upcoming sessions 30 minutes before start_time — runs every 15 minutes
Schedule::command('attendance:activate')->everyFifteenMinutes()->withoutOverlapping()->appendOutputTo(storage_path('logs/attendance-activate.log'));

// Complete active sessions 30 minutes after end_time and mark absent — runs every 15 minutes
Schedule::command('attendance:complete')->everyFifteenMinutes()->withoutOverlapping()->appendOutputTo(storage_path('logs/attendance-complete.log'));

// Generate sessions for the upcoming week for all active groups — runs daily at midnight
Schedule::command('attendance:generate-weekly')->dailyAt('00:00')->withoutOverlapping()->appendOutputTo(storage_path('logs/attendance-generate-weekly.log'));
