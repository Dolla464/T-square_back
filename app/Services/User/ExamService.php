<?php

namespace App\Services\User;

use App\Events\StudentExamAttemptCompleted;
use App\Mail\CertificateMail;
use App\Notifications\CertificateReady;
use App\Models\Answer;
use App\Models\Choice;
use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Question;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ExamService
{
    protected $certificateService;

    public function __construct(CertificateService $certificateService)
    {
        $this->certificateService = $certificateService;
    }

    /**
     * Get all available exams for a student
     *
     * @param [type] $student
     * @return Collection
     */
    public function getAvailableExams($student)
    {
        // Get the available exams and count the student's attempts for each exam in the same query
        return $student->availableExams()
            ->where('is_active', true)
            ->with('course')
            ->withCount(['attempts' => function ($q) use ($student) {
                $q->where('student_id', $student->id);
            }])
            ->get();
    }

    public function startAttempt(int $studentId, int $examId)
    {
        // 1. Protection: Find an ongoing attempt for the same student and exam
        $existingAttempt = ExamAttempt::where('student_id', $studentId)
            ->where('exam_id', $examId)
            ->where('status', 'ongoing')
            ->first();

        if ($existingAttempt) {
            // If an ongoing attempt is found, we load the questions and choices for it
            return $existingAttempt->load(['questions' => function ($query) {
                // To ensure the random ordering of the choices within the question
                $query->with('choices');
            }]);
        }

        // 2. If no ongoing attempt is found, fetch the exam data and check the entry conditions
        $exam = Exam::findOrFail($examId);

        // Check the enrollment (Enrollment)
        $isEnrolled = Enrollment::where('student_id', $studentId)
            ->where('course_id', $exam->course_id)
            ->exists();

        if (!$isEnrolled) {
            abort(403, 'Sorry, you are not enrolled in this course to take the exam.');
        }

        // Calculate the number of previous attempts to check the allowed limit
        $attemptsCount = ExamAttempt::where('student_id', $studentId)
            ->where('exam_id', $examId)
            ->count();

        if ($exam->max_attempts && $attemptsCount >= $exam->max_attempts) {
            abort(403, 'Sorry, you have exhausted the maximum number of attempts available for this exam!');
        }

        // 3. Create a new attempt record
        $attempt = ExamAttempt::create([
            'student_id' => $studentId,
            'exam_id' => $examId,
            'status' => 'ongoing',
            'started_at' => now(),
        ]);

        // 4. Dynamic question bank: determine the required limit based on what the admin specified in the exam
        $limit = $exam->questions_per_attempt ?? 10;

        // Pull question IDs (IDs) randomly by the required number from the question bank of this exam
        $randomQuestionIds = Question::where('exam_id', $examId)
            ->inRandomOrder() // Randomize the questions
            ->take($limit)    // Pull only the required number (e.g. 50 out of 100)
            ->pluck('id');

        // 5. Attach the randomly selected questions to the current attempt in the intermediate table to fix them
        $attempt->questions()->attach($randomQuestionIds);

        // 6. Return the attempt loaded with only the selected questions and their random choices
        return $attempt->load(['questions' => function ($query) {
            $query->with('choices');
        }]);
    }

    /**
     *  Save one question answer - now only allows answers for the exam's questions
     */
    public function saveAnswer(int $attemptId, int $questionId, int $choiceId)
    {
        // Added loading the exam with the attempts_count for the student to ensure the save function
        $attempt = ExamAttempt::with(['exam' => function ($q) use ($attemptId) {
            $q->withCount(['attempts' => function ($sq) use ($attemptId) {
                // Get the number of attempts based on the student who owns this attempt
                $sq->where('student_id', DB::raw('(SELECT student_id FROM exam_attempts WHERE id = ' . $attemptId . ')'));
            }]);
        }, 'exam.questions'])->findOrFail($attemptId);

        // Protection: Ensure the exam is still in the ongoing state and has not been closed
        if ($attempt->status !== 'ongoing') {
            abort(403, 'This attempt is already closed and cannot be modified.');
        }

        // Additional protection: Ensure the student has not exceeded the attempts during the answer (protection against hacking)
        if ($attempt->exam->max_attempts && $attempt->exam->attempts_count > $attempt->exam->max_attempts) {
            abort(403, 'Sorry, you have exceeded the maximum number of attempts.');
        }

        $question = $attempt->exam->questions->where('id', $questionId)->first();
        if (! $question) {
            abort(403, 'This question does not belong to this exam.');
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
            return [
                'score' => $attempt->score,
                'total_marks' => $attempt->exam->total_marks,
                'passing_mark' => $attempt->exam->passing_mark,
                'is_passed' => $attempt->score >= $attempt->exam->passing_mark,
                'status' => $attempt->status,
            ];
        }

        $result = DB::transaction(function () use ($attempt) {
            $totalScore = $attempt->answers()->sum('marks_earned');
            $isPassed = $totalScore >= $attempt->exam->passing_mark;
            $certificateEnrollmentId = null;

            $attempt->update([
                'score' => $totalScore,
                'status' => $isPassed ? 'passed' : 'failed',
                'finished_at' => now(),
            ]);

            // The update must be here before the return of the transaction
            if ($isPassed && $attempt->exam->is_final) {
                // 1. Update the Enrollment (Use Eloquent to trigger observers)
                $enrollment = Enrollment::where('student_id', '=', $attempt->student_id, 'and')
                    ->where('course_id', '=', $attempt->exam->course_id, 'and')
                    ->first();

                if ($enrollment) {
                    if (!$enrollment->is_completed) {
                        $enrollment->markAsCompleted();
                    }

                    $certificateEnrollmentId = $enrollment->id; // Store the enrollment ID for the certificate
                }
            }

            // Now we return the result to the controller
            return [
                'score' => $totalScore,
                'total_marks' => $attempt->exam->total_marks,
                'passing_mark' => $attempt->exam->passing_mark,
                'is_passed' => $isPassed,
                'status' => $isPassed ? 'passed' : 'failed',
                'certificate_enrollment_id' => $certificateEnrollmentId,
            ];
        });

        if ($result['certificate_enrollment_id']) {
            $this->issueCertificateAndSendEmail($result['certificate_enrollment_id'], $attempt);
        }

        $attempt->refresh()->loadMissing(['student.user', 'exam.course']);
        StudentExamAttemptCompleted::dispatch($attempt);

        unset($result['certificate_enrollment_id']);

        return $result;
    }

    private function issueCertificateAndSendEmail(int $enrollmentId, ExamAttempt $attempt): void
    {
        try {
            $enrollment = Enrollment::with(['student', 'course'])->find($enrollmentId);

            if (! $enrollment) {
                return;
            }

            $attempt->refresh()->load(['student.user', 'exam.course']);
            $certificate = $this->certificateService->issueCertificate($enrollment);
            $pdfPath = $certificate->certificate_url;
            $email = $attempt->student->user?->email;

            if ($email) {
                Mail::to($email)->send(new CertificateMail($attempt, $pdfPath));

                $enrollment->student?->notify(new CertificateReady($enrollment));

                Log::info('Certificate email sent', [
                    'attempt_id' => $attempt->id,
                    'student_id' => $attempt->student_id,
                    'email' => $email,
                ]);
            } else {
                Log::warning('Certificate email skipped: no user email', [
                    'attempt_id' => $attempt->id,
                    'student_id' => $attempt->student_id,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('Certificate generation/email failed: ' . $e->getMessage(), [
                'attempt_id' => $attempt->id,
                'enrollment_id' => $enrollmentId,
            ]);
        }
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
