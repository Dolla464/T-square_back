<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\User\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * @tags Authentication
 */
class AuthenticatedSessionController extends Controller
{
    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): JsonResponse
    {
        $request->authenticate();

        $user = $request->user();
        $user->forceFill(['last_login_at' => now()])->save();

        if ($this->isStatefulRequest($request)) {
            $request->session()->regenerate();

            return $this->successResponse(
                [
                    'user' => new UserResource($user->load(['roles', 'student'])),
                ],
                'Success'
            );
        }

        $token = $user->createToken('T-Square-Access-Token')->plainTextToken;

        return $this->successResponse(
            [
                'token' => $token,
                'user' => new UserResource($user->load(['roles', 'student'])),
            ],
            'Success'
        );
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return $this->errorResponse('User not found or already logged out', 401);
        }

        if ($this->isStatefulRequest($request)) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        } else {
            $token = $user->currentAccessToken();
            if ($token instanceof PersonalAccessToken) {
                $token->delete();
            }
        }

        return $this->successResponse(null, 'Logged out successfully');
    }

    private function isStatefulRequest(Request $request): bool
    {
        return EnsureFrontendRequestsAreStateful::fromFrontend($request);
    }
}
