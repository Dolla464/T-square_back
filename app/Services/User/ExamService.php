<?php

namespace App\Services\User;

use App\Events\StudentExamAttemptAwaitingGrading;
use App\Events\StudentExamAttemptCompleted;
use App\Models\Answer;
use App\Models\Choice;
use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Question;
use App\Services\Exam\ExamAttemptAuthorizationService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
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

        if (! $this->hasCompletedEnrollment($studentId, $exam->course_id)) {
            abort(403, 'Sorry, you are not enrolled in this course to take the exam.');
        }

        $bankCount = Question::where('exam_id', $examId)->count();

        return retry(3, function () use ($studentId, $examId, $exam, $bankCount) {
            return DB::transaction(function () use ($studentId, $examId, $exam, $bankCount) {
                return $this->createOrResumeAttemptWithinTransaction(
                    $studentId,
                    $examId,
                    $exam,
                    $bankCount,
                );
            });
        }, 100, fn (\Throwable $exception) => $this->isDeadlockException($exception));
    }

    private function createOrResumeAttemptWithinTransaction(
        int $studentId,
        int $examId,
        Exam $exam,
        int $bankCount,
    ): ExamAttempt {
        $existingAttempt = ExamAttempt::query()
            ->where('student_id', $studentId)
            ->where('exam_id', $examId)
            ->where('status', ExamAttempt::STATUS_ONGOING)
            ->lockForUpdate()
            ->first();

        if ($existingAttempt) {
            return $this->resumeExistingAttempt($existingAttempt, $studentId, $exam, $bankCount);
        }

        return $this->createNewAttempt($studentId, $examId, $exam, $bankCount);
    }

    private function resumeExistingAttempt(
        ExamAttempt $existingAttempt,
        int $studentId,
        Exam $exam,
        int $bankCount,
    ): ExamAttempt {
        $existingAttempt->loadMissing('exam');

        if ($existingAttempt->questions()->count() === 0) {
            $this->attachSampledQuestions(
                $existingAttempt,
                $this->sampleQuestionIdsForAttempt($exam, $exam->id, $bankCount)
            );
            $this->assertAttemptHasQuestions($existingAttempt);
        }

        if ($this->attemptAuthorizationService->isTimedOut($existingAttempt)) {
            $this->completeAttempt($existingAttempt->id, $studentId);

            if ($this->studentCanStartNewAttempt($studentId, $exam, $bankCount)) {
                return $this->createNewAttempt($studentId, $exam->id, $exam, $bankCount);
            }

            return $existingAttempt->refresh()->load(['questions.choices', 'answers', 'exam']);
        }

        $access = $this->attemptAuthorizationService->validateSubmittableAttempt($existingAttempt, $studentId);

        if (! $access->isAllowed()) {
            if ($this->attemptAuthorizationService->isExamContextRevoked($access)) {
                $this->closeOngoingAttempt($existingAttempt->id, $studentId);

                return $existingAttempt->refresh()->load(['questions.choices', 'answers', 'exam']);
            }

            abort($access->getStatusCode(), $access->getMessage());
        }

        return $existingAttempt->load(['questions.choices', 'answers', 'exam']);
    }

    private function createNewAttempt(
        int $studentId,
        int $examId,
        Exam $exam,
        int $bankCount,
    ): ExamAttempt {
        if (! $exam->is_active) {
            abort(403, 'This exam is not currently available.');
        }

        if (! $this->hasGroupExamAccess($studentId, $examId, $exam->course_id)) {
            abort(403, 'This exam has not been activated for your group yet.');
        }

        if ($bankCount === 0) {
            abort(422, 'This exam has no questions yet. Please contact the administrator.');
        }

        $attemptsCount = ExamAttempt::query()
            ->where('student_id', $studentId)
            ->where('exam_id', $examId)
            ->count();

        if ($exam->max_attempts && $attemptsCount >= $exam->max_attempts) {
            abort(403, 'Sorry, you have exhausted the maximum number of attempts available for this exam!');
        }

        try {
            $attempt = ExamAttempt::create([
                'student_id' => $studentId,
                'exam_id' => $examId,
                'duration_minutes' => $exam->duration,
                'started_at' => now(),
                'status' => ExamAttempt::STATUS_ONGOING,
            ]);
        } catch (UniqueConstraintViolationException|QueryException $exception) {
            if (! $this->isDuplicateOngoingSlotException($exception)) {
                throw $exception;
            }

            $existingAttempt = ExamAttempt::query()
                ->where('student_id', $studentId)
                ->where('exam_id', $examId)
                ->where('status', ExamAttempt::STATUS_ONGOING)
                ->lockForUpdate()
                ->firstOrFail();

            return $this->resumeExistingAttempt($existingAttempt, $studentId, $exam, $bankCount);
        }

        $this->attachSampledQuestions(
            $attempt,
            $this->sampleQuestionIdsForAttempt($exam, $examId, $bankCount)
        );
        $this->assertAttemptHasQuestions($attempt);

        return $attempt->load(['questions.choices', 'answers', 'exam']);
    }

    private function assertAttemptHasQuestions(ExamAttempt $attempt): void
    {
        if ($attempt->questions()->count() === 0) {
            abort(422, 'This exam has no questions available for this attempt. Please contact the administrator.');
        }
    }

    private function studentCanStartNewAttempt(int $studentId, Exam $exam, int $bankCount): bool
    {
        if (! $exam->is_active) {
            return false;
        }

        if (! $this->hasGroupExamAccess($studentId, $exam->id, $exam->course_id)) {
            return false;
        }

        if ($bankCount === 0) {
            return false;
        }

        $attemptsCount = ExamAttempt::query()
            ->where('student_id', $studentId)
            ->where('exam_id', $exam->id)
            ->count();

        if ($exam->max_attempts && $attemptsCount >= $exam->max_attempts) {
            return false;
        }

        return true;
    }

    /**
     * Build the frontend results payload for a completed attempt.
     */
    public function getAttemptResultsPayload(ExamAttempt $attempt): array
    {
        $attempt->loadMissing(['exam', 'questions']);

        $result = $this->buildAttemptResult(
            $attempt,
            $attempt->score,
            status: $attempt->status,
        );

        if ($attempt->status === ExamAttempt::STATUS_AWAITING_GRADING) {
            return array_merge($result, [
                'percentage' => null,
            ]);
        }

        $totalMarks = $result['total_marks'] > 0 ? $result['total_marks'] : 1;
        $percentage = round(((float) $result['score'] / $totalMarks) * 100, 2);

        return array_merge($result, [
            'percentage' => $percentage.'%',
        ]);
    }

    public function saveAnswer(
        int $attemptId,
        int $questionId,
        ?int $choiceId = null,
        ?int $studentId = null,
        ?string $answerText = null,
    ): Answer {
        $attempt = ExamAttempt::with([
            'exam',
            'questions',
        ])->findOrFail($attemptId);

        if ($studentId !== null) {
            $access = $this->attemptAuthorizationService->validateMutableAttempt($attempt, $studentId);

            if (! $access->isAllowed()) {
                if ($this->attemptAuthorizationService->isExamContextRevoked($access)) {
                    $this->closeOngoingAttempt($attemptId, $studentId);
                }

                abort($access->getStatusCode(), $access->getMessage());
            }
        } elseif ($attempt->status !== ExamAttempt::STATUS_ONGOING) {
            abort(403, 'This attempt is already closed and cannot be modified.');
        }

        if ($this->attemptAuthorizationService->isTimedOut($attempt)) {
            $this->completeAttempt($attemptId, $studentId);

            abort(403, 'Exam time has expired.');
        }

        $question = $attempt->questions->firstWhere('id', $questionId);
        if (! $question) {
            abort(403, 'This question does not belong to this attempt.');
        }

        if ($question->isEssay()) {
            $trimmedText = trim((string) $answerText);
            if ($trimmedText === '') {
                abort(422, 'Essay answer cannot be empty.');
            }

            $answer = Answer::updateOrCreate(
                ['attempt_id' => $attemptId, 'question_id' => $questionId],
                [
                    'choice_id' => null,
                    'answer_text' => $trimmedText,
                ]
            );
            $answer->forceFill([
                'is_correct' => null,
                'marks_earned' => 0,
                'graded_at' => null,
                'graded_by' => null,
            ])->save();

            return $answer;
        }

        if ($choiceId === null) {
            abort(422, 'A choice is required for this question.');
        }

        $choice = Choice::findOrFail($choiceId);
        if ($choice->question_id != $question->id) {
            abort(403, 'Selected choice does not belong to this question.');
        }

        $answer = Answer::updateOrCreate(
            ['attempt_id' => $attemptId, 'question_id' => $questionId],
            [
                'choice_id' => $choiceId,
                'answer_text' => null,
            ]
        );
        $answer->forceFill([
            'is_correct' => $choice->is_correct,
            'marks_earned' => $choice->is_correct ? $question->marks : 0,
            'graded_at' => null,
            'graded_by' => null,
        ])->save();

        return $answer;
    }

    public function completeAttempt($attemptId, ?int $studentId = null, bool $skipAvailabilityCheck = false): array
    {
        $result = DB::transaction(function () use ($attemptId, $studentId, $skipAvailabilityCheck) {
            $attempt = ExamAttempt::query()
                ->with(['exam', 'questions'])
                ->whereKey($attemptId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($studentId !== null && $attempt->student_id !== $studentId) {
                abort(403, 'You do not own this attempt.');
            }

            if ($attempt->status !== ExamAttempt::STATUS_ONGOING) {
                return [
                    'kind' => 'existing',
                    'payload' => $this->buildAttemptResult($attempt, $attempt->score, status: $attempt->status),
                ];
            }

            if ($studentId !== null && ! $skipAvailabilityCheck) {
                $authResult = $this->attemptAuthorizationService->validateSubmittableAttempt($attempt, $studentId);

                if (! $authResult->isAllowed()) {
                    if (! $this->attemptAuthorizationService->isExamContextRevoked($authResult)) {
                        abort($authResult->getStatusCode(), $authResult->getMessage());
                    }
                }
            }

            $totalScore = (float) $attempt->answers()->sum('marks_earned');

            if ($this->attemptNeedsEssayGrading($attempt)) {
                $attempt->forceFill([
                    'score' => $totalScore,
                    'status' => ExamAttempt::STATUS_AWAITING_GRADING,
                    'finished_at' => now(),
                ])->save();

                return [
                    'kind' => 'awaiting_grading',
                    'attempt' => $attempt,
                    'payload' => $this->buildAttemptResult(
                        $attempt,
                        $totalScore,
                        status: ExamAttempt::STATUS_AWAITING_GRADING,
                    ),
                ];
            }

            return [
                'kind' => 'finalized',
                'attempt' => $attempt,
                'payload' => $this->finalizeAttemptScoreWithinTransaction($attempt, $totalScore),
            ];
        });

        if ($result['kind'] === 'awaiting_grading') {
            $attempt = $result['attempt']->refresh()->loadMissing(['student.user', 'exam.course']);
            StudentExamAttemptAwaitingGrading::dispatch($attempt);

            return $result['payload'];
        }

        if ($result['kind'] === 'finalized') {
            $attempt = $result['attempt']->refresh()->loadMissing(['student.user', 'exam.course']);
            StudentExamAttemptCompleted::dispatch($attempt);

            return $result['payload'];
        }

        return $result['payload'];
    }

    public function finalizeAttemptScore(ExamAttempt $attempt, float $totalScore): array
    {
        $result = DB::transaction(function () use ($attempt, $totalScore) {
            $lockedAttempt = ExamAttempt::query()
                ->with(['exam', 'questions'])
                ->whereKey($attempt->id)
                ->lockForUpdate()
                ->firstOrFail();

            return $this->finalizeAttemptScoreWithinTransaction($lockedAttempt, $totalScore);
        });

        $attempt->refresh()->loadMissing(['student.user', 'exam.course']);
        StudentExamAttemptCompleted::dispatch($attempt);

        return $result;
    }

    private function closeOngoingAttempt(int $attemptId, int $studentId): array
    {
        return $this->completeAttempt($attemptId, $studentId, skipAvailabilityCheck: true);
    }

    private function finalizeAttemptScoreWithinTransaction(ExamAttempt $attempt, float $totalScore): array
    {
        $attemptPassingMark = $this->getAttemptPassingMark($attempt);
        $isPassed = $totalScore >= $attemptPassingMark;
        $timedOut = $this->attemptAuthorizationService->isTimedOut($attempt);

        $status = $isPassed ? 'passed' : ($timedOut ? 'timed_out' : 'failed');

        $attempt->forceFill([
            'score' => $totalScore,
            'status' => $status,
            'finished_at' => $attempt->finished_at ?? now(),
        ])->save();

        if ($isPassed && $attempt->exam?->is_final) {
            $enrollment = Enrollment::where('student_id', '=', $attempt->student_id, 'and')
                ->where('course_id', '=', $attempt->exam->course_id, 'and')
                ->first();

            if ($enrollment && ! $enrollment->is_completed) {
                $enrollment->markAsCompleted();
            }
        }

        return $this->buildAttemptResult($attempt, $totalScore, $isPassed, $status);
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

    public function getAttemptTimeStatus(int $attemptId, int $studentId): array
    {
        $attempt = ExamAttempt::with('exam')->findOrFail($attemptId);

        if ($attempt->student_id !== $studentId) {
            abort(403, 'This attempt does not belong to the authenticated student.');
        }

        if (
            $attempt->status === ExamAttempt::STATUS_ONGOING
            && $this->attemptAuthorizationService->isTimedOut($attempt)
        ) {
            $this->completeAttempt($attemptId, $studentId);
            $attempt->refresh()->load('exam');
        }

        $payload = array_merge(
            [
                'attempt_id' => $attempt->id,
                'status' => $attempt->status,
            ],
            $this->attemptAuthorizationService->getTimeStatusPayload($attempt),
        );

        if ($attempt->status !== ExamAttempt::STATUS_ONGOING) {
            $payload['results'] = $this->getAttemptResultsPayload($attempt);
        }

        return $payload;
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

    public function getStaffAttemptReview(int $attemptId): ExamAttempt
    {
        return ExamAttempt::reviewable()
            ->with([
                'exam:id,title,total_marks,passing_mark,course_id',
                'questionsWithTrashed.choices',
                'answers',
                'integrityEvents',
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
        $status ??= $attempt->status;

        if ($status === ExamAttempt::STATUS_AWAITING_GRADING) {
            $isPassed = null;
        } elseif ($isPassed === null) {
            $attempt->forceFill(['status' => $status]);
            $isPassed = $attempt->resolveIsPassed();
            if ($isPassed === null) {
                $isPassed = $score >= $attemptPassingMark;
            }
        }

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
            'requires_review' => $isPassed === true && $attempt->exam->is_final,
        ];
    }

    private function attemptNeedsEssayGrading(ExamAttempt $attempt): bool
    {
        $essayQuestionIds = $attempt->questions
            ->filter(fn (Question $question) => $question->isEssay())
            ->pluck('id');

        if ($essayQuestionIds->isEmpty()) {
            return false;
        }

        return $attempt->answers()
            ->whereIn('question_id', $essayQuestionIds)
            ->whereNotNull('answer_text')
            ->where('answer_text', '!=', '')
            ->exists();
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

    private function isDeadlockException(\Throwable $exception): bool
    {
        if (! $exception instanceof QueryException) {
            return false;
        }

        return (int) ($exception->errorInfo[1] ?? 0) === 1213;
    }

    private function isDuplicateOngoingSlotException(\Throwable $exception): bool
    {
        if ($exception instanceof UniqueConstraintViolationException) {
            return true;
        }

        if (! $exception instanceof QueryException) {
            return false;
        }

        if ((int) ($exception->errorInfo[1] ?? 0) !== 1062) {
            return false;
        }

        return str_contains(strtolower($exception->getMessage()), 'ongoing_slot');
    }
}
