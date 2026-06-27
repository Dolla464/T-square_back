<?php

use App\Http\Controllers\Api\Admin\AdminCategoryController;
use App\Http\Controllers\Api\Admin\ChunkedUploadController;
use App\Http\Controllers\Api\Admin\AdminCertificateController;
use App\Http\Controllers\Api\Admin\AdminCourseController;
use App\Http\Controllers\Api\Admin\AdminDiscoveryMediaController;
use App\Http\Controllers\Api\Admin\AdminExamController;
use App\Http\Controllers\Api\Admin\AdminInstructorController;
use App\Http\Controllers\Api\Admin\AdminLearningGroupController;
use App\Http\Controllers\Api\Admin\AdminMessageController;
use App\Http\Controllers\Api\Admin\AdminPaymentController;
use App\Http\Controllers\Api\Admin\AdminQuestionController;
use App\Http\Controllers\Api\Admin\AdminReviewController;
use App\Http\Controllers\Api\Admin\AdminSolutionController;
use App\Http\Controllers\Api\Admin\AdminStudentController;
use App\Http\Controllers\Api\Admin\AdminTagController;
use App\Http\Controllers\Api\Admin\AdminUserController;
use App\Http\Controllers\Api\Admin\SettingController as AdminSettingController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin Routes — auth:sanctum + role:admin
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum', 'role:admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {

        // Update a single general setting (site_name, contact_email, whatsapp,
        // facebook_url, maintenance_mode) — one { key, value } pair per request.
        Route::post('/settings', [AdminSettingController::class, 'update'])
            ->name('settings.update');

        // Current maintenance-mode status (used to hydrate the toggle on load).
        Route::get('/settings/maintenance-status', [AdminSettingController::class, 'getMaintenanceStatus'])
            ->name('settings.maintenance-status');

        // Users
        Route::post('users', [AdminUserController::class, 'store'])
            ->name('users.store');

        // Tags
        Route::get('tags', [AdminTagController::class, 'index'])
            ->name('tags.index');

        // Categories ───────────────────────────────────────────────────────────
        // Static "tree" segment is declared FIRST so that it is never captured
        // by the {category} wildcard of the apiResource below.
        Route::get('categories/tree', [AdminCategoryController::class, 'tree'])
            ->name('categories.tree');

        // Full CRUD without destroy (deleting categories is strictly forbidden).
        Route::apiResource('categories', AdminCategoryController::class)
            ->except(['destroy']);

        // Courses (full CRUD)
        Route::prefix('courses')->name('courses.')->group(function () {
            Route::get('trash', [AdminCourseController::class, 'trash'])->name('trash');
            Route::post('{id}/restore', [AdminCourseController::class, 'restore'])->name('restore');
            Route::delete('{id}/force-delete', [AdminCourseController::class, 'forceDelete'])->name('force-delete');
            // Chunked video upload for course preview lessons.
            // {course:id} forces binding by primary key because Course::getRouteKeyName() returns 'slug'.
            Route::post('{course:id}/previews/chunked-upload', [ChunkedUploadController::class, 'store'])
                ->name('previews.chunked-upload');
        });
        Route::apiResource('courses', AdminCourseController::class);

        // Exams
        Route::prefix('exams')->name('exams.')->group(function () {
            Route::get('trash', [AdminExamController::class, 'trash'])->name('trash');
            Route::post('{id}/restore', [AdminExamController::class, 'restore'])->name('restore');
            Route::delete('{id}/force-delete', [AdminExamController::class, 'forceDelete'])->name('force-delete');
            Route::patch('{id}/toggle-status', [AdminExamController::class, 'toggleStatus'])->name('toggle-status');
        });
        Route::apiResource('exams', AdminExamController::class);

        // Questions
        Route::prefix('questions')->name('questions.')->group(function () {
            Route::get('trash', [AdminQuestionController::class, 'trash'])->name('trash');
            Route::post('{id}/restore', [AdminQuestionController::class, 'restore'])->name('restore');
            Route::delete('{id}/force-delete', [AdminQuestionController::class, 'forceDelete'])->name('force-delete');
        });
        Route::apiResource('questions', AdminQuestionController::class);

        // Instructors — POST used for update to support multipart/form-data uploads
        Route::post('instructors/{instructor}', [AdminInstructorController::class, 'update'])
            ->name('instructors.update');
        Route::apiResource('instructors', AdminInstructorController::class)
            ->except(['store', 'update']);

        // Learning Groups
        Route::prefix('learning-groups')->group(function () {
            Route::get('/selection', [AdminLearningGroupController::class, 'selection']);
            // Per-group bulk actions
            Route::get('{groupId}/unassigned-students', [AdminLearningGroupController::class, 'getUnassignedStudents'])->name('learning-groups.unassigned-students');
            Route::post('{groupId}/bulk-assign',        [AdminLearningGroupController::class, 'bulkAssignStudents'])->name('learning-groups.bulk-assign');
            Route::post('{groupId}/bulk-complete',      [AdminLearningGroupController::class, 'bulkCompleteStudents'])->name('learning-groups.bulk-complete');
            // Schedule & attendance
            Route::get('{learningGroup}/schedule',   [AdminLearningGroupController::class, 'getSchedule'])->name('learning-groups.schedule');
            Route::get('{learningGroup}/sessions',   [AdminLearningGroupController::class, 'getSessions'])->name('learning-groups.sessions');
            Route::get('{learningGroup}/attendance', [AdminLearningGroupController::class, 'getAttendanceReport'])->name('learning-groups.attendance');
        });
        Route::apiResource('learning-groups', AdminLearningGroupController::class);

        // Students — POST used for update (same multipart/form-data reason)
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

        // Messages (read-only – index + show)
        Route::apiResource('messages', AdminMessageController::class)
            ->only(['index', 'show']);

        // Reviews
        Route::put('reviews/{review}', [AdminReviewController::class, 'update'])
            ->name('reviews.update');
        Route::apiResource('reviews', AdminReviewController::class)
            ->except(['store', 'update']);

        // Payments — no manual store; handled via payment gateway callbacks
        Route::apiResource('payments', AdminPaymentController::class)
            ->except(['store']);

        // Certificates
        Route::get('certificates/{certificate}/view', [AdminCertificateController::class, 'viewFile'])
            ->name('certificates.view');
        Route::get('certificates/{certificate}/download', [AdminCertificateController::class, 'downloadFile'])
            ->name('certificates.download');
        Route::apiResource('certificates', AdminCertificateController::class)
            ->except(['store', 'create']);

        // Solutions (full CRUD)
        Route::apiResource('solutions', AdminSolutionController::class);

        // Discovery media & website media
        Route::get('/discovery-media', [AdminDiscoveryMediaController::class, 'index'])->name('discovery-media.index');
        Route::post('/website-media/upload', [AdminDiscoveryMediaController::class, 'upload'])
            ->name('website-media.upload');
        Route::delete('/website-media/delete', [AdminDiscoveryMediaController::class, 'destroy'])
            ->name('website-media.delete');
    });
