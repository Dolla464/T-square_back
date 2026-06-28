<?php

use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\Instructor\InstructorDashboardController;
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

        // ── Instructor Dashboard (Overview + Schedule) ────────────────────────
        Route::prefix('dashboard')->name('dashboard.')->group(function () {
            Route::get('stats', [InstructorDashboardController::class, 'getStats'])
                ->name('stats');
            Route::get('active-groups', [InstructorDashboardController::class, 'getActiveGroups'])
                ->name('active-groups');
            Route::get('completed-groups', [InstructorDashboardController::class, 'getCompletedGroups'])
                ->name('completed-groups');
            Route::get('groups/{group}', [InstructorDashboardController::class, 'getGroupDetails'])
                ->name('group-details');
            Route::get('schedule', [InstructorDashboardController::class, 'getSchedule'])
                ->name('schedule');
        });

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

            // GET /api/instructor/attendance/sessions/{session}/records?since={ms}
            Route::get('sessions/{session}/records', [AttendanceController::class, 'getSessionRecords'])
                ->name('session.records');

            // POST /api/instructor/attendance/mark
            Route::post('mark', [AttendanceController::class, 'markAttendance'])
                ->name('mark');
        });
    });
