<?php

namespace App\Http\Controllers;

use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    use ApiResponseTrait;

    public function getUserProfile(Request $request)
    {
        $user = $request->user();

        if ($user->role === 'student') {
            $user->load('student');
        } elseif ($user->role === 'admin') {
            $user->load('admin');
        } elseif ($user->role === 'instructor') {
            $user->load('instructor');
        }

        return $this->successResponse(
            $user,
            'Profile fetched successfully'
        );
    }
}
