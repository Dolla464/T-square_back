<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;

class RegisteredUserController extends Controller
{
    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:' . User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'last_login_at' => now(),
        ]);

        event(new Registered($user));

        // log the user in immediately after registration
        Auth::login($user);

        // generate a Sanctum token for the newly registered user
        $token = $user->createToken('T-Square-Access-Token')->plainTextToken;

        return $this->successResponse(
            [
                'token' => $token,
                'user' => $user->load('roles'), // عشان الـ Front يعرف هو طالب ولا مدرس فوراً
            ],
            'User registered successfully',
            201
        );
    }
}
