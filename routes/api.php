<?php

use App\Http\Controllers\Api\Admin\AdminCategoryController;
use App\Http\Controllers\Api\Admin\AdminCertificateController;
use App\Http\Controllers\Api\Admin\AdminCourseController;
use App\Http\Controllers\Api\Admin\AdminDiscoveryMediaController;
use App\Http\Controllers\Api\Admin\AdminExamController;
use App\Http\Controllers\Api\Admin\AdminInstructorController;
use App\Http\Controllers\Api\Admin\AdminLearningGroupController;
use App\Http\Controllers\Api\Admin\AdminPaymentController;
use App\Http\Controllers\Api\Admin\AdminQuestionController;
use App\Http\Controllers\Api\Admin\AdminMessageController;
use App\Http\Controllers\Api\Admin\AdminReviewController;
use App\Http\Controllers\Api\Admin\AdminSolutionController;
use App\Http\Controllers\Api\Admin\AdminStudentController;
use App\Http\Controllers\Api\Admin\AdminTagController;
use App\Http\Controllers\Api\Admin\AdminUserController;
use App\Http\Controllers\Api\Admin\SettingController as AdminSettingController;
use App\Http\Controllers\Api\Notification\NotificationController;
use App\Http\Controllers\Api\User\CategoryController;
use App\Http\Controllers\Api\User\CertificateController;
use App\Http\Controllers\Api\User\ContactUsController;
use App\Http\Controllers\Api\User\CourseController;
use App\Http\Controllers\Api\User\CourseDashboardController;
use App\Http\Controllers\Api\User\CourseReviewController;
use App\Http\Controllers\Api\User\EnrollmentController;
use App\Http\Controllers\Api\User\ExamController;
use App\Http\Controllers\Api\User\InstructorController;
use App\Http\Controllers\Api\User\ProfileController;
use App\Http\Controllers\Api\User\SolutionsController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\Api\PublicWebsiteController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Authentication Routes
|--------------------------------------------------------------------------
*/

require __DIR__ . '/auth.php';

/*
|--------------------------------------------------------------------------
| Public Routes (No Authentication Required)
|--------------------------------------------------------------------------
*/

Route::get('/settings/{key}', [SettingController::class, 'getSettingByKey'])
    ->name('settings.show');
Route::get('/website-media/{key}', [PublicWebsiteController::class, 'getMediaByKey'])
    ->name('website-media.get');
// Fixed maintenance page route for students
Route::get('/maintenance', function () {
    return view('maintenance'); // Simply return the maintenance Blade view
})->name('maintenance.page');

// ── Student public browsing routes ────────────────────────────────────────
Route::prefix('student')->name('student.')->group(function () {

    Route::get('categories', [CategoryController::class, 'index'])
        ->name('categories.index');

    Route::get('instructors', [InstructorController::class, 'index'])
        ->name('instructors.index');

    Route::post('contact-us', [ContactUsController::class, 'store'])
        ->name('contact-us.store');

    // Solutions
    Route::controller(SolutionsController::class)
        ->prefix('solutions')
        ->name('solutions.')
        ->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('{solution}', 'show')->name('show');
        });

    // Reviews
    Route::controller(CourseReviewController::class)
        ->prefix('reviews')
        ->name('reviews.')
        ->group(function () {
            Route::get('latest', 'latest')->name('latest');
            Route::get('course/{courseId}', 'course')->name('course');
        });

    // Courses — generic {slug} must come last to avoid swallowing named segments
    Route::controller(CourseController::class)
        ->prefix('courses')
        ->name('courses.')
        ->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('{slug}', 'show')->name('show');
        });
});

/*
|--------------------------------------------------------------------------
| Authenticated Routes — auth:sanctum
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {

    // Authenticated user identity
    Route::get('user', fn(Request $request) => $request->user())
        ->name('user.show');

    // Profile
    Route::controller(ProfileController::class)
        ->prefix('profile')
        ->name('profile.')
        ->group(function () {
            Route::get('/', 'show')->name('show');
            Route::put('/', 'update')->name('update');
        });

    // Notifications — read-all before parameterised {id}/read
    Route::controller(NotificationController::class)
        ->prefix('notifications')
        ->name('notifications.')
        ->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('read-all', 'markAllAsRead')->name('read-all');
            Route::post('{id}/read', 'markAsRead')->name('read');
        });

    // Exams — named static segments before parameterised {id}
    Route::controller(ExamController::class)
        ->prefix('exams')
        ->name('exams.')
        ->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('my-results', 'myResults')->name('my-results');
            Route::post('start', 'start')->name('start');
            Route::post('save-answer', 'answer')->name('save-answer');
            Route::post('{id}/submit', 'submit')->name('submit');
        });

    // ── Student authenticated routes ───────────────────────────────────────
    Route::prefix('student')->name('student.')->group(function () {

        Route::get('dashboard/courses', CourseDashboardController::class)
            ->name('courses.dashboard');

        Route::get('courses/{course_id}/check-enrollment', [EnrollmentController::class, 'checkEnrollment'])
            ->name('courses.check-enrollment')
            ->middleware('auth:sanctum');

        Route::post('enrollments', [EnrollmentController::class, 'store'])
            ->name('enrollments.store');

        // Certificates — {enrollment}/download must be declared before {enrollment}
        Route::controller(CertificateController::class)
            ->prefix('certificates')
            ->name('certificates.')
            ->group(function () {
                Route::get('/', 'index')->name('index');
                Route::get('{enrollment}/view', 'view')->name('view');
                Route::get('{enrollment}/download', 'download')->name('download');
                Route::get('{enrollment}', 'show')->name('show');
            });
    });
});

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
        });
        Route::apiResource('learning-groups', AdminLearningGroupController::class);

        // Students — POST used for update (same multipart/form-data reason)
        Route::post('students/{student}', [AdminStudentController::class, 'update'])
            ->name('students.update');
        Route::prefix('students')->group(function () {
            // 1. Update student status
            Route::patch('{student}/status', [AdminStudentController::class, 'updateStatus']);

            // 2. Toggle user verification
            Route::post('{student}/toggle-verify', [AdminStudentController::class, 'toggleVerify']);
            // 3. Update the course group of the student
            Route::put('{student}/courses/{course}/group', [AdminStudentController::class, 'updateCourseGroup']);
            // 4. Update the course status of the student
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

        // Settings
        Route::get('/discovery-media', [AdminDiscoveryMediaController::class, 'index'])->name('discovery-media.index');
        // Unified route to upload media (whether for hero, about, or discovery)
        Route::post('/website-media/upload', [AdminDiscoveryMediaController::class, 'upload'])
            ->name('website-media.upload');

        // Unified route to delete a single image from any array section
        Route::delete('/website-media/delete', [AdminDiscoveryMediaController::class, 'destroy'])
            ->name('website-media.delete');
    });
