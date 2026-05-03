<?php

use App\Http\Controllers\Api\Admin\AdminInstructorController;
use App\Http\Controllers\Api\Admin\AdminSolutionController;
use App\Http\Controllers\Api\Admin\AdminTagController;
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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


require __DIR__ . '/auth.php';

Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
    // profile routes
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::put('/profile', [ProfileController::class, 'update']);
    // مسارات الإشعارات
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);
    // Exam routes
    Route::prefix('exams')->group(function () {
        Route::get('/', [ExamController::class, 'index']);
        Route::post('/start', [ExamController::class, 'start']); // بدء الامتحان
        Route::post('/save-answer', [ExamController::class, 'answer']); // حفظ إجابة سؤال
        Route::post('/{id}/submit', [ExamController::class, 'submit']); // إنهاء الامتحان
        Route::get('/my-results', [ExamController::class, 'myResults']);

    });
});


Route::get('/settings/{key}', [SettingController::class, 'getSettingByKey']);

Route::group(['prefix' => 'student', 'namespace' => 'App\Http\Controllers\Api\User'], function () {
    Route::get('/categories', [CategoryController::class, 'index']); // للأقسام
    Route::get('/courses', [CourseController::class, 'index']);      // للكورسات
    Route::get('/courses/dashboard', CourseDashboardController::class)->middleware('auth:sanctum'); // لوحة تحكم الكورسات
    Route::get('/courses/{slug}', [CourseController::class, 'show']);
    Route::post('/enrollments', [EnrollmentController::class, 'store'])->middleware('auth:sanctum');
    Route::get('/solutions', [SolutionsController::class, 'index']);     // جميع الحلول
    Route::get('/solutions/{solution}', [SolutionsController::class, 'show']);
    Route::get('/instructors', [InstructorController::class, 'index']); // عرض ال instructors
    Route::post('/contact-us', [ContactUsController::class, 'store'])->name('contact-us.store'); // تواصل معنا
    Route::get('/reviews/latest', [CourseReviewController::class, 'latest']); // يعرض اخر 5 reviews بس
    Route::get('/reviews/course/{courseId}', [CourseReviewController::class, 'course']); // يعرض reviews الخاصة بكورس معين
    Route::get('/enrollments/{enrollment}', [CertificateController::class, 'show'])// عرض بيانات الشهادة
     ->name('certificate.show');
    Route::get('/enrollments/{enrollment}/download', [CertificateController::class, 'download'])// تحميل الشهادة
     ->name('certificate.download');

});

Route::group(['prefix' => 'admin', 'namespace' => 'App\Http\Controllers\Api\Admin'], function () {
    Route::get('/tags', [AdminTagController::class, 'index']);
    // Solutions Management
    Route::apiResource('solutions', AdminSolutionController::class);
    // Instructors Management
    Route::post('instructors/{instructor}', [AdminInstructorController::class, 'update']);
    Route::apiResource('instructors', AdminInstructorController::class)->except(['store', 'update']);
});


