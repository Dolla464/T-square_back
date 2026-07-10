<?php

namespace App\Services\User;

use App\Events\StudentExamAttemptCompleted;
use App\Models\Answer;
use App\Models\Choice;
use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Question;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ExamService
{
    /**
     * Get all available exams for a student
     *
     * @param [type] $student
     * @return Collection
     */
    public function getAvailableExams($student)
    {
        return $student->availableExams()
            ->where('is_active', true)
            ->whereHas('activatedGroups', function ($q) use ($student) {
                $q->whereHas('enrollments', function ($eq) use ($student) {
                    $eq->where('student_id', $student->id)
                        ->whereNotNull('group_id')
                        ->whereColumn('enrollments.course_id', 'exams.course_id')
                        ->withCompletedOrder();
                });
            })
            ->with('course')
            ->withCount([
                'attempts' => function ($q) use ($student) {
                    $q->where('student_id', $student->id);
                },
                'questions',
            ])
            ->get();
    }

    public function startAttempt(int $studentId, int $examId)
    {
        // 1. Fetch exam first to validate enrollment and bank size before touching attempts
        $exam = Exam::findOrFail($examId);

        if (! $exam->is_active) {
            abort(403, 'This exam is not currently available.');
        }

        if (! $this->hasCompletedEnrollment($studentId, $exam->course_id)) {
            abort(403, 'Sorry, you are not enrolled in this course to take the exam.');
        }

        if (! $this->hasGroupExamAccess($studentId, $examId, $exam->course_id)) {
            abort(403, 'This exam has not been activated for your group yet.');
        }

        // 2. Guard: refuse to start/resume when the question bank is empty
        $bankCount = Question::where('exam_id', $examId)->count();
        if ($bankCount === 0) {
            abort(422, 'This exam has no questions yet. Please contact the administrator.');
        }

        // 3. Check for an existing ongoing attempt
        $existingAttempt = ExamAttempt::where('student_id', $studentId)
            ->where('exam_id', $examId)
            ->where('status', 'ongoing')
            ->first();

        if ($existingAttempt) {
            // If the ongoing attempt has no questions (created before the bank was populated),
            // repopulate it now so the student is not stuck on an empty exam.
            if ($existingAttempt->questions()->count() === 0) {
                $limit = $exam->questions_per_attempt ?? 10;
                $randomQuestionIds = Question::where('exam_id', $examId)
                    ->inRandomOrder()
                    ->take($limit)
                    ->pluck('id');
                $existingAttempt->questions()->attach($randomQuestionIds);
            }

            return $existingAttempt->load(['questions.choices', 'answers']);
        }

        // 4. Calculate the number of previous (closed) attempts to check the allowed limit
        $attemptsCount = ExamAttempt::where('student_id', $studentId)
            ->where('exam_id', $examId)
            ->count();

        if ($exam->max_attempts && $attemptsCount >= $exam->max_attempts) {
            abort(403, 'Sorry, you have exhausted the maximum number of attempts available for this exam!');
        }

        // 5. Create a new attempt record
        $attempt = ExamAttempt::create([
            'student_id' => $studentId,
            'exam_id' => $examId,
            'status' => 'ongoing',
            'started_at' => now(),
        ]);

        // 6. Randomly sample questions from the bank and freeze them in the pivot table
        $limit = $exam->questions_per_attempt ?? 10;
        $randomQuestionIds = Question::where('exam_id', $examId)
            ->inRandomOrder()
            ->take($limit)
            ->pluck('id');

        $attempt->questions()->attach($randomQuestionIds);

        return $attempt->load(['questions.choices', 'answers']);
    }

    /**
     *  Save one question answer - now only allows answers for the exam's questions
     */
    public function saveAnswer(int $attemptId, int $questionId, int $choiceId)
    {
        $attempt = ExamAttempt::with([
            'exam' => function ($q) use ($attemptId) {
                $q->withCount(['attempts' => function ($sq) use ($attemptId) {
                    $sq->where('student_id', DB::raw('(SELECT student_id FROM exam_attempts WHERE id = ' . $attemptId . ')'));
                }]);
            },
            'questions',
        ])->findOrFail($attemptId);

        // Protection: Ensure the exam is still in the ongoing state and has not been closed
        if ($attempt->status !== 'ongoing') {
            abort(403, 'This attempt is already closed and cannot be modified.');
        }

        // Additional protection: Ensure the student has not exceeded the attempts during the answer
        if ($attempt->exam->max_attempts && $attempt->exam->attempts_count > $attempt->exam->max_attempts) {
            abort(403, 'Sorry, you have exceeded the maximum number of attempts.');
        }

        // Validate against the attempt's own question subset, not the full exam bank
        $question = $attempt->questions->firstWhere('id', $questionId);
        if (! $question) {
            abort(403, 'This question does not belong to this attempt.');
        }

        $choice = Choice::findOrFail($choiceId);
        if ($choice->question_id != $question->id) {
            abort(403, 'Selected choice does not belong to this question.');
        }

        return Answer::updateOrCreate(
            ['attempt_id' => $attemptId, 'question_id' => $questionId],
            [
                'choice_id' => $choiceId,
                'is_correct' => $choice->is_correct,
                'marks_earned' => $choice->is_correct ? $question->marks : 0,
            ]
        );
    }

    /**
     * Complete the exam and calculate the final result
     */
    public function completeAttempt($attemptId, ?int $studentId = null)
    {
        $attempt = ExamAttempt::with('exam')->findOrFail($attemptId);

        if ($studentId && $attempt->student_id !== $studentId) {
            abort(403, 'You are not allowed to submit this attempt.');
        }

        if ($attempt->status !== 'ongoing') {
            return $this->buildAttemptResult($attempt, $attempt->score);
        }

        $result = DB::transaction(function () use ($attempt) {
            $totalScore = $attempt->answers()->sum('marks_earned');
            $isPassed = $totalScore >= $attempt->exam->passing_mark;

            $attempt->update([
                'score' => $totalScore,
                'status' => $isPassed ? 'passed' : 'failed',
                'finished_at' => now(),
            ]);

            if ($isPassed && $attempt->exam->is_final) {
                $enrollment = Enrollment::where('student_id', '=', $attempt->student_id, 'and')
                    ->where('course_id', '=', $attempt->exam->course_id, 'and')
                    ->first();

                if ($enrollment && ! $enrollment->is_completed) {
                    $enrollment->markAsCompleted();
                }
            }

            return $this->buildAttemptResult($attempt, $totalScore, $isPassed);
        });

        $attempt->refresh()->loadMissing(['student.user', 'exam.course']);
        StudentExamAttemptCompleted::dispatch($attempt);

        return $result;
    }

    private function buildAttemptResult(ExamAttempt $attempt, $score, ?bool $isPassed = null): array
    {
        $isPassed ??= $score >= $attempt->exam->passing_mark;

        return [
            'score' => $score,
            'total_marks' => $attempt->exam->total_marks,
            'passing_mark' => $attempt->exam->passing_mark,
            'is_passed' => $isPassed,
            'status' => $isPassed ? 'passed' : 'failed',
            'is_final' => (bool) $attempt->exam->is_final,
            'course_id' => $attempt->exam->course_id,
            'requires_review' => $isPassed && $attempt->exam->is_final,
        ];
    }

    private function hasCompletedEnrollment(int $studentId, int $courseId): bool
    {
        return Enrollment::query()
            ->where('student_id', $studentId)
            ->where('course_id', $courseId)
            ->withCompletedOrder()
            ->exists();
    }

    private function hasGroupExamAccess(int $studentId, int $examId, int $courseId): bool
    {
        return Enrollment::query()
            ->where('student_id', $studentId)
            ->where('course_id', $courseId)
            ->whereNotNull('group_id')
            ->withCompletedOrder()
            ->whereExists(function ($q) use ($examId) {
                $q->selectRaw('1')
                    ->from('group_exam_activations')
                    ->whereColumn('group_exam_activations.learning_group_id', 'enrollments.group_id')
                    ->where('group_exam_activations.exam_id', $examId);
            })
            ->exists();
    }

    public function getStudentResults($studentId, ?int $examId = null)
    {
        $query = ExamAttempt::where('student_id', '=', $studentId, 'and')
            // Added passed and failed based on the last modification in the Service
            ->whereIn('status', ['passed', 'failed', 'completed', 'timed_out'], 'and', false)
            ->with(['exam' => function ($query) {
                $query->select('id', 'course_id', 'title', 'total_marks', 'passing_mark', 'is_final');
            }, 'exam.course:id,title'])
            ->orderBy('finished_at', 'desc');
        // If the exam ID is passed, filter the attempts for the exams belonging to this exam only
        if ($examId) {
            $query->whereHas('exam', function ($q) use ($examId) {
                $q->where('id', $examId);
            });
        }
        return $query->get()->map(function ($attempt) {
            $attempt->can_download_certificate = ($attempt->status === 'passed' && $attempt->exam->is_final);
            return $attempt;
        });
    }
}
