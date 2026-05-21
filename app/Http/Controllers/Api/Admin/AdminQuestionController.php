<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUpdateQuestionRequest;
use App\Http\Resources\Admin\AdminQuestionResource;
use App\Models\Question;
use App\Services\Admin\AdminQuestionService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AdminQuestionController extends Controller
{
    protected AdminQuestionService $questionService;

    public function __construct(AdminQuestionService $questionService)
    {
        $this->questionService = $questionService;
    }

    /**
     * Display questions by exam id (for the detailed view page)
     * Example: api/admin/questions?exam_id=16
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate(['exam_id' => 'required|exists:exams,id']);

        $questions = $this->questionService->getQuestionsByExam($request->exam_id, 10);

        return response()->json([
            'status' => 'success',
            'message' => 'Questions fetched successfully',
            'questions_count' => $questions->count(),
            'data' => AdminQuestionResource::collection($questions)
        ], 200);
    }

    /**
     * Create a new question with its choices
     */
    public function store(StoreUpdateQuestionRequest $request): JsonResponse
    {
        $question = $this->questionService->createQuestion($request->validated());

        return $this->successResponse(
            new AdminQuestionResource($question),
            'Question and choices created successfully',
            201
        );
    }

    /**
     * Display the details of a question
     */
    public function show(Question $question): JsonResponse
    {
        return $this->successResponse(
            new AdminQuestionResource($question->load('choices')),
            'Question retrieved successfully'
        );
    }

    /**
     * Update the question and its choices
     */
    public function update(StoreUpdateQuestionRequest $request, Question $question): JsonResponse
    {
        $updatedQuestion = $this->questionService->updateQuestion($question, $request->validated());

        return $this->successResponse(
            new AdminQuestionResource($updatedQuestion),
            'Question and choices updated successfully',
            200
        );
    }
    /**
     * Delete a question
     */
    public function destroy(Question $question): JsonResponse
    {
        $this->questionService->deleteQuestion($question);

        return $this->successResponse(
            null,
            'Question deleted successfully'
        );
    }

    /**
     * Display the deleted questions only
     */
    public function trash(): JsonResponse
    {
        $trashedQuestions = $this->questionService->getTrashedQuestions();

        return $this->successResponse(
            AdminQuestionResource::collection($trashedQuestions),
            'Trashed questions fetched successfully'
        );
    }

    /**
     * Restore a deleted question
     */
    public function restore(int $id): JsonResponse
    {
        $question = $this->questionService->restoreQuestion($id);

        return $this->successResponse(
            new AdminQuestionResource($question),
            'Question and its choices restored successfully'
        );
    }

    /**
     * Force delete a question
     */
    public function forceDelete(int $id): JsonResponse
    {
        $this->questionService->forceDeleteQuestion($id);

        return $this->successResponse(
            null,
            'Question and its choices deleted permanently from system'
        );
    }
}
