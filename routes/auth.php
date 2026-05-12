<?php

use App\Http\Controllers\Api\Admin\AdminUserController;
use App\Http\Controllers\Api\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Api\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Api\Auth\NewPasswordController;
use App\Http\Controllers\Api\Auth\PasswordResetLinkController;
use App\Http\Controllers\Api\Auth\VerifyEmailController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// 1. رووتس للضيوف فقط (Guest) - مش محتاجين توكن
Route::middleware('guest')->group(function () {
    Route::post('/register', [AdminUserController::class, 'store'])->name('register');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login');
    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])->name('password.email');
    Route::post('/reset-password', [NewPasswordController::class, 'store'])->name('password.store');
});

// 2. رووتس لليوزر المسجل فقط (Auth Sanctum) - لازم توكن
Route::middleware('auth:sanctum')->group(function () {

    // الـ Logout لازم يكون هنا عشان السيرفر يعرف يمسح التوكن بتاعك
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    // روت اليوزر اللي جربناه واشتغل
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // تأكيد الإيميل لو شغال بالـ API
    Route::post('/email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('verification.send');
    Route::get('/verify-email/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['auth:sanctum', 'signed', 'throttle:6,1'])
        ->name('verification.verify');
});
