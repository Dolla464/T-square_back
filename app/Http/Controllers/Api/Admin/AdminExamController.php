<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Exam\ExamFilterRequest;
use App\Http\Requests\Admin\Exam\UpdateExamRequest;
use App\Services\Admin\AdminExamService;
use App\Http\Resources\Admin\AdminExamResource;
use App\Models\Exam;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AdminExamController extends Controller
{
    protected AdminExamService $examService;

    // Inject the service into the controller (Dependency Injection)
    public function __construct(AdminExamService $examService)
    {
        $this->examService = $examService;
    }

    /**
     * Display the filtered exams for the admin
     */
    public function index(ExamFilterRequest $request): JsonResponse
    {
        // Pass the validated data only to the Service
        $exams = $this->examService->getFilteredExamsForAdmin($request->validated());

        // Return the data wrapped by the Resource with the Pagination automatically
        return $this->paginateResponse(AdminExamResource::collection($exams), 'Exams fetched successfully');
    }

    /**
     * Create a new exam
     */
    public function store(UpdateExamRequest $request): JsonResponse
    {
        $exam = $this->examService->createExam($request->validated());

        return $this->successResponse(
            new AdminExamResource($exam->load('course')),
            'Exam created successfully',
            201
        );
    }

    /**
     * Display the specified exam
     */
    public function show(Exam $exam): JsonResponse
    {
        // Load the course and the questions to the Resource to read them correctly
        $exam->load('course')->loadCount('questions');

        return $this->successResponse(
            new AdminExamResource($exam),
            'Exam retrieved successfully'
        );
    }

    /**
     * Update the specified exam
     */
    public function update(UpdateExamRequest $request, Exam $exam): JsonResponse
    {
        $updatedExam = $this->examService->updateExam($exam, $request->validated());

        return $this->successResponse(
            new AdminExamResource($updatedExam->load('course')),
            'Exam updated successfully'
        );
    }

    /**
     * Delete the specified exam (Soft Delete)
     */
    public function destroy(Exam $exam): JsonResponse
    {
        $this->examService->deleteExam($exam);

        return $this->successResponse(
            new AdminExamResource($exam->load('course')),
            'Exam deleted successfully'
        );
    }

    /**
     * Display the list of deleted exams (trash)
     */
    public function trash(): JsonResponse
    {
        $trashedExams = $this->examService->getTrashedExams();
        return $this->paginateResponse(AdminExamResource::collection($trashedExams), 'Trashed exams fetched successfully');
    }

    /**
     * Restore a deleted exam
     */
    public function restore(int $id): JsonResponse
    {
        $exam = $this->examService->restoreExam($id);

        return $this->successResponse(
            new AdminExamResource($exam->load('course')),
            'Exam restored successfully'
        );
    }

    /**
     * Force delete the specified exam
     */
    public function forceDelete(int $id): JsonResponse
    {
        $this->examService->forceDeleteExam($id);

        return $this->successResponse(
            null,
            'Exam deleted permanently'
        );
    }

    /**
     * Change the status of a specific exam
     */
    public function toggleStatus(Request $request, int $id): JsonResponse
    {
        // The validation will ensure that the passed value is either 0 or 1
        $request->validate([
            'is_active' => 'required|in:0,1',
        ]);

        // Call the Service to update the status
        $exam = $this->examService->toggleExamStatus($id, $request->is_active);

        return $this->successResponse(
            new AdminExamResource($exam), // The Resource of the exam
            'Exam status updated successfully'
        );
    }
}
