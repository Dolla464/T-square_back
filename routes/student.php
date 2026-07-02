<?php

use App\Http\Controllers\Api\User\CategoryController;
use App\Http\Controllers\Api\User\CertificateController;
use App\Http\Controllers\Api\User\ContactUsController;
use App\Http\Controllers\Api\User\CourseController;
use App\Http\Controllers\Api\User\CourseDashboardController;
use App\Http\Controllers\Api\User\CourseReviewController;
use App\Http\Controllers\Api\User\EnrollmentController;
use App\Http\Controllers\Api\User\InstructorController;
use App\Http\Controllers\Api\User\SolutionsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Student Public Routes (No Authentication Required)
|--------------------------------------------------------------------------
*/

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
| Student Authenticated Routes — auth:sanctum
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')
    ->prefix('student')
    ->name('student.')
    ->group(function () {

        Route::get('dashboard/courses', CourseDashboardController::class)
            ->name('courses.dashboard');
        Route::get('dashboard/courses/{id}', [CourseDashboardController::class, 'showCourse'])
            ->name('courses.dashboard.show');

        Route::get('courses/{course_id}/check-enrollment', [EnrollmentController::class, 'checkEnrollment'])
            ->name('courses.check-enrollment');

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

        Route::controller(CourseReviewController::class)
            ->prefix('reviews')
            ->name('reviews.')
            ->group(function () {
                Route::get('eligibility/{courseId}', 'eligibility')->name('eligibility');
                Route::post('/', 'store')->name('store');
            });
    });
