<?php

namespace App\Http\Controllers\Api\Instructor;

use App\Http\Controllers\Concerns\EnsuresInstructorOwnsResource;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUpdateQuestionRequest;
use App\Http\Resources\Admin\AdminQuestionResource;
use App\Models\Exam;
use App\Models\Question;
use App\Services\Instructor\InstructorQuestionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @tags Instructor: Questions
 */
class InstructorQuestionController extends Controller
{
    use EnsuresInstructorOwnsResource;

    public function __construct(private InstructorQuestionService $questionService)
    {}

    public function index(Request $request): JsonResponse
    {
        $instructor = $this->resolveInstructor($request);
        if (!$instructor) {
            return $this->instructorNotFoundResponse();
        }

        $request->validate(['exam_id' => 'required|exists:exams,id']);

        $exam = Exam::findOrFail($request->exam_id);
        if ($response = $this->verifyExamOwnership($exam, $instructor)) {
            return $response;
        }

        $questions = $this->questionService->getQuestionsByExam($request->exam_id);

        return response()->json([
            'status' => 'success',
            'message' => 'Questions fetched successfully',
            'questions_count' => $questions->count(),
            'data' => AdminQuestionResource::collection($questions),
        ], 200);
    }

    public function store(StoreUpdateQuestionRequest $request): JsonResponse
    {
        $instructor = $this->resolveInstructor($request);
        if (!$instructor) {
            return $this->instructorNotFoundResponse();
        }

        $exam = Exam::findOrFail($request->exam_id);
        if ($response = $this->verifyExamOwnership($exam, $instructor)) {
            return $response;
        }

        $question = $this->questionService->createQuestion($request->validated());

        return $this->successResponse(
            new AdminQuestionResource($question),
            'Question and choices created successfully',
            201
        );
    }

    public function show(Request $request, Question $question): JsonResponse
    {
        $instructor = $this->resolveInstructor($request);
        if (!$instructor) {
            return $this->instructorNotFoundResponse();
        }

        if ($response = $this->verifyQuestionOwnership($question, $instructor)) {
            return $response;
        }

        return $this->successResponse(
            new AdminQuestionResource($question->load('choices')),
            'Question retrieved successfully'
        );
    }

    public function update(StoreUpdateQuestionRequest $request, Question $question): JsonResponse
    {
        $instructor = $this->resolveInstructor($request);
        if (!$instructor) {
            return $this->instructorNotFoundResponse();
        }

        if ($response = $this->verifyQuestionOwnership($question, $instructor)) {
            return $response;
        }

        $exam = Exam::findOrFail($request->exam_id);
        if ($response = $this->verifyExamOwnership($exam, $instructor)) {
            return $response;
        }

        $updatedQuestion = $this->questionService->updateQuestion($question, $request->validated());

        return $this->successResponse(
            new AdminQuestionResource($updatedQuestion),
            'Question and choices updated successfully',
            200
        );
    }

    public function destroy(Request $request, Question $question): JsonResponse
    {
        $instructor = $this->resolveInstructor($request);
        if (!$instructor) {
            return $this->instructorNotFoundResponse();
        }

        if ($response = $this->verifyQuestionOwnership($question, $instructor)) {
            return $response;
        }

        $this->questionService->deleteQuestion($question);

        return $this->successResponse(null, 'Question deleted successfully');
    }

    public function trash(Request $request): JsonResponse
    {
        $instructor = $this->resolveInstructor($request);
        if (!$instructor) {
            return $this->instructorNotFoundResponse();
        }

        $request->validate(['exam_id' => 'required|exists:exams,id']);

        $exam = Exam::findOrFail($request->exam_id);
        if ($response = $this->verifyExamOwnership($exam, $instructor)) {
            return $response;
        }

        $trashedQuestions = $this->questionService->getTrashedQuestions($request->exam_id);

        return $this->successResponse(
            AdminQuestionResource::collection($trashedQuestions),
            'Trashed questions fetched successfully'
        );
    }

    public function restore(Request $request, int $id): JsonResponse
    {
        $instructor = $this->resolveInstructor($request);
        if (!$instructor) {
            return $this->instructorNotFoundResponse();
        }

        $question = Question::withTrashed()->with('exam.course')->findOrFail($id);
        if ($response = $this->verifyQuestionOwnership($question, $instructor)) {
            return $response;
        }

        $restored = $this->questionService->restoreQuestion($id);

        return $this->successResponse(
            new AdminQuestionResource($restored),
            'Question and its choices restored successfully'
        );
    }

    public function forceDelete(Request $request, int $id): JsonResponse
    {
        $instructor = $this->resolveInstructor($request);
        if (!$instructor) {
            return $this->instructorNotFoundResponse();
        }

        $question = Question::withTrashed()->with('exam.course')->findOrFail($id);
        if ($response = $this->verifyQuestionOwnership($question, $instructor)) {
            return $response;
        }

        $this->questionService->forceDeleteQuestion($id);

        return $this->successResponse(null, 'Question and its choices deleted permanently from system');
    }
}
