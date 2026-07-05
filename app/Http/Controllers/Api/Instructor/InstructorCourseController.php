<?php

namespace App\Http\Controllers\Api\Instructor;

use App\Http\Controllers\Concerns\EnsuresInstructorOwnsResource;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\Course\AdminCourseResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @tags Instructor: Courses
 */
class InstructorCourseController extends Controller
{
    use EnsuresInstructorOwnsResource;

    public function index(Request $request): JsonResponse
    {
        $instructor = $this->resolveInstructor($request);
        if (!$instructor) {
            return $this->instructorNotFoundResponse();
        }

        $perPage = min(100, max(1, (int) $request->query('per_page', 100)));

        $courses = $instructor->courses()
            ->select('id', 'title', 'slug', 'status')
            ->latest()
            ->paginate($perPage);

        return $this->paginateResponse(
            AdminCourseResource::collection($courses),
            'Courses fetched successfully'
        );
    }
}
