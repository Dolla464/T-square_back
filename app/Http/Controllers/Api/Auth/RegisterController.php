<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterStudentRequest;
use App\Services\UserService;
use App\Traits\HandleImageUploadTrait;
use Illuminate\Auth\Events\Registered;

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

        $token = $user->createToken('T-Square-Access-Token')->plainTextToken;

        $user->load('student');

        return $this->successResponse(
            [
                'user' => $user,
                'token' => $token,
            ],
            'Registered successfully. Please verify your email.',
            201
        );
    }
}
