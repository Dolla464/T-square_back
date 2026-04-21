<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Http\Resources\InstructorResource;
use App\Services\InstructorService;
use App\Services\User\InstructorService as UserInstructorService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;

class InstructorController extends Controller
{
    use ApiResponseTrait;

    protected $instructorService;

    // بنعمل Injection للـ Service في الـ Constructor
    public function __construct(UserInstructorService $instructorService)
    {
        $this->instructorService = $instructorService;
    }

    public function index(): JsonResponse
    {
        // بنادي على الـ Service تجيب الداتا الخام
        $instructors = $this->instructorService->getActiveInstructors(10);
       // بنعدي الـ items على الـ Resource الأول قبل ما نبعتها للـ trait (ده شغل ال controller)
        $instructors->through(fn ($instructor) => new InstructorResource($instructor));

        return $this->paginateResponse($instructors, 'Instructors fetched successfully.');
    }
}