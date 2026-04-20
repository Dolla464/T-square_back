<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Http\Resources\User\Courses\CourseDetailsResource;
use App\Http\Resources\User\Courses\CourseListResource;
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
        //return $this->successResponse($courses, 'Courses fetched successfully');
        return CourseListResource::collection($courses)->response()->getData(true);
    }

    public function show($slug)
    {
        $course = $this->courseService->getCourseDetails($slug);
        //return $this->successResponse($course);
        return response()->json([
            'status' => 'success',
            'data' => [
                'course' => new CourseDetailsResource($course['course']),
                'related' => CourseListResource::collection($course['related'])
            ]
        ]);
    }
}
