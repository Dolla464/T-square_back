<?php

namespace App\Http\Controllers\Api\Instructor;

use App\Http\Controllers\Concerns\EnsuresInstructorOwnsResource;
use App\Http\Controllers\Controller;
use App\Http\Resources\User\Exam\ExamResultResource;
use App\Models\Exam;
use App\Models\LearningGroup;
use App\Models\Student;
use App\Services\Exam\GroupExamActivationService;
use App\Services\Exam\GroupExamResultsService;
use App\Services\Instructor\InstructorLearningGroupService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @tags Instructor: Learning Groups (Exam Results)
 */
class InstructorLearningGroupController extends Controller
{
    use EnsuresInstructorOwnsResource;

    public function __construct(
        private InstructorLearningGroupService $learningGroupService,
        private GroupExamResultsService $groupExamResultsService,
        private GroupExamActivationService $groupExamActivationService
    ) {}

    public function selection(Request $request): JsonResponse
    {
        $instructor = $this->resolveInstructor($request);
        if (!$instructor) {
            return $this->instructorNotFoundResponse();
        }

        $groups = $this->learningGroupService->getSelectionForInstructor($instructor->id);

        return $this->successResponse($groups, 'Learning groups retrieved for selection successfully');
    }

    public function getGroupExams(Request $request, LearningGroup $learningGroup): JsonResponse
    {
        $instructor = $this->resolveInstructor($request);
        if (!$instructor) {
            return $this->instructorNotFoundResponse();
        }

        if ($response = $this->verifyGroupOwnership($learningGroup, $instructor)) {
            return $response;
        }

        return $this->successResponse(
            $this->groupExamResultsService->getExamsForGroup($learningGroup),
            'Group exams retrieved successfully'
        );
    }

    public function toggleExamActivation(Request $request, LearningGroup $learningGroup, Exam $exam): JsonResponse
    {
        $instructor = $this->resolveInstructor($request);
        if (!$instructor) {
            return $this->instructorNotFoundResponse();
        }

        if ($response = $this->verifyGroupOwnership($learningGroup, $instructor)) {
            return $response;
        }

        if ($response = $this->verifyExamBelongsToGroup($exam, $learningGroup)) {
            return $response;
        }

        $validated = $request->validate([
            'is_activated' => 'required|boolean',
        ]);

        try {
            $payload = $this->groupExamActivationService->toggleActivation(
                $learningGroup,
                $exam,
                $instructor->id,
                (bool) $validated['is_activated']
            );
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 404);
        }

        return $this->successResponse(
            $payload,
            $validated['is_activated']
                ? 'Exam activated for group successfully'
                : 'Exam deactivated for group successfully'
        );
    }

    public function getExamResults(Request $request, LearningGroup $learningGroup, Exam $exam): JsonResponse
    {
        $instructor = $this->resolveInstructor($request);
        if (!$instructor) {
            return $this->instructorNotFoundResponse();
        }

        if ($response = $this->verifyGroupOwnership($learningGroup, $instructor)) {
            return $response;
        }

        try {
            $payload = $this->groupExamResultsService->getExamResultsSummary($learningGroup, $exam);
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 404);
        }

        return $this->successResponse($payload, 'Exam results retrieved successfully');
    }

    public function exportExamResults(Request $request, LearningGroup $learningGroup, Exam $exam): JsonResponse
    {
        $instructor = $this->resolveInstructor($request);
        if (!$instructor) {
            return $this->instructorNotFoundResponse();
        }

        if ($response = $this->verifyGroupOwnership($learningGroup, $instructor)) {
            return $response;
        }

        $validated = $request->validate([
            'format' => 'nullable|string|in:pdf,excel',
        ]);

        try {
            $payload = $this->groupExamResultsService->getExamResultsSummary($learningGroup, $exam);
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 404);
        }

        $format = $validated['format'] ?? 'pdf';

        if ($format === 'excel') {
            return $this->exportExamResultsExcel($payload);
        }

        return $this->exportExamResultsPdf($payload);
    }

    public function getStudentExamResults(Request $request, LearningGroup $learningGroup, Student $student): JsonResponse
    {
        $instructor = $this->resolveInstructor($request);
        if (!$instructor) {
            return $this->instructorNotFoundResponse();
        }

        if ($response = $this->verifyGroupOwnership($learningGroup, $instructor)) {
            return $response;
        }

        $validated = $request->validate([
            'exam_id' => 'required|integer|exists:exams,id',
        ]);

        $exam = Exam::findOrFail($validated['exam_id']);

        if ($response = $this->verifyExamBelongsToGroup($exam, $learningGroup)) {
            return $response;
        }

        try {
            $attempts = $this->groupExamResultsService->getStudentExamAttempts($learningGroup, $student, $exam);
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 404);
        }

        return $this->successResponse(
            ExamResultResource::collection($attempts),
            'Student exam results retrieved successfully'
        );
    }

    private function exportExamResultsPdf(array $payload): JsonResponse
    {
        $pdf = Pdf::loadView('exports.exam-results-pdf', [
            'payload'     => $payload,
            'generatedAt' => now()->format('Y-m-d H:i'),
        ])->setPaper('a4', 'landscape');

        $filename = 'exam-results-' . $payload['exam_id'] . '-' . now()->format('Ymd') . '.pdf';

        return $this->successResponse([
            'content'  => base64_encode($pdf->output()),
            'filename' => $filename,
            'mime'     => 'application/pdf',
        ], 'PDF export ready');
    }

    private function exportExamResultsExcel(array $payload): JsonResponse
    {
        $filename = 'exam-results-' . $payload['exam_id'] . '-' . now()->format('Ymd') . '.csv';

        ob_start();
        $handle = fopen('php://output', 'w');
        fputs($handle, "\xEF\xBB\xBF");

        fputcsv($handle, ['#', 'Student Name', 'Email', 'Attempts', 'Highest Score', 'Status']);

        $totalMarks = $payload['total_marks'] ?? null;

        foreach ($payload['students'] as $idx => $student) {
            $hasAttempts = $student['has_attempts'] ?? false;
            $highestScore = $student['highest_score'] ?? null;

            if ($hasAttempts && $highestScore !== null) {
                $scoreDisplay = $totalMarks !== null
                    ? $highestScore . ' / ' . $totalMarks
                    : (string) $highestScore;
                $status = ($student['is_passed'] ?? false) ? 'Passed' : 'Failed';
            } else {
                $scoreDisplay = '—';
                $status = 'No attempts';
            }

            fputcsv($handle, [
                $idx + 1,
                $student['full_name'] ?? '',
                $student['email'] ?? '',
                $student['attempts_count'] ?? 0,
                $scoreDisplay,
                $status,
            ]);
        }

        fclose($handle);
        $content = ob_get_clean();

        return $this->successResponse([
            'content'  => base64_encode($content),
            'filename' => $filename,
            'mime'     => 'text/csv',
        ], 'CSV export ready');
    }
}
