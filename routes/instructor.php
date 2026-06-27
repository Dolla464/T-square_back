<?php

use App\Http\Controllers\Api\AttendanceController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Instructor Routes — auth:sanctum + role:instructor
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum', 'role:instructor'])
    ->prefix('instructor')
    ->name('instructor.')
    ->group(function () {

        // Today's sessions for the authenticated instructor
        Route::get('schedule/today', [AttendanceController::class, 'todaySchedule'])->name('schedule.today');

        // Instructor attendance management routes
        Route::prefix('attendance')->name('attendance.')->group(function () {

            // GET /api/instructor/attendance/today-schedule
            Route::get('today-schedule', [AttendanceController::class, 'instructorTodaySchedule'])
                ->name('today-schedule');

            // GET /api/instructor/attendance/sessions/{session}
            Route::get('sessions/{session}', [AttendanceController::class, 'getSessionDetails'])
                ->name('session.details');

            // GET /api/instructor/attendance/sessions/{session}/qr
            Route::get('sessions/{session}/qr', [AttendanceController::class, 'getSessionQr'])
                ->name('session.qr');

            // POST /api/instructor/attendance/mark
            Route::post('mark', [AttendanceController::class, 'markAttendance'])
                ->name('mark');
        });
    });
