<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\User\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class AuthenticatedSessionController extends Controller
{
    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): JsonResponse
    {
        $request->authenticate();

        $user = $request->user();
        $user->update(['last_login_at' => now()]);

        $token = $request->user()->createToken('T-Square-Access-Token')->plainTextToken;

        //$request->session()->regenerate();

        return $this->successResponse(
            [
                'token' => $token,
                'user'  => new UserResource($user->load(['roles', 'student'])),
            ],
            'Success'
        );
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): JsonResponse
    {
        // نتأكد إن فيه يوزر عامل Login بالتوكن فعلاً
        if ($request->user()) {
            // مسح التوكن الحالي
            $request->user()->currentAccessToken()->delete();

            return $this->successResponse(null, 'Logged out successfully');
        }

        // لو مفيش يوزر (يعني الـ Token غلط أو مش مبعوت)
        return $this->errorResponse('User not found or already logged out', 401);
    }
}
