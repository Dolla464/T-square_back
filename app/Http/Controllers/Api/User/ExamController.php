<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Student\SaveAnswerRequest;
use App\Http\Requests\Api\Student\StartExamRequest;
use App\Http\Resources\User\Exam\ExamAttemptResource;
use App\Http\Resources\User\Exam\ExamListResource;
use App\Http\Resources\User\Exam\ExamResultResource;
use App\Services\User\ExamService;
use Illuminate\Http\Request;
use App\Traits\ApiResponseTrait;

/**
 * @tags Exams
 */
class ExamController extends Controller
{
    use ApiResponseTrait;

    /** @var ExamService */
    protected $examService;

    public function __construct(ExamService $examService)
    {
        $this->examService = $examService;
    }

    public function index(Request $request)
    {
        $student = $request->user()->student;

        if (! $student) {
            return $this->errorResponse('Student profile not found', 404);
        }

        $exams = $this->examService->getAvailableExams($student);

        return ExamListResource::collection($exams);
    }

    public function start(StartExamRequest $request)
    {
        // Connect to the student through the User relationship
        $student = $request->user()->student;

        if (! $student) {
            return $this->errorResponse('Student profile not found', 404);
        }

        $attempt = $this->examService->startAttempt($student->id, $request->exam_id);

        // Load the relationships so the Resource works properly (Eager Loading)
        $attempt->load('exam.questions.choices');

        return new ExamAttemptResource($attempt);
    }

    public function answer(SaveAnswerRequest $request)
    {
        $this->examService->saveAnswer(
            $request->attempt_id,
            $request->question_id,
            $request->choice_id
        );

        return response()->json(['status' => 'saved']);
    }

    public function submit(Request $request, int $id)
    {
        $student = $request->user()->student;

        if (! $student) {
            return $this->errorResponse('Student profile not found', 404);
        }

        $result = $this->examService->completeAttempt($id, $student->id);

        // Prevent division by zero
        $totalMarks = $result['total_marks'] > 0 ? $result['total_marks'] : 1;
        $score = $result['score'];
        $percentage = round(($score / $totalMarks) * 100, 2);

        return response()->json([
            'message' => 'Exam completed successfully',
            'results' => [
                'score' => $score,
                'total_marks' => $totalMarks,
                'is_passed' => $result['is_passed'],
                'status' => $result['status'],
                'percentage' => $percentage . '%',
                'is_final' => $result['is_final'],
                'course_id' => $result['course_id'],
                'requires_review' => $result['requires_review'],
                'feedback' => $result['is_passed']
                    ? 'Congratulations. You passed this exam.'
                    : 'Sorry. You failed this exam. Try again.',
            ],
        ]);
    }

    public function myResults(Request $request)
    {
        $student = $request->user()->student;

        if (! $student) {
            return $this->errorResponse('Student not found', 404);
        }

        // Read the course ID if it is passed as a Query Parameter
        $examId = $request->query('exam_id') ? (int)$request->query('exam_id') : null;

        $results = $this->examService->getStudentResults($student->id, $examId);

        return $this->successResponse(
            data: ExamResultResource::collection($results),
            message: 'Exam results retrieved successfully.',
        );
    }
}
