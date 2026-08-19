<?php

use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\Auth\CurrentUserController;
use App\Http\Controllers\Api\Notification\NotificationController;
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

// Attendance — hardware device QR scanner (device authenticates via device_id)
Route::post('attendance/scan', [AttendanceController::class, 'scan'])
    ->middleware(['attendance.device', 'throttle:60,1'])
    ->name('attendance.scan');

Route::middleware('auth:sanctum')->group(function () {

    // Authenticated user identity
    Route::get('user', [CurrentUserController::class, 'show'])
        ->name('user.show');

    // Profile
    Route::controller(ProfileController::class)
        ->prefix('profile')
        ->name('profile.')
        ->group(function () {
            Route::get('/', 'show')->name('show');
            Route::post('/', 'update')->middleware('throttle:10,1')->name('update');
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
});
