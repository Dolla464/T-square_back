<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Http\Resources\User\Instructors\InstructorResource;
use App\Services\User\InstructorService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;

class InstructorController extends Controller
{
    use ApiResponseTrait;

    protected $instructorService;

    // بنعمل Injection للـ Service في الـ Constructor
    public function __construct(InstructorService $instructorService)
    {
        $this->instructorService = $instructorService;
    }

    public function index(): JsonResponse
    {
        // بنادي على الـ Service تجيب الداتا الخام
        $instructors = $this->instructorService->getActiveInstructors(10);

        return $this->paginateResponse(
            InstructorResource::collection($instructors), 
            'Instructors fetched successfully'
        );
    }
}
