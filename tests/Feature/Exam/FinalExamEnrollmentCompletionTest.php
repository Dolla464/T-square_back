<?php

use App\Models\Certificate;
use App\Models\Choice;
use App\Models\Course;
use App\Models\CourseReview;
use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Instructor;
use App\Models\LearningGroup;
use App\Models\Order;
use App\Models\Question;
use App\Models\Student;
use App\Notifications\CourseReviewRequired;
use App\Notifications\InstructorExamResultNotification;
use App\Notifications\StudentExamAttemptStatusNotification;
use App\Support\CourseInstructorSync;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    Role::create(['name' => 'student', 'guard_name' => 'web']);
    Role::create(['name' => 'instructor', 'guard_name' => 'web']);
});

function createFinalExamContext(
    string $groupStatus = 'active',
    bool $enrollmentCompleted = false,
    bool $isFinal = true,
    ?string $endDate = null,
): array {
    $instructor = Instructor::factory()->create();
    $instructor->user->assignRole('instructor');

    $course = Course::factory()->create([
        'instructor_id' => $instructor->id,
        'status' => 'published',
        'published_at' => now(),
        'duration_weeks' => 4,
    ]);
    app(CourseInstructorSync::class)->sync($course, [$instructor->id]);

    $student = Student::factory()->create();
    $student->user->assignRole('student');

    $order = Order::factory()->create([
        'student_id' => $student->id,
        'status' => 'completed',
    ]);

    $group = LearningGroup::create([
        'group_name' => 'Final Exam Batch',
        'course_id' => $course->id,
        'course_instructor_id' => courseInstructorIdFor($course, $instructor),
        'start_date' => now()->subWeeks(8)->toDateString(),
        'end_date' => $endDate ?? now()->subDay()->toDateString(),
        'status' => $groupStatus,
        'enrolled_students' => 1,
    ]);

    $enrollment = Enrollment::factory()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'order_id' => $order->id,
        'group_id' => $group->id,
        'is_completed' => $enrollmentCompleted,
        'completed_at' => $enrollmentCompleted ? now() : null,
    ]);

    $exam = Exam::factory()->create([
        'course_id' => $course->id,
        'questions_per_attempt' => 3,
        'total_marks' => 100,
        'passing_mark' => 60,
        'max_attempts' => 3,
        'shuffle_questions' => false,
        'is_final' => $isFinal,
    ]);

    DB::table('group_exam_activations')->insert([
        'exam_id' => $exam->id,
        'learning_group_id' => $group->id,
        'activated_at' => now(),
    ]);

    for ($i = 0; $i < 5; $i++) {
        $question = Question::factory()->create([
            'exam_id' => $exam->id,
            'marks' => 10,
        ]);

        Choice::factory()->create([
            'question_id' => $question->id,
            'choice_text' => 'Correct '.$i,
            'is_correct' => true,
        ]);

        Choice::factory()->count(3)->create([
            'question_id' => $question->id,
            'is_correct' => false,
        ]);
    }

    Sanctum::actingAs($student->user, ['*']);

    return compact('instructor', 'student', 'course', 'group', 'enrollment', 'exam');
}

function submitPassingExamAttempt(Exam $exam): ExamAttempt
{
    $startResponse = test()->postJson('/api/exams/start', ['exam_id' => $exam->id]);
    $startResponse->assertCreated();

    $attemptId = $startResponse->json('data.attempt_id');

    foreach ($startResponse->json('data.questions') as $question) {
        $correctChoice = collect($question['choices'])->first(
            fn ($choice) => str_starts_with($choice['choice_text'], 'Correct')
        );

        test()->postJson('/api/exams/save-answer', [
            'attempt_id' => $attemptId,
            'question_id' => $question['id'],
            'choice_id' => $correctChoice['id'],
        ])->assertOk();
    }

    test()->postJson("/api/exams/{$attemptId}/submit")
        ->assertOk()
        ->assertJsonPath('results.is_passed', true)
        ->assertJsonPath('results.status', 'passed');

    return ExamAttempt::query()->findOrFail($attemptId);
}

function createFinalEssayExamContext(string $groupStatus = 'active', bool $enrollmentCompleted = false): array
{
    $context = createFinalExamContext($groupStatus, $enrollmentCompleted, true);

    Question::query()->where('exam_id', $context['exam']->id)->delete();

    $context['exam']->update([
        'questions_per_attempt' => 2,
        'total_marks' => 20,
        'passing_mark' => 12,
    ]);

    $mcq = Question::factory()->create([
        'exam_id' => $context['exam']->id,
        'type' => Question::TYPE_MCQ,
        'marks' => 10,
    ]);
    Choice::factory()->create([
        'question_id' => $mcq->id,
        'choice_text' => 'Correct',
        'is_correct' => true,
    ]);
    Choice::factory()->count(3)->create([
        'question_id' => $mcq->id,
        'is_correct' => false,
    ]);

    $essay = Question::factory()->essay()->create([
        'exam_id' => $context['exam']->id,
        'marks' => 10,
    ]);

    Sanctum::actingAs($context['student']->user, ['*']);

    $start = test()->postJson('/api/exams/start', ['exam_id' => $context['exam']->id])->assertCreated();
    $attemptId = $start->json('data.attempt_id');
    $correctChoice = collect(
        collect($start->json('data.questions'))->firstWhere('id', $mcq->id)['choices']
    )->firstWhere('choice_text', 'Correct');

    test()->postJson('/api/exams/save-answer', [
        'attempt_id' => $attemptId,
        'question_id' => $mcq->id,
        'choice_id' => $correctChoice['id'],
    ]);
    test()->postJson('/api/exams/save-answer', [
        'attempt_id' => $attemptId,
        'question_id' => $essay->id,
        'answer_text' => 'Essay answer body',
    ]);
    test()->postJson("/api/exams/{$attemptId}/submit")->assertOk();

    $context['attempt'] = ExamAttempt::query()->findOrFail($attemptId);
    $context['essayAnswer'] = $context['attempt']->answers()->where('question_id', $essay->id)->firstOrFail();

    return $context;
}

it('keeps enrollment incomplete when a final exam is passed on an active group', function (): void {
    ['group' => $group, 'enrollment' => $enrollment, 'exam' => $exam] = createFinalExamContext('active');

    $attempt = submitPassingExamAttempt($exam);

    $enrollment->refresh();
    $group->refresh();

    expect($attempt->status)->toBe('passed');
    expect($enrollment->is_completed)->toBeFalse();
    expect($enrollment->completed_at)->toBeNull();
    expect($group->status)->toBe('active');
});

it('leaves enrollment completed when a final exam is passed on an already completed group', function (): void {
    ['group' => $group, 'enrollment' => $enrollment, 'exam' => $exam] = createFinalExamContext('completed', true);

    $attempt = submitPassingExamAttempt($exam);

    $enrollment->refresh();
    $group->refresh();

    expect($attempt->status)->toBe('passed');
    expect($enrollment->is_completed)->toBeTrue();
    expect($enrollment->completed_at)->not->toBeNull();
    expect($group->status)->toBe('completed');
});

it('keeps enrollment incomplete when a final exam is failed on an active group', function (): void {
    ['group' => $group, 'enrollment' => $enrollment, 'exam' => $exam] = createFinalExamContext('active');

    $startResponse = test()->postJson('/api/exams/start', ['exam_id' => $exam->id])->assertCreated();
    $attemptId = $startResponse->json('data.attempt_id');

    foreach ($startResponse->json('data.questions') as $question) {
        $wrongChoice = collect($question['choices'])->first(
            fn ($choice) => ! str_starts_with($choice['choice_text'], 'Correct')
        );

        test()->postJson('/api/exams/save-answer', [
            'attempt_id' => $attemptId,
            'question_id' => $question['id'],
            'choice_id' => $wrongChoice['id'],
        ]);
    }

    test()->postJson("/api/exams/{$attemptId}/submit")
        ->assertOk()
        ->assertJsonPath('results.is_passed', false);

    $enrollment->refresh();
    $group->refresh();

    expect($enrollment->is_completed)->toBeFalse();
    expect($group->status)->toBe('active');
});

it('does not apply final exam completion rules to non-final exams', function (): void {
    ['enrollment' => $enrollment, 'exam' => $exam] = createFinalExamContext('active', false, false);

    submitPassingExamAttempt($exam);

    $enrollment->refresh();

    expect($enrollment->is_completed)->toBeFalse();
});

it('does not send CourseReviewRequired when a final exam passes on an active group', function (): void {
    Notification::fake();

    ['student' => $student, 'instructor' => $instructor, 'exam' => $exam] = createFinalExamContext('active');

    submitPassingExamAttempt($exam);

    Notification::assertSentTo($student->user, StudentExamAttemptStatusNotification::class);
    Notification::assertSentTo($instructor->user, InstructorExamResultNotification::class);
    Notification::assertNotSentTo($student->user, CourseReviewRequired::class);
});

it('sends CourseReviewRequired when a final exam passes on a completed enrollment without an existing review', function (): void {
    Notification::fake();

    ['student' => $student, 'exam' => $exam] = createFinalExamContext('completed', true);

    submitPassingExamAttempt($exam);

    Notification::assertSentTo($student->user, CourseReviewRequired::class);
});

it('completes the enrollment and sends CourseReviewRequired after the group closes later', function (): void {
    Carbon::setTestNow('2026-09-26 10:00:00');
    Notification::fake();

    ['group' => $group, 'enrollment' => $enrollment, 'exam' => $exam, 'student' => $student] = createFinalExamContext(
        'active',
        false,
        true,
        '2026-09-25',
    );

    submitPassingExamAttempt($exam);

    $enrollment->refresh();
    expect($enrollment->is_completed)->toBeFalse();
    Notification::assertNotSentTo($student->user, CourseReviewRequired::class);

    Artisan::call('learning-groups:complete-expired');

    $enrollment->refresh();
    $group->refresh();

    expect($group->status)->toBe('completed');
    expect($enrollment->is_completed)->toBeTrue();
    Notification::assertSentTo($student->user, CourseReviewRequired::class);

    Carbon::setTestNow();
});

it('keeps enrollment incomplete after essay grading produces a passing final result on an active group', function (): void {
    Notification::fake();

    ['instructor' => $instructor, 'enrollment' => $enrollment, 'attempt' => $attempt, 'essayAnswer' => $essayAnswer] = createFinalEssayExamContext('active');

    actingAsInstructor($instructor);

    test()->postJson("/api/instructor/exam-grading/{$attempt->id}", [
        'answers' => [
            ['answer_id' => $essayAnswer->id, 'marks_earned' => 10],
        ],
    ])->assertOk()
        ->assertJsonPath('data.status', 'passed');

    $enrollment->refresh();

    expect($enrollment->is_completed)->toBeFalse();
    Notification::assertNotSentTo($enrollment->student->user, CourseReviewRequired::class);
});

it('preserves completed-group behavior for essay-graded final exams', function (): void {
    Notification::fake();

    ['instructor' => $instructor, 'enrollment' => $enrollment, 'attempt' => $attempt, 'essayAnswer' => $essayAnswer, 'student' => $student] = createFinalEssayExamContext('completed', true);

    actingAsInstructor($instructor);

    test()->postJson("/api/instructor/exam-grading/{$attempt->id}", [
        'answers' => [
            ['answer_id' => $essayAnswer->id, 'marks_earned' => 10],
        ],
    ])->assertOk()
        ->assertJsonPath('data.status', 'passed');

    $enrollment->refresh();

    expect($enrollment->is_completed)->toBeTrue();
    Notification::assertSentTo($student->user, CourseReviewRequired::class);
});

it('does not send duplicate CourseReviewRequired when the student already submitted a review', function (): void {
    Notification::fake();

    ['student' => $student, 'course' => $course, 'instructor' => $instructor, 'exam' => $exam] = createFinalExamContext('completed', true);

    CourseReview::create([
        'course_id' => $course->id,
        'student_id' => $student->id,
        'instructor_id' => $instructor->id,
        'content_rating' => 5,
        'instructor_rating' => 5,
        'center_rating' => 5,
        'overall_comment' => 'Already reviewed',
    ]);

    submitPassingExamAttempt($exam);

    Notification::assertNotSentTo($student->user, CourseReviewRequired::class);
});

it('does not auto-issue a certificate when a final exam is passed', function (): void {
    ['exam' => $exam, 'student' => $student, 'course' => $course] = createFinalExamContext('active');

    expect(Certificate::query()->count())->toBe(0);

    submitPassingExamAttempt($exam);

    expect(Certificate::query()->where([
        'student_id' => $student->id,
        'course_id' => $course->id,
    ])->count())->toBe(0);
});
