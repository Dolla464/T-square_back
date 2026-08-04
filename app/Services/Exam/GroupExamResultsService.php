<?php

namespace App\Services\Exam;

use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\LearningGroup;
use App\Models\Student;
use App\Services\User\ExamService;
use Illuminate\Support\Collection;

class GroupExamResultsService
{
    public function __construct(
        private ExamService $examService,
        private GroupExamActivationService $groupExamActivationService
    ) {}

    public function assertExamBelongsToGroup(Exam $exam, LearningGroup $group): bool
    {
        return (int) $exam->course_id === (int) $group->course_id;
    }

    public function assertStudentBelongsToGroup(Student $student, LearningGroup $group): bool
    {
        return $group->students()->where('students.id', $student->id)->exists();
    }

    public function getExamsForGroup(LearningGroup $group): array
    {
        return $this->groupExamActivationService->getExamsWithActivationStatus($group);
    }

    public function getExamResultsSummary(LearningGroup $group, Exam $exam): array
    {
        if (! $this->assertExamBelongsToGroup($exam, $group)) {
            throw new \InvalidArgumentException('Exam not found for this group.');
        }

        $group->load(['course:id,title']);
        $exam->loadMissing('course:id,title');

        $students = $group->students()->with('user:id,email')->get();
        $studentIds = $students->pluck('id');

        $attemptsByStudent = ExamAttempt::query()
            ->where('exam_id', $exam->id)
            ->whereIn('student_id', $studentIds)
            ->whereIn('status', ExamAttempt::REVIEWABLE_STATUSES)
            ->with(['questions:id,marks'])
            ->get(['id', 'student_id', 'score', 'status', 'exam_id'])
            ->groupBy('student_id');

        $studentsData = $students->map(function ($student) use ($attemptsByStudent, $exam) {
            $attempts = $attemptsByStudent->get($student->id, collect());
            $summary = $this->summarizeStudentAttempts($attempts, $exam);

            return [
                'student_id' => $student->id,
                'full_name' => $student->full_name ?? $student->user?->name ?? 'Unknown',
                'email' => $student->user?->email ?? null,
                'attempts_count' => $summary['attempts_count'],
                'highest_score' => $summary['highest_score'],
                'highest_attempt_max_marks' => $summary['highest_attempt_max_marks'],
                'highest_attempt_passing_mark' => $summary['highest_attempt_passing_mark'],
                'is_passed' => $summary['is_passed'],
                'has_attempts' => $summary['has_attempts'],
            ];
        });

        return [
            'exam_id' => $exam->id,
            'exam_title' => $exam->title,
            'group_name' => $group->group_name,
            'course_title' => $group->course->title ?? $exam->course->title ?? null,
            'total_marks' => $exam->total_marks,
            'passing_mark' => $exam->passing_mark,
            'exam_total_marks' => $exam->total_marks,
            'exam_passing_mark' => $exam->passing_mark,
            'questions_per_attempt' => $exam->questions_per_attempt,
            'students' => $studentsData->values()->all(),
        ];
    }

    public function getStudentExamAttempts(LearningGroup $group, Student $student, Exam $exam)
    {
        if (! $this->assertExamBelongsToGroup($exam, $group)) {
            throw new \InvalidArgumentException('Exam not found for this group.');
        }

        if (! $this->assertStudentBelongsToGroup($student, $group)) {
            throw new \InvalidArgumentException('Student is not enrolled in this group.');
        }

        return $this->examService->getStudentResults($student->id, $exam->id);
    }

    public function getStudentAttemptReview(LearningGroup $group, Student $student, ExamAttempt $attempt): ExamAttempt
    {
        if (! $this->assertStudentBelongsToGroup($student, $group)) {
            throw new \InvalidArgumentException('Student is not enrolled in this group.');
        }

        if ($attempt->student_id !== $student->id) {
            throw new \InvalidArgumentException('Attempt does not belong to this student.');
        }

        $attempt->loadMissing('exam');
        if (! $this->assertExamBelongsToGroup($attempt->exam, $group)) {
            throw new \InvalidArgumentException('Exam not found for this group.');
        }

        if (! $attempt->isReviewable()) {
            throw new \InvalidArgumentException('This attempt is not available for review.');
        }

        return $this->examService->getAttemptReview($attempt->id);
    }

    private function summarizeStudentAttempts(Collection $attempts, Exam $exam): array
    {
        $attemptsCount = $attempts->count();

        if ($attemptsCount === 0) {
            return [
                'attempts_count' => 0,
                'highest_score' => null,
                'highest_attempt_max_marks' => null,
                'highest_attempt_passing_mark' => null,
                'is_passed' => false,
                'has_attempts' => false,
            ];
        }

        $isPassed = $attempts->contains(fn (ExamAttempt $attempt) => $attempt->status === 'passed');

        $bestAttempt = $attempts
            ->sortByDesc(fn (ExamAttempt $attempt) => (float) $attempt->score)
            ->first();

        $bestAttempt->setRelation('exam', $exam);

        return [
            'attempts_count' => $attemptsCount,
            'highest_score' => (float) $bestAttempt->score,
            'highest_attempt_max_marks' => $this->examService->getAttemptMaxMarks($bestAttempt),
            'highest_attempt_passing_mark' => $this->examService->getAttemptPassingMark($bestAttempt),
            'is_passed' => $isPassed,
            'has_attempts' => true,
        ];
    }
}
