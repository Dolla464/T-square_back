<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CourseStoreRequest;
use App\Http\Requests\Admin\CourseUpdateRequest;
use App\Http\Resources\Admin\Course\AdminCourseResource;
use App\Services\Admin\AdminCourseService;
use Illuminate\Http\Request;

/**
 * @tags Admin: Courses
 */
class AdminCourseController extends Controller
{
    protected $courseService;

    public function __construct(AdminCourseService $courseService)
    {
        $this->courseService = $courseService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $perPage = $request->query('per_page', 10);

        $filters = $request->only(['search', 'status', 'category_id']);

        $courses = $this->courseService->index($filters, (int) $perPage);

        return AdminCourseResource::collection($courses);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(CourseStoreRequest $request)
    {
        $data = $request->validated();

        $course = $this->courseService->create($data);

        return $this->successResponse(
            new AdminCourseResource($course),
            'Course created successfully',
            201
        );
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $course = $this->courseService->show($id);

        return new AdminCourseResource($course);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(CourseUpdateRequest $request, string $id)
    {
        $data = $request->validated();

        $course = $this->courseService->update($id, $data);

        return new AdminCourseResource($course);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $this->courseService->destroy($id);

        return response()->json(null, 204);
    }

    /**
     * Display a listing of the trashed resources.
     */
    public function trash(Request $request)
    {
        $trashedCourses = $this->courseService->getTrashedCourses($request->get('per_page', 10));

        return AdminCourseResource::collection($trashedCourses);
    }

    /**
     * Restore the specified resource from storage.
     */
    public function restore($id)
    {
        $this->courseService->restoreCourse($id);
        return $this->successResponse(null, 'Course restored successfully');
    }

    /**
     * Force delete the specified resource from storage.
     */
    public function forceDelete($id)
    {
        $this->courseService->forceDeleteCourse($id);
        return $this->successResponse(null, 'Course deleted permanently');
    }
}
