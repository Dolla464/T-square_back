<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Services\UserService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;

class AdminUserController extends Controller
{

    public function store(StoreUserRequest $request, UserService $userService)
    {
        $data = $request->validated();

        // فحص هل المستخدم الحالي أدمن (باستخدام الميدل وير المحمل على الريكويست)
        $isAdmin = $request->user() && $request->user()->hasRole('admin');

        if ($isAdmin) {
            $data['verified'] = now();
        }

        $user = $userService->handleUserCreation($data);

        // تحميل الداتا الفرعية بناءً على الرول
        $user->load($user->role === 'student' ? 'student' : 'instructor');

        // حالة التسجيل العام (ضيف)
        if (!$isAdmin) {
            // إطلاق حدث التسجيل (لإرسال إيميل التحقق)
            event(new Registered($user));

            // توليد التوكن مباشرة (لا حاجة لـ Auth::login في الـ API)
            $token = $user->createToken('T-Square-Access-Token')->plainTextToken;

            return $this->successResponse(
                [
                    'user' => $user,
                    'token' => $token
                ],
                'Registration successful. Please verify your email.',
                201
            );
        }

        // حالة إضافة مستخدم بواسطة الأدمن
        return $this->successResponse(
            $user,
            'Admin: New user created successfully',
            201
        );
    }
}
