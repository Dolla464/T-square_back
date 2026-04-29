<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\UpdateProfileRequest;
use App\Http\Resources\User\Profile\ProfileResource;
use App\Services\ProfileService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private readonly ProfileService $profileService
    ) {}

    public function show(Request $request)
    {
        $user = $this->profileService->show($request->user());

        return $this->successResponse(
            new ProfileResource($user),
            'Profile fetched successfully'
        );
    }

    public function update(UpdateProfileRequest $request)
    {
        $user = $request->user();
        $user = $this->profileService->update($user, $request->validated());

        return $this->successResponse(new ProfileResource($user), 'Profile updated successfully');
    }

    public function getUserProfile(Request $request)
    {
        return $this->show($request);
    }
}
