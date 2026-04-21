<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Http\Resources\InstructorResource;
use App\Models\Instructor;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;

class InstructorController extends Controller
{
    use ApiResponseTrait;

    public function index(): JsonResponse
    {
        $instructors = Instructor::query()
            ->select([
                'instructors.id',
                'instructors.user_id',
                'instructors.full_name',
                'instructors.field',
                'instructors.avatar',
                'instructors.insta_url',
                'instructors.linkedin_url',
                'instructors.facebook_url',
            ])
            ->where('instructors.status', 'active')
            ->with(['user:id,email'])
            ->paginate(10);

        // بنعدي الـ items على الـ Resource الأول قبل ما نبعتها للـ trait
        $instructors->through(fn ($instructor) => new InstructorResource($instructor));

        return $this->paginateResponse($instructors, 'Instructors fetched successfully.');
    }
}
