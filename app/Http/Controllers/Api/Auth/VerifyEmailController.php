<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Traits\ApiResponseTrait;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request; // استخدم Request العادي

/**
 * @tags Authentication
 */
class VerifyEmailController extends Controller
{
    use ApiResponseTrait;

    /**
     * Mark the authenticated user's email address as verified.
     */
    public function __invoke(Request $request)
    {
        // 1. التحقق من أن المستخدم هو صاحب الرابط (الأمان الكامل)
        // أرسلنا الـ id في المسار، ونتحقق منه مقابل المستخدم المسجل دخوله بالـ Token
        if ($request->user()->getKey() != $request->route('id')) {
            return $this->errorResponse('Unauthorized: This link belongs to another user.', 403);
        }

        // 2. التحقق من الـ Hash (لضمان صحة الرابط والبريد)
        if (! hash_equals(sha1($request->user()->getEmailForVerification()), (string) $request->route('hash'))) {
            return $this->errorResponse('Invalid verification link.', 403);
        }

        // 3. هل البريد مفعل مسبقاً؟
        if ($request->user()->hasVerifiedEmail()) {
            return $this->successResponse('Email already verified.', 200);
        }

        // 4. تفعيل البريد
        if ($request->user()->markEmailAsVerified()) {
            event(new Verified($request->user()));
        }

        return $this->successResponse('Email verified successfully', 200);
    }
}
