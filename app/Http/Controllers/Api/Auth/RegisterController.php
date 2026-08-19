<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterStudentRequest;
use App\Http\Resources\User\UserResource;
use App\Services\UserService;
use App\Traits\HandleImageUploadTrait;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

/**
 * @tags Authentication
 */
class RegisterController extends Controller
{
    use HandleImageUploadTrait;

    public function store(RegisterStudentRequest $request, UserService $userService)
    {
        $data = $request->safePayload();

        if ($request->hasFile('avatar')) {
            $data['avatar'] = $this->uploadImage($request->file('avatar'), 'avatars');
        }

        $user = $userService->registerStudent($data);

        event(new Registered($user));

        $request->clearRateLimiter();

        $user->load('student');

        if ($this->isStatefulRequest($request)) {
            Auth::login($user);
            $request->session()->regenerate();

            return $this->successResponse(
                [
                    'user' => new UserResource($user->load(['roles', 'student'])),
                ],
                'Registered successfully. Please verify your email.',
                201
            );
        }

        $token = $user->createToken('T-Square-Access-Token')->plainTextToken;

        return $this->successResponse(
            [
                'user' => new UserResource($user->load(['roles', 'student'])),
                'token' => $token,
            ],
            'Registered successfully. Please verify your email.',
            201
        );
    }

    private function isStatefulRequest(Request $request): bool
    {
        return EnsureFrontendRequestsAreStateful::fromFrontend($request);
    }
}
