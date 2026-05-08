<?php

namespace App\Services\User;

use App\Events\StudentExamAttemptCompleted;
use App\Mail\CertificateMail;
use App\Models\Answer;
use App\Models\Choice;
use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Question;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ExamService
{
    protected $certificateService;

    public function __construct(\App\Services\User\CertificateService $certificateService)
    {
        $this->certificateService = $certificateService;
    }
    /**
     * get all available exams for a student
     *
     * @param [type] $student
     * @return Collection
     */
    public function getAvailableExams($student)
    {
        // بنجيب الامتحانات المتاحة بناءً على الكورسات اللي الطالب مشترك فيها ويكون الامتحان active
        return $student->availableExams()->where('is_active', true)->with('course')->get();
    }
    public function startAttempt(int $studentId, int $examId)
    {
        $exam = Exam::withCount(['attempts' => function ($q) use ($studentId) {
            $q->where('student_id', '=', $studentId, 'and');
        }])->findOrFail($examId);

        $isEnrolled = Enrollment::where('student_id', '=', $studentId, 'and')
            ->where('course_id', '=', $exam->course_id, 'and')
            ->exists();

        if (!$isEnrolled) {
            abort(403, 'You are not enrolled in this course.');
        }

        if ($exam->attempts_count >= $exam->max_attempts) {
            abort(403, 'عفواً، لقد استنفدت محاولاتك!');
        }

        $attempt = ExamAttempt::create([
            'student_id' => $studentId,
            'exam_id' => $examId,
            'status' => 'ongoing',
            'started_at' => now(),
        ]);

        return $attempt;
    }
    /**
     *  save one question answer - now only allows answers for the exam's questions
     */
    public function saveAnswer(int $attemptId, int $questionId, int $choiceId)
    {
        $attempt = ExamAttempt::with('exam.questions')->findOrFail($attemptId);

        // Ensure this question belongs to the exam for this attempt
        $question = $attempt->exam->questions->where('id', $questionId)->first();
        if (!$question) {
            abort(403, 'This question does not belong to this exam.');
        }

        $choice = Choice::findOrFail($choiceId);

        if ($choice->question_id != $question->id) {
            abort(403, 'Selected choice does not belong to this question.');
        }

        // استخدام updateOrCreate عشان لو الطالب غير رأيه في نفس السؤال
        return Answer::updateOrCreate(
            ['attempt_id' => $attemptId, 'question_id' => $questionId],
            [
                'choice_id'    => $choiceId,
                'is_correct'   => $choice->is_correct,
                'marks_earned' => $choice->is_correct ? $question->marks : 0
            ]
        );
    }

    /**
     * إنهاء الامتحان وحساب النتيجة النهائية
     */
    public function completeAttempt($attemptId, ?int $studentId = null)
    {
        $attempt = ExamAttempt::with('exam')->findOrFail($attemptId);

        if ($studentId && $attempt->student_id !== $studentId) {
            abort(403, 'You are not allowed to submit this attempt.');
        }

        if ($attempt->status !== 'ongoing') {
            return [
                'score'        => $attempt->score,
                'total_marks'  => $attempt->exam->total_marks,
                'passing_mark' => $attempt->exam->passing_mark,
                'is_passed'    => $attempt->score >= $attempt->exam->passing_mark,
                'status'       => $attempt->status,
            ];
        }

        $result = DB::transaction(function () use ($attempt) {
            $totalScore = $attempt->answers()->sum('marks_earned');
            $isPassed = $totalScore >= $attempt->exam->passing_mark;
            $certificateEnrollmentId = null;

            $attempt->update([
                'score'       => $totalScore,
                'status'      => $isPassed ? 'passed' : 'failed',
                'finished_at' => now()
            ]);

            // التحديث لازم يكون هنا قبل الـ return بتاع الـ transaction
            if ($isPassed && $attempt->exam->is_final) {
                // 1. تحديث الـ Enrollment (Use Eloquent to trigger observers)
                $enrollment = Enrollment::where('student_id', '=', $attempt->student_id, 'and')
                    ->where('course_id', '=', $attempt->exam->course_id, 'and')
                    ->first();
                
                if ($enrollment) {
                    $enrollment->update([
                        'is_completed' => true,
                        'completed_at' => now()
                    ]);

                    $certificateEnrollmentId = $enrollment->id;
                }
            }

            // دلوقتي نرجع النتيجة للـ Controller
            return [
                'score'        => $totalScore,
                'total_marks'  => $attempt->exam->total_marks,
                'passing_mark' => $attempt->exam->passing_mark,
                'is_passed'    => $isPassed,
                'status'       => $isPassed ? 'passed' : 'failed',
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

            if (!$enrollment) {
                return;
            }

            $attempt->refresh()->load(['student.user', 'exam.course']);
            $certificate = $this->certificateService->issueCertificate($enrollment);
            $pdfPath = $certificate->certificate_url;
            $email = $attempt->student->user?->email;

            if ($email) {
                Mail::to($email)->send(new CertificateMail($attempt, $pdfPath));

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

    public function getStudentResults($studentId)
    {
        return ExamAttempt::where('student_id', '=', $studentId, 'and')
            // أضفنا passed و failed بناءً على التعديل الأخير في الـ Service
            ->whereIn('status', ['passed', 'failed', 'completed', 'timed_out'], 'and', false)
            ->with(['exam' => function ($query) {
                // بنجيب الكورس واسم الامتحان وهل هو نهائي ولا لأ
                $query->select('id', 'course_id', 'title', 'passing_mark', 'is_final');
            }, 'exam.course:id,title']) // نجيب بس الأعمدة اللي محتاجينها لتوفير الـ Memory
            ->orderBy('finished_at', 'desc')
            ->get()
            ->map(function ($attempt) {
                // إضافة معلومة سريعة للفرونت إند: هل دي شهادة قابلة للتحميل؟
                $attempt->can_download_certificate = ($attempt->status === 'passed' && $attempt->exam->is_final);
                return $attempt;
            });
    }
}
