<?php

use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\Instructor\InstructorCourseController;
use App\Http\Controllers\Api\Instructor\InstructorDashboardController;
use App\Http\Controllers\Api\Instructor\InstructorExamController;
use App\Http\Controllers\Api\Instructor\InstructorLearningGroupController;
use App\Http\Controllers\Api\Instructor\InstructorQuestionController;
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

        // ── Instructor Courses (for exam creation dropdown) ───────────────────
        Route::get('courses', [InstructorCourseController::class, 'index'])
            ->name('courses.index');

        // ── Exams ───────────────────────────────────────────────────────────
        Route::prefix('exams')->name('exams.')->group(function () {
            Route::get('trash', [InstructorExamController::class, 'trash'])->name('trash');
            Route::post('{id}/restore', [InstructorExamController::class, 'restore'])->name('restore');
            Route::delete('{id}/force-delete', [InstructorExamController::class, 'forceDelete'])->name('force-delete');
            Route::patch('{id}/toggle-status', [InstructorExamController::class, 'toggleStatus'])->name('toggle-status');
        });
        Route::apiResource('exams', InstructorExamController::class);

        // ── Questions ───────────────────────────────────────────────────────
        Route::prefix('questions')->name('questions.')->group(function () {
            Route::get('trash', [InstructorQuestionController::class, 'trash'])->name('trash');
            Route::post('{id}/restore', [InstructorQuestionController::class, 'restore'])->name('restore');
            Route::delete('{id}/force-delete', [InstructorQuestionController::class, 'forceDelete'])->name('force-delete');
        });
        Route::apiResource('questions', InstructorQuestionController::class);

        // ── Learning Groups (exam results only) ─────────────────────────────
        Route::prefix('learning-groups')->name('learning-groups.')->group(function () {
            Route::get('selection', [InstructorLearningGroupController::class, 'selection'])->name('selection');
            Route::get('{learningGroup}/exams', [InstructorLearningGroupController::class, 'getGroupExams'])->name('exams');
            Route::get('{learningGroup}/exams/{exam}/results/export', [InstructorLearningGroupController::class, 'exportExamResults'])->name('exams.results.export');
            Route::get('{learningGroup}/exams/{exam}/results', [InstructorLearningGroupController::class, 'getExamResults'])->name('exams.results');
            Route::get('{learningGroup}/students/{student}/exam-results', [InstructorLearningGroupController::class, 'getStudentExamResults'])->name('students.exam-results');
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
