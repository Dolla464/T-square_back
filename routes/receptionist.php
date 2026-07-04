<?php

use App\Http\Controllers\Api\Receptionist\ReceptionistAttendanceController;
use App\Http\Controllers\Api\Receptionist\ReceptionistScheduleController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Receptionist Routes — auth:sanctum + role:receptionist
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum', 'role:receptionist'])
    ->prefix('receptionist')
    ->name('receptionist.')
    ->group(function () {

        // ── Schedule (read-only) ──────────────────────────────────────────────
        // Static 'export' segment declared FIRST to avoid capture by {session} wildcard.
        Route::get('schedule/export', [ReceptionistScheduleController::class, 'export'])
            ->name('schedule.export');
        Route::get('schedule', [ReceptionistScheduleController::class, 'index'])
            ->name('schedule.index');
        Route::get('instructors', [ReceptionistScheduleController::class, 'instructors'])
            ->name('instructors.index');

        // ── Attendance (centre-wide) ──────────────────────────────────────────
        Route::prefix('attendance')->name('attendance.')->group(function () {
            Route::get('today-schedule', [ReceptionistAttendanceController::class, 'todaySchedule'])
                ->name('today-schedule');
            Route::get('sessions/{session}/qr', [ReceptionistAttendanceController::class, 'getSessionQr'])
                ->name('sessions.qr');
            Route::get('sessions/{session}/records', [ReceptionistAttendanceController::class, 'getSessionRecords'])
                ->name('sessions.records');
            Route::get('sessions/{session}', [ReceptionistAttendanceController::class, 'getSessionDetails'])
                ->name('sessions.show');
            Route::post('mark', [ReceptionistAttendanceController::class, 'markAttendance'])
                ->name('mark');
        });

    });
