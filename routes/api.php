<?php

use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\Notification\NotificationController;
use App\Http\Controllers\Api\User\ExamController;
use App\Http\Controllers\Api\User\ProfileController;
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

require __DIR__ . '/public.php';

/*
|--------------------------------------------------------------------------
| Student Routes (public + authenticated)
|--------------------------------------------------------------------------
*/

require __DIR__ . '/student.php';

/*
|--------------------------------------------------------------------------
| Instructor Routes — auth:sanctum + role:instructor
|--------------------------------------------------------------------------
*/

require __DIR__ . '/instructor.php';

/*
|--------------------------------------------------------------------------
| Receptionist Routes — auth:sanctum + role:receptionist
|--------------------------------------------------------------------------
*/

require __DIR__ . '/receptionist.php';

/*
|--------------------------------------------------------------------------
| Admin Routes — auth:sanctum + role:admin
|--------------------------------------------------------------------------
*/

require __DIR__ . '/admin.php';

/*
|--------------------------------------------------------------------------
| Shared Authenticated Routes — auth:sanctum (any role)
|--------------------------------------------------------------------------
*/

// Attendance — hardware device QR scanner (no user session required, device authenticates via device_id)
Route::post('attendance/scan', [AttendanceController::class, 'scan'])->name('attendance.scan');

// Student attendance QR generation (auth:sanctum, student role handled inside controller)
Route::middleware(['auth:sanctum', 'role:student'])
    ->prefix('student')
    ->name('student.')
    ->group(function () {
        Route::get('attendance/qr', [AttendanceController::class, 'getStudentQr'])->name('attendance.qr');
    });

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
            Route::post('/', 'update')->name('update');
            Route::put('/password', 'updatePassword')->name('password.update');
        });

    // Notifications — static segments before parameterised {id}/read
    Route::controller(NotificationController::class)
        ->prefix('notifications')
        ->name('notifications.')
        ->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('unread-count', 'unreadCount')->name('unread-count');
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
});
