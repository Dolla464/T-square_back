<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Services\User\CourseService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;

class CourseController extends Controller
{
    use ApiResponseTrait;

    protected $courseService;

    public function __construct(CourseService $courseService)
    {
        $this->courseService = $courseService;
    }

    public function index(Request $request)
    {
        $courses = $this->courseService->getActiveCourses($request->all());
        return $this->successResponse($courses, 'Courses fetched successfully');
    }

    public function show($slug)
    {
        $course = $this->courseService->getCourseDetails($slug);
        return $this->successResponse($course);
    }
}
