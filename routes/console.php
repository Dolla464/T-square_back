<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Remove stale video chunk folders every day at 02:00
Schedule::command('chunks:cleanup')->dailyAt('02:00');

// Remove uploaded preview video files that have no matching DB record (orphans older than 24 h)
Schedule::command('previews:cleanup-orphans')->dailyAt('02:30');

// Attendance automation
// Activate upcoming sessions from 30 minutes before start through the end of the session — runs every 15 minutes
Schedule::command('attendance:activate')->everyFifteenMinutes()->withoutOverlapping()->appendOutputTo(storage_path('logs/attendance-activate.log'));

// Complete active sessions 30 minutes after end_time and mark absent — runs every 15 minutes
Schedule::command('attendance:complete')->everyFifteenMinutes()->withoutOverlapping()->appendOutputTo(storage_path('logs/attendance-complete.log'));

// Generate sessions for the upcoming week for all active groups — runs daily at midnight
Schedule::command('attendance:generate-weekly')->dailyAt('00:00')->withoutOverlapping()->appendOutputTo(storage_path('logs/attendance-generate-weekly.log'));

// Complete active learning groups whose end_date is before today — runs daily at 01:00
Schedule::command('learning-groups:complete-expired')->dailyAt('01:00')->withoutOverlapping()->appendOutputTo(storage_path('logs/learning-groups-complete-expired.log'));

// Complete eligible online enrollments after course duration has elapsed — runs daily at 03:00
Schedule::command('enrollments:complete-online')->dailyAt('03:00')->withoutOverlapping()->appendOutputTo(storage_path('logs/enrollments-complete-online.log'));

// Close exam attempts that exceeded their allowed duration — runs every minute
Schedule::command('exams:close-expired')->everyMinute()->withoutOverlapping()->appendOutputTo(storage_path('logs/exams-close-expired.log'));

// Purge completed group attendance card bulk operation metadata (30-day retention)
Schedule::command('attendance-cards:cleanup-bulk-operations')->dailyAt('03:30');
