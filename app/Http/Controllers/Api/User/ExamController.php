<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Student\SaveAnswerRequest;
use App\Http\Requests\Api\Student\StartExamRequest;
use App\Http\Requests\Api\Student\StoreIntegrityEventsRequest;
use App\Http\Requests\Api\Student\SubmitExamRequest;
use App\Models\ExamAttempt;
use App\Services\Exam\ExamIntegrityService;
use App\Http\Resources\User\Exam\ExamAttemptResource;
use App\Http\Resources\User\Exam\ExamAttemptReviewResource;
use App\Http\Resources\User\Exam\ExamListResource;
use App\Http\Resources\User\Exam\ExamResultResource;
use App\Services\User\ExamService;
use App\Support\ExamTimerDiagnostic;
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

    public function __construct(
        ExamService $examService,
        private ExamIntegrityService $examIntegrityService,
    ) {
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

        // Load the attempt's own question subset with choices and any saved answers.
        $attempt->load(['questions.choices', 'answers', 'exam']);

        $resource = new ExamAttemptResource($attempt);
        ExamTimerDiagnostic::logStart($attempt, $resource->resolve($request));

        return $resource;
    }

    public function answer(SaveAnswerRequest $request)
    {
        $student = $request->user()->student;

        $this->examService->saveAnswer(
            $request->attempt_id,
            $request->question_id,
            $request->input('choice_id'),
            $student?->id,
            $request->input('answer_text'),
        );

        return response()->json(['status' => 'saved']);
    }

    public function submit(SubmitExamRequest $request, int $id)
    {
        $student = $request->user()->student;

        if (! $student) {
            return $this->errorResponse('Student profile not found', 404);
        }

        $result = $this->examService->completeAttempt($id, $student->id);

        $totalMarks = $result['total_marks'] > 0 ? $result['total_marks'] : 1;
        $score = $result['score'];
        $status = $result['status'];
        $isAwaitingGrading = $status === 'awaiting_grading';
        $percentage = $isAwaitingGrading
            ? null
            : round(($score / $totalMarks) * 100, 2).'%';

        $feedback = match (true) {
            $isAwaitingGrading => 'Your exam has been submitted and is awaiting instructor grading.',
            $result['is_passed'] === true => 'Congratulations. You passed this exam.',
            default => 'Sorry. You failed this exam. Try again.',
        };

        return response()->json([
            'message' => $isAwaitingGrading
                ? 'Exam submitted successfully. Awaiting instructor grading.'
                : 'Exam completed successfully',
            'results' => [
                'score' => $score,
                'total_marks' => $totalMarks,
                'is_passed' => $result['is_passed'],
                'status' => $status,
                'percentage' => $percentage,
                'is_final' => $result['is_final'],
                'course_id' => $result['course_id'],
                'requires_review' => $result['requires_review'],
                'feedback' => $feedback,
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

    public function timeStatus(Request $request, int $attemptId)
    {
        $student = $request->user()->student;

        if (! $student) {
            return $this->errorResponse('Student profile not found', 404);
        }

        $status = $this->examService->getAttemptTimeStatus($attemptId, $student->id);

        return $this->successResponse(
            data: $status,
            message: 'Exam time status retrieved successfully.',
        );
    }

    public function reviewAttempt(Request $request, int $attemptId)
    {
        $student = $request->user()->student;

        if (! $student) {
            return $this->errorResponse('Student not found', 404);
        }

        $attempt = $this->examService->getAttemptReview($attemptId);

        if ($attempt->student_id !== $student->id) {
            return $this->errorResponse('You are not allowed to view this attempt.', 403);
        }

        return $this->successResponse(
            new ExamAttemptReviewResource($attempt),
            'Attempt review retrieved successfully.',
        );
    }

    public function recordIntegrityEvents(StoreIntegrityEventsRequest $request, int $attemptId)
    {
        $student = $request->user()->student;

        if (! $student) {
            return $this->errorResponse('Student profile not found', 404);
        }

        $attempt = ExamAttempt::query()->findOrFail($attemptId);

        $recorded = $this->examIntegrityService->recordEvents(
            $attempt,
            $student,
            $request->validated('events'),
        );

        return $this->successResponse(
            ['recorded' => $recorded],
            'Integrity events recorded successfully.',
            201,
        );
    }
}
