<?php

use App\Http\Controllers\Api\Admin\AdminSolutionController;
use App\Http\Controllers\Api\Admin\AdminTagController;
use App\Http\Controllers\Api\User\CategoryController;
use App\Http\Controllers\Api\User\ContactUsController;
use App\Http\Controllers\Api\User\CourseController;
use App\Http\Controllers\Api\User\CourseReviewController;
use App\Http\Controllers\Api\User\InstructorController;
use App\Http\Controllers\Api\User\SolutionsController;
use App\Http\Controllers\Api\User\StudentController;
use App\Http\Controllers\Api\User\UserController;
use App\Http\Controllers\SettingController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


require __DIR__ . '/auth.php';

Route::middleware(['auth:sanctum'])->get('/user', function (Request $request) {
    return $request->user();
});

Route::get('/settings/{key}', [SettingController::class, 'getSettingByKey']);

Route::group(['prefix' => 'student', 'namespace' => 'App\Http\Controllers\Api\User'], function () {
    Route::get('/categories', [CategoryController::class, 'index']); // للأقسام
    Route::get('/courses', [CourseController::class, 'index']);      // للكورسات
    Route::get('/courses/{slug}', [CourseController::class, 'show']);
    Route::get('/solutions', [SolutionsController::class, 'index']);     // جميع الحلول
    Route::get('/solutions/{solution}', [SolutionsController::class, 'show']);
    Route::get('/instructors', [InstructorController::class, 'index']); // عرض ال instructors
    Route::post('/contact-us', [ContactUsController::class, 'store'])->name('contact-us.store'); // تواصل معنا
    Route::get('/reviews/latest', [CourseReviewController::class, 'latest']);; // يعرض اخر 5 reviews بس
});
// Users Management (CRUD)
Route::group(['prefix' => 'users', 'namespace' => 'App\Http\Controllers\Api\User'], function () {
    Route::get('/', [UserController::class, 'index'])->name('users.index');
    Route::post('/', [UserController::class, 'store'])->name('users.store');
    Route::get('/search', [UserController::class, 'search'])->name('users.search');
    Route::post('/{id}/restore', [UserController::class, 'restore'])->name('users.restore');
    Route::delete('/{id}/force-delete', [UserController::class, 'forceDelete'])->name('users.force-delete');
    Route::get('/{user}', [UserController::class, 'show'])->name('users.show');
    Route::post('/{user}', [UserController::class, 'update'])->name('users.update');
    Route::delete('/{user}', [UserController::class, 'destroy'])->name('users.destroy');
});

// Students Management (CRUD)
Route::group(['prefix' => 'students', 'namespace' => 'App\Http\Controllers\Api\User'], function () {
    Route::get('/', [StudentController::class, 'index'])->name('students.index');
    Route::post('/', [StudentController::class, 'store'])->name('students.store');
    Route::get('/{student}', [StudentController::class, 'show'])->name('students.show');
    Route::post('/{student}', [StudentController::class, 'update'])->name('students.update');
    Route::delete('/{student}', [StudentController::class, 'destroy'])->name('students.destroy');
});

Route::group(['prefix' => 'admin', 'namespace' => 'App\Http\Controllers\Api\Admin'], function () {
    Route::get('/tags', [AdminTagController::class, 'index']);
    // Solutions Management
    Route::apiResource('solutions', AdminSolutionController::class);
});
