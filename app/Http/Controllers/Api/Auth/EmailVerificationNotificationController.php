<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Traits\ApiResponseTrait; // افترضت أنك تستخدم هذا الـ Trait كما في الـ Controller السابق
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmailVerificationNotificationController extends Controller
{
    use ApiResponseTrait;

    /**
     * Send a new email verification notification.
     */
    public function store(Request $request): JsonResponse
    {
        // إذا كان البريد مفعل بالفعل، نرسل رد JSON بدلاً من إعادة التوجيه
        if ($request->user()->hasVerifiedEmail()) {
            return $this->successResponse('Email is already verified.', 200);
        }

        // إرسال الإشعار (سيستخدم الرابط المخصص الذي وضعناه في AppServiceProvider)
        $request->user()->sendEmailVerificationNotification();

        return $this->successResponse('Verification link sent successfully to your email.', 200);
    }
}
