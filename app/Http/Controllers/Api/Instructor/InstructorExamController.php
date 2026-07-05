<?php

namespace App\Http\Controllers\Api\Instructor;

use App\Http\Controllers\Concerns\EnsuresInstructorOwnsResource;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Exam\ExamFilterRequest;
use App\Http\Requests\Instructor\Exam\InstructorUpdateExamRequest;
use App\Http\Resources\Admin\AdminExamResource;
use App\Models\Exam;
use App\Services\Instructor\InstructorExamService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @tags Instructor: Exams
 */
class InstructorExamController extends Controller
{
    use EnsuresInstructorOwnsResource;

    public function __construct(private InstructorExamService $examService)
    {}

    public function index(ExamFilterRequest $request): JsonResponse
    {
        $instructor = $this->resolveInstructor($request);
        if (!$instructor) {
            return $this->instructorNotFoundResponse();
        }

        $exams = $this->examService->getFilteredExamsForInstructor(
            $instructor->id,
            $request->validated()
        );

        return $this->paginateResponse(AdminExamResource::collection($exams), 'Exams fetched successfully');
    }

    public function store(InstructorUpdateExamRequest $request): JsonResponse
    {
        $instructor = $this->resolveInstructor($request);
        if (!$instructor) {
            return $this->instructorNotFoundResponse();
        }

        $exam = $this->examService->createExam($request->validated());

        return $this->successResponse(
            new AdminExamResource($exam->load('course')),
            'Exam created successfully',
            201
        );
    }

    public function show(Request $request, Exam $exam): JsonResponse
    {
        $instructor = $this->resolveInstructor($request);
        if (!$instructor) {
            return $this->instructorNotFoundResponse();
        }

        if ($response = $this->verifyExamOwnership($exam, $instructor)) {
            return $response;
        }

        $exam->load('course')->loadCount('questions');

        return $this->successResponse(
            new AdminExamResource($exam),
            'Exam retrieved successfully'
        );
    }

    public function update(InstructorUpdateExamRequest $request, Exam $exam): JsonResponse
    {
        $instructor = $this->resolveInstructor($request);
        if (!$instructor) {
            return $this->instructorNotFoundResponse();
        }

        if ($response = $this->verifyExamOwnership($exam, $instructor)) {
            return $response;
        }

        $updatedExam = $this->examService->updateExam($exam, $request->validated());

        return $this->successResponse(
            new AdminExamResource($updatedExam->load('course')),
            'Exam updated successfully'
        );
    }

    public function destroy(Request $request, Exam $exam): JsonResponse
    {
        $instructor = $this->resolveInstructor($request);
        if (!$instructor) {
            return $this->instructorNotFoundResponse();
        }

        if ($response = $this->verifyExamOwnership($exam, $instructor)) {
            return $response;
        }

        $this->examService->deleteExam($exam);

        return $this->successResponse(
            new AdminExamResource($exam->load('course')),
            'Exam deleted successfully'
        );
    }

    public function trash(Request $request): JsonResponse
    {
        $instructor = $this->resolveInstructor($request);
        if (!$instructor) {
            return $this->instructorNotFoundResponse();
        }

        $perPage = (int) $request->query('per_page', 10);
        $trashedExams = $this->examService->getTrashedExams($instructor->id, $perPage);

        return $this->paginateResponse(AdminExamResource::collection($trashedExams), 'Trashed exams fetched successfully');
    }

    public function restore(Request $request, int $id): JsonResponse
    {
        $instructor = $this->resolveInstructor($request);
        if (!$instructor) {
            return $this->instructorNotFoundResponse();
        }

        $exam = $this->examService->restoreExam($id, $instructor->id);

        return $this->successResponse(
            new AdminExamResource($exam->load('course')),
            'Exam restored successfully'
        );
    }

    public function forceDelete(Request $request, int $id): JsonResponse
    {
        $instructor = $this->resolveInstructor($request);
        if (!$instructor) {
            return $this->instructorNotFoundResponse();
        }

        $this->examService->forceDeleteExam($id, $instructor->id);

        return $this->successResponse(null, 'Exam deleted permanently');
    }

    public function toggleStatus(Request $request, int $id): JsonResponse
    {
        $instructor = $this->resolveInstructor($request);
        if (!$instructor) {
            return $this->instructorNotFoundResponse();
        }

        $request->validate([
            'is_active' => 'required|in:0,1',
        ]);

        $exam = $this->examService->toggleExamStatus($id, $request->is_active, $instructor->id);

        return $this->successResponse(
            new AdminExamResource($exam),
            'Exam status updated successfully'
        );
    }
}
