<?php

namespace App\Services\Exam;

use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\LearningGroup;
use App\Models\Student;
use App\Services\User\ExamService;

class GroupExamResultsService
{
    private const FINISHED_STATUSES = ['passed', 'failed', 'completed', 'timed_out'];

    public function __construct(
        private ExamService $examService
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
        $group->load('course:id,title');

        return Exam::query()
            ->where('course_id', $group->course_id)
            ->where('is_active', true)
            ->orderBy('title')
            ->get(['id', 'title', 'total_marks', 'passing_mark', 'max_attempts'])
            ->map(fn (Exam $exam) => [
                'id'           => $exam->id,
                'title'        => $exam->title,
                'total_marks'  => $exam->total_marks,
                'passing_mark' => $exam->passing_mark,
                'max_attempts' => $exam->max_attempts,
            ])
            ->values()
            ->all();
    }

    public function getExamResultsSummary(LearningGroup $group, Exam $exam): array
    {
        if (!$this->assertExamBelongsToGroup($exam, $group)) {
            throw new \InvalidArgumentException('Exam not found for this group.');
        }

        $group->load(['course:id,title']);
        $exam->loadMissing('course:id,title');

        $students = $group->students()->with('user:id,email')->get();
        $studentIds = $students->pluck('id');

        $attemptsByStudent = ExamAttempt::query()
            ->where('exam_id', $exam->id)
            ->whereIn('student_id', $studentIds)
            ->whereIn('status', self::FINISHED_STATUSES)
            ->get(['id', 'student_id', 'score', 'status'])
            ->groupBy('student_id');

        $passingMark = $exam->passing_mark;

        $studentsData = $students->map(function ($student) use ($attemptsByStudent, $passingMark) {
            $attempts = $attemptsByStudent->get($student->id, collect());
            $attemptsCount = $attempts->count();
            $highestScore = $attemptsCount > 0
                ? $attempts->max(fn ($a) => (float) $a->score)
                : null;

            return [
                'student_id'     => $student->id,
                'full_name'      => $student->full_name ?? $student->user?->name ?? 'Unknown',
                'email'          => $student->user?->email ?? null,
                'attempts_count' => $attemptsCount,
                'highest_score'  => $highestScore,
                'is_passed'      => $highestScore !== null && $highestScore >= $passingMark,
                'has_attempts'   => $attemptsCount > 0,
            ];
        });

        return [
            'exam_id'      => $exam->id,
            'exam_title'   => $exam->title,
            'group_name'   => $group->group_name,
            'course_title' => $group->course->title ?? $exam->course->title ?? null,
            'total_marks'  => $exam->total_marks,
            'passing_mark' => $exam->passing_mark,
            'students'     => $studentsData->values()->all(),
        ];
    }

    public function getStudentExamAttempts(LearningGroup $group, Student $student, Exam $exam)
    {
        if (!$this->assertExamBelongsToGroup($exam, $group)) {
            throw new \InvalidArgumentException('Exam not found for this group.');
        }

        if (!$this->assertStudentBelongsToGroup($student, $group)) {
            throw new \InvalidArgumentException('Student is not enrolled in this group.');
        }

        return $this->examService->getStudentResults($student->id, $exam->id);
    }
}
