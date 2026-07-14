<?php

use App\Http\Controllers\Api\Admin\AdminCourseController;
use App\Http\Controllers\Api\Admin\AdminInstructorController;
use App\Http\Controllers\Api\Admin\AdminLearningGroupController;
use App\Http\Controllers\Api\Admin\AdminPaymentController;
use App\Http\Controllers\Api\Admin\AdminStudentController;
use App\Http\Controllers\Api\Admin\AdminUserController;
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

        // ── Users (student registration) ──────────────────────────────────────
        Route::post('users', [AdminUserController::class, 'store'])
            ->name('users.store');

        // ── Students ──────────────────────────────────────────────────────────
        Route::post('students/{student}', [AdminStudentController::class, 'update'])
            ->name('students.update');
        Route::prefix('students')->group(function () {
            Route::patch('{student}/status', [AdminStudentController::class, 'updateStatus']);
            Route::post('{student}/toggle-verify', [AdminStudentController::class, 'toggleVerify']);
            Route::put('{student}/courses/{course}/group', [AdminStudentController::class, 'updateCourseGroup']);
            Route::put('{student}/courses/{course}/status', [AdminStudentController::class, 'updateCourseStatus']);
        });
        Route::apiResource('students', AdminStudentController::class)
            ->except(['store', 'update']);

        // ── Payments (no export, no destroy) ──────────────────────────────────
        Route::get('payments/export', fn () => response()->json([
            'status' => 'error',
            'message' => 'Forbidden',
        ], 403))->name('payments.export.denied');
        Route::delete('payments/{payment}', fn () => response()->json([
            'status' => 'error',
            'message' => 'Forbidden',
        ], 403))->name('payments.destroy.denied');
        Route::apiResource('payments', AdminPaymentController::class)
            ->only(['index', 'store', 'show', 'update']);

        // ── Learning Groups ───────────────────────────────────────────────────
        Route::prefix('learning-groups')->group(function () {
            Route::get('/selection', [AdminLearningGroupController::class, 'selection']);
            Route::get('{groupId}/unassigned-students', [AdminLearningGroupController::class, 'getUnassignedStudents'])->name('learning-groups.unassigned-students');
            Route::post('{groupId}/bulk-assign', [AdminLearningGroupController::class, 'bulkAssignStudents'])->name('learning-groups.bulk-assign');
            Route::post('{groupId}/bulk-complete', [AdminLearningGroupController::class, 'bulkCompleteStudents'])->name('learning-groups.bulk-complete');
            Route::get('{learningGroup}/schedule', [AdminLearningGroupController::class, 'getSchedule'])->name('learning-groups.schedule');
            Route::get('{learningGroup}/sessions', [AdminLearningGroupController::class, 'getSessions'])->name('learning-groups.sessions');
            Route::get('{learningGroup}/attendance-summary', [AdminLearningGroupController::class, 'getAttendanceSummary'])->name('learning-groups.attendance-summary');
            Route::get('{learningGroup}/sessions/{session}/attendance/export', [AdminLearningGroupController::class, 'exportSessionAttendance'])->name('learning-groups.sessions.attendance.export');
            Route::get('{learningGroup}/sessions/{session}/attendance', [AdminLearningGroupController::class, 'getSessionAttendance'])->name('learning-groups.sessions.attendance');
            Route::post('{learningGroup}/sessions/{session}/attendance/mark', [AdminLearningGroupController::class, 'markSessionAttendance'])->name('learning-groups.sessions.attendance.mark');
            Route::get('{learningGroup}/students/{student}/attendance/export', [AdminLearningGroupController::class, 'exportStudentCourseAttendance'])->name('learning-groups.students.attendance.export');
            Route::get('{learningGroup}/students/{student}/attendance', [AdminLearningGroupController::class, 'getStudentCourseAttendance'])->name('learning-groups.students.attendance');
            Route::get('{learningGroup}/attendance', [AdminLearningGroupController::class, 'getAttendanceReport'])->name('learning-groups.attendance');
            Route::get('{learningGroup}/students/export', [AdminLearningGroupController::class, 'exportStudents'])->name('learning-groups.students.export');
            Route::get('{learningGroup}/exams', [AdminLearningGroupController::class, 'getGroupExams'])->name('learning-groups.exams');
            Route::get('{learningGroup}/exams/{exam}/results/export', [AdminLearningGroupController::class, 'exportExamResults'])->name('learning-groups.exams.results.export');
            Route::get('{learningGroup}/exams/{exam}/results', [AdminLearningGroupController::class, 'getExamResults'])->name('learning-groups.exams.results');
            Route::get('{learningGroup}/students/{student}/exam-results', [AdminLearningGroupController::class, 'getStudentExamResults'])->name('learning-groups.students.exam-results');
            Route::get('{learningGroup}/students/{student}/exam-attempts/{attempt}/review', [AdminLearningGroupController::class, 'getStudentAttemptReview'])->name('learning-groups.students.exam-attempts.review');
        });
        Route::apiResource('learning-groups', AdminLearningGroupController::class);

        // ── Helpers (read-only for forms) ─────────────────────────────────────
        Route::get('courses', [AdminCourseController::class, 'index'])->name('courses.index');
        Route::get('courses/{course}', [AdminCourseController::class, 'show'])->name('courses.show');
        Route::get('instructors', [AdminInstructorController::class, 'index'])->name('instructors.index');
        Route::get('instructors/{instructor}', [AdminInstructorController::class, 'show'])->name('instructors.show');

    });
