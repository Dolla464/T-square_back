<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Services\UserService;
use App\Traits\HandleImageUploadTrait;
use Illuminate\Auth\Events\Registered;

/**
 * @tags Admin: Users
 */
class AdminUserController extends Controller
{
    use HandleImageUploadTrait;

    public function store(StoreUserRequest $request, UserService $userService)
    {
        // 1. get the validated data
        $data = $request->validated();

        // 2. handle the image upload using the trait
        if ($request->hasFile('avatar')) {
            // pass the file, and the folder name (avatars)
            $data['avatar'] = $this->uploadImage($request->file('avatar'), 'avatars');
        }

        // 3. determine the activation status based on who is creating the account
        // if the current user is an admin, the account is activated immediately
        $isAdmin = auth()->check() && auth()->user()->hasRole('admin');

        if ($isAdmin) {
            $data['verified'] = now();
        }

        $data['created_by'] = $isAdmin ? 'admin' : 'site';

        // 4. execute the creation process through the Service
        $user = $userService->handleUserCreation($data);

        // 5. load the relationships needed to display in the response (Response)
        $user->load($user->role === 'student' ? 'student' : 'instructor');

        // --- the first case: external registration (Student Self-Register) ---
        if (! $isAdmin) {
            // trigger the registration event (to send the verification email)
            event(new Registered($user));

            // generate the login token (Sanctum)
            $token = $user->createToken('T-Square-Access-Token')->plainTextToken;

            return $this->successResponse(
                [
                    'user' => $user,
                    'token' => $token,
                ],
                'Registered successfully. Please verify your email.',
                201
            );
        }

        // --- the second case: addition by the admin ---
        return $this->successResponse(
            $user,
            'Admin: New user created successfully',
            201
        );
    }
}
