<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\LessonStoreRequest;
use App\Http\Requests\Admin\LessonUpdateRequest;
use App\Http\Resources\Admin\AdminLessonResource;
use App\Models\Course;
use App\Models\Lesson;
use App\Services\Admin\AdminLessonService;
use Illuminate\Http\JsonResponse;

class AdminLessonController extends Controller
{
    public function __construct(
        private readonly AdminLessonService $lessonService,
    ) {}

    public function index(Course $course): JsonResponse
    {
        $lessons = $this->lessonService->listForCourse($course->id);

        return $this->successResponse(
            AdminLessonResource::collection($lessons),
            'Lessons retrieved successfully.'
        );
    }

    public function store(LessonStoreRequest $request, Course $course): JsonResponse
    {
        $lesson = $this->lessonService->create($course, $request->validated());

        return $this->successResponse(
            new AdminLessonResource($lesson),
            'Lesson created successfully.',
            201
        );
    }

    public function update(LessonUpdateRequest $request, Course $course, Lesson $lesson): JsonResponse
    {
        $lesson = $this->lessonService->update($course, $lesson, $request->validated());

        return $this->successResponse(
            new AdminLessonResource($lesson),
            'Lesson updated successfully.'
        );
    }

    public function destroy(Course $course, Lesson $lesson): JsonResponse
    {
        $this->lessonService->delete($course, $lesson);

        return $this->successResponse(null, 'Lesson deleted successfully.');
    }
}
