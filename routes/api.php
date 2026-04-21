<?php

use App\Http\Controllers\Api\User\CategoryController;
use App\Http\Controllers\Api\User\CourseController;
use App\Http\Controllers\Api\User\InstructorController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


require __DIR__ . '/auth.php';

Route::middleware(['auth:sanctum'])->get('/user', function (Request $request) {
    return $request->user();
});

Route::group(['prefix' => 'student', 'namespace' => 'App\Http\Controllers\Api\User'], function () {
    Route::get('/categories', [CategoryController::class, 'index']); // للأقسام
    Route::get('/courses', [CourseController::class, 'index']);      // للكورسات
    Route::get('/courses/{slug}', [CourseController::class, 'show']);
    Route::get('/instructors', [InstructorController::class, 'index']); // عرض ال instructors
});
