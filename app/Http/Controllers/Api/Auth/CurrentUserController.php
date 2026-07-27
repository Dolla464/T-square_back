<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\User\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @tags Authentication
 */
class CurrentUserController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return $this->successResponse(
            new UserResource($request->user()->load(['roles', 'student'])),
            'User fetched successfully'
        );
    }
}
