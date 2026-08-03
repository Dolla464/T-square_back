<?php

namespace App\Services\User;

use App\Events\StudentExamAttemptCompleted;
use App\Models\Answer;
use App\Models\Choice;
use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Question;
use App\Services\Exam\ExamAttemptAuthorizationService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ExamService
{
    public function __construct(
        private ExamAttemptAuthorizationService $attemptAuthorizationService,
    ) {}

    /**
     * Get all available exams for a student
     */
    public function getAvailableExams($student): Collection
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

    public function startAttempt(int $studentId, int $examId): ExamAttempt
    {
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

        $bankCount = Question::where('exam_id', $examId)->count();
        if ($bankCount === 0) {
            abort(422, 'This exam has no questions yet. Please contact the administrator.');
        }

        $existingAttempt = ExamAttempt::where('student_id', $studentId)
            ->where('exam_id', $examId)
            ->where('status', 'ongoing')
            ->first();

        if ($existingAttempt) {
            if ($existingAttempt->questions()->count() === 0) {
                $this->attachSampledQuestions(
                    $existingAttempt,
                    $this->sampleQuestionIdsForAttempt($exam, $examId, $bankCount)
                );
            }

            if ($this->attemptAuthorizationService->isTimedOut($existingAttempt)) {
                $this->completeAttempt($existingAttempt->id, $studentId);

                return $existingAttempt->refresh()->load(['questions.choices', 'answers', 'exam']);
            }

            return $existingAttempt->load(['questions.choices', 'answers', 'exam']);
        }

        $attemptsCount = ExamAttempt::where('student_id', $studentId)
            ->where('exam_id', $examId)
            ->count();

        if ($exam->max_attempts && $attemptsCount >= $exam->max_attempts) {
            abort(403, 'Sorry, you have exhausted the maximum number of attempts available for this exam!');
        }

        $attempt = ExamAttempt::create([
            'student_id' => $studentId,
            'exam_id' => $examId,
            'started_at' => now(),
        ]);
        $attempt->forceFill(['status' => 'ongoing'])->save();

        $this->attachSampledQuestions(
            $attempt,
            $this->sampleQuestionIdsForAttempt($exam, $examId, $bankCount)
        );

        return $attempt->load(['questions.choices', 'answers', 'exam']);
    }

    /**
     * Build the frontend results payload for a completed attempt.
     */
    public function getAttemptResultsPayload(ExamAttempt $attempt): array
    {
        $attempt->loadMissing(['exam', 'questions']);

        $isPassed = $attempt->status === 'passed';
        $result = $this->buildAttemptResult(
            $attempt,
            $attempt->score,
            $isPassed,
            $attempt->status,
        );
        $totalMarks = $result['total_marks'] > 0 ? $result['total_marks'] : 1;
        $percentage = round(((float) $result['score'] / $totalMarks) * 100, 2);

        return array_merge($result, [
            'percentage' => $percentage.'%',
        ]);
    }

    public function saveAnswer(int $attemptId, int $questionId, int $choiceId): Answer
    {
        $attempt = ExamAttempt::with([
            'exam',
            'questions',
        ])->findOrFail($attemptId);

        if ($attempt->status !== 'ongoing') {
            abort(403, 'This attempt is already closed and cannot be modified.');
        }

        $question = $attempt->questions->firstWhere('id', $questionId);
        if (! $question) {
            abort(403, 'This question does not belong to this attempt.');
        }

        $choice = Choice::findOrFail($choiceId);
        if ($choice->question_id != $question->id) {
            abort(403, 'Selected choice does not belong to this question.');
        }

        $answer = Answer::updateOrCreate(
            ['attempt_id' => $attemptId, 'question_id' => $questionId],
            ['choice_id' => $choiceId]
        );
        $answer->forceFill([
            'is_correct' => $choice->is_correct,
            'marks_earned' => $choice->is_correct ? $question->marks : 0,
        ])->save();

        return $answer;
    }

    public function completeAttempt($attemptId, ?int $studentId = null): array
    {
        if ($studentId) {
            $authResult = $this->attemptAuthorizationService->checkSubmittable((int) $attemptId, $studentId);

            if (! $authResult->isAllowed()) {
                abort($authResult->getStatusCode(), $authResult->getMessage());
            }
        }

        $attempt = ExamAttempt::with(['exam', 'questions'])->findOrFail($attemptId);

        if ($attempt->status !== 'ongoing') {
            return $this->buildAttemptResult($attempt, $attempt->score);
        }

        $result = DB::transaction(function () use ($attempt) {
            $totalScore = $attempt->answers()->sum('marks_earned');
            $attemptPassingMark = $this->getAttemptPassingMark($attempt);
            $isPassed = $totalScore >= $attemptPassingMark;
            $timedOut = $this->attemptAuthorizationService->isTimedOut($attempt);

            $status = $isPassed ? 'passed' : ($timedOut ? 'timed_out' : 'failed');

            $attempt->forceFill([
                'score' => $totalScore,
                'status' => $status,
                'finished_at' => now(),
            ])->save();

            if ($isPassed && $attempt->exam->is_final) {
                $enrollment = Enrollment::where('student_id', '=', $attempt->student_id, 'and')
                    ->where('course_id', '=', $attempt->exam->course_id, 'and')
                    ->first();

                if ($enrollment && ! $enrollment->is_completed) {
                    $enrollment->markAsCompleted();
                }
            }

            return $this->buildAttemptResult($attempt, $totalScore, $isPassed, $status);
        });

        $attempt->refresh()->loadMissing(['student.user', 'exam.course']);
        StudentExamAttemptCompleted::dispatch($attempt);

        return $result;
    }

    public function getAttemptMaxMarks(ExamAttempt $attempt): float
    {
        if ($attempt->relationLoaded('questions')) {
            return (float) $attempt->questions->unique('id')->sum('marks');
        }

        if ($attempt->relationLoaded('questionsWithTrashed')) {
            return (float) $attempt->questionsWithTrashed->unique('id')->sum('marks');
        }

        $questionIds = $attempt->questions()->pluck('questions.id')->unique();

        return (float) Question::whereIn('id', $questionIds)->sum('marks');
    }

    public function getAttemptPassingMark(ExamAttempt $attempt): float
    {
        $exam = $attempt->exam;
        $attemptMax = $this->getAttemptMaxMarks($attempt);

        if (! $exam || $exam->total_marks <= 0) {
            return 0.0;
        }

        return round(($exam->passing_mark / $exam->total_marks) * $attemptMax, 2);
    }

    public function getAttemptReview(int $attemptId): ExamAttempt
    {
        return ExamAttempt::reviewable()
            ->with([
                'exam:id,title,total_marks,passing_mark,course_id',
                'questionsWithTrashed.choices',
                'answers',
            ])
            ->findOrFail($attemptId);
    }

    public function getStudentResults($studentId, ?int $examId = null)
    {
        $query = ExamAttempt::where('student_id', '=', $studentId, 'and')
            ->whereIn('status', ExamAttempt::REVIEWABLE_STATUSES, 'and', false)
            ->with([
                'exam' => function ($query) {
                    $query->select('id', 'course_id', 'title', 'total_marks', 'passing_mark', 'is_final');
                },
                'exam.course:id,title',
                'questions',
            ])
            ->orderBy('finished_at', 'desc');

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

    private function buildAttemptResult(
        ExamAttempt $attempt,
        $score,
        ?bool $isPassed = null,
        ?string $status = null,
    ): array {
        $attemptMaxMarks = $this->getAttemptMaxMarks($attempt);
        $attemptPassingMark = $this->getAttemptPassingMark($attempt);
        $isPassed ??= $score >= $attemptPassingMark;
        $status ??= $isPassed ? 'passed' : 'failed';

        return [
            'score' => $score,
            'total_marks' => $attemptMaxMarks,
            'attempt_max_marks' => $attemptMaxMarks,
            'passing_mark' => $attemptPassingMark,
            'attempt_passing_mark' => $attemptPassingMark,
            'exam_total_marks' => $attempt->exam->total_marks,
            'exam_passing_mark' => $attempt->exam->passing_mark,
            'is_passed' => $isPassed,
            'status' => $status,
            'is_final' => (bool) $attempt->exam->is_final,
            'course_id' => $attempt->exam->course_id,
            'requires_review' => $isPassed && $attempt->exam->is_final,
        ];
    }

    private function resolveSampleLimit(Exam $exam, int $bankCount): int
    {
        $requested = max(1, (int) ($exam->questions_per_attempt ?: 10));

        return min($requested, $bankCount);
    }

    private function sampleQuestionIdsForAttempt(Exam $exam, int $examId, int $bankCount): array
    {
        $limit = $this->resolveSampleLimit($exam, $bankCount);

        $query = Question::where('exam_id', $examId);

        if ($exam->shuffle_questions) {
            $query->inRandomOrder();
        } else {
            $query->orderBy('id');
        }

        return $query->take($limit)->pluck('id')->all();
    }

    private function attachSampledQuestions(ExamAttempt $attempt, array $questionIds): void
    {
        $sync = [];

        foreach ($questionIds as $index => $questionId) {
            $sync[$questionId] = ['sort_order' => $index + 1];
        }

        $attempt->questions()->sync($sync);
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
}
