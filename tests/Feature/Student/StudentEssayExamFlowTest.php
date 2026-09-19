<?php

use App\Models\Choice;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Instructor;
use App\Models\LearningGroup;
use App\Models\Order;
use App\Models\Question;
use App\Models\Student;
use App\Notifications\InstructorGradingRequiredNotification;
use App\Support\CourseInstructorSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

function createMixedExamContext(): array
{
    $instructor = Instructor::factory()->create();
    $instructor->user->assignRole('instructor');

    $course = Course::factory()->create([
        'instructor_id' => $instructor->id,
        'status' => 'published',
        'published_at' => now(),
    ]);
    app(CourseInstructorSync::class)->sync($course, [$instructor->id]);

    $student = Student::factory()->create();
    $student->user->assignRole('student');

    $order = Order::factory()->create([
        'student_id' => $student->id,
        'status' => 'completed',
    ]);

    $group = LearningGroup::factory()->create([
        'course_id' => $course->id,
    ]);

    Enrollment::factory()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'order_id' => $order->id,
        'group_id' => $group->id,
    ]);

    $exam = Exam::factory()->create([
        'course_id' => $course->id,
        'questions_per_attempt' => 2,
        'total_marks' => 20,
        'passing_mark' => 12,
        'max_attempts' => 3,
        'shuffle_questions' => false,
    ]);

    DB::table('group_exam_activations')->insert([
        'exam_id' => $exam->id,
        'learning_group_id' => $group->id,
        'activated_at' => now(),
    ]);

    $mcq = Question::factory()->create([
        'exam_id' => $exam->id,
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
        'exam_id' => $exam->id,
        'marks' => 10,
    ]);

    Sanctum::actingAs($student->user, ['*']);

    return compact('student', 'course', 'exam', 'group', 'mcq', 'essay', 'instructor');
}

it('defers mixed exam submission when an essay answer was submitted', function (): void {
    Notification::fake();

    ['exam' => $exam, 'mcq' => $mcq, 'essay' => $essay, 'instructor' => $instructor] = createMixedExamContext();

    $start = test()->postJson('/api/exams/start', ['exam_id' => $exam->id])->assertCreated();
    $attemptId = $start->json('data.attempt_id');
    $mcqPayload = collect($start->json('data.questions'))->firstWhere('id', $mcq->id);
    $correctChoice = collect($mcqPayload['choices'])->firstWhere('choice_text', 'Correct');

    test()->postJson('/api/exams/save-answer', [
        'attempt_id' => $attemptId,
        'question_id' => $mcq->id,
        'choice_id' => $correctChoice['id'],
    ])->assertOk();

    test()->postJson('/api/exams/save-answer', [
        'attempt_id' => $attemptId,
        'question_id' => $essay->id,
        'answer_text' => 'This is my essay answer.',
    ])->assertOk();

    $submit = test()->postJson("/api/exams/{$attemptId}/submit")->assertOk();

    $submit->assertJsonPath('results.status', 'awaiting_grading')
        ->assertJsonPath('results.is_passed', null)
        ->assertJsonPath('results.score', 10)
        ->assertJsonPath('results.percentage', null);

    expect(ExamAttempt::find($attemptId)->status)->toBe('awaiting_grading');

    Notification::assertSentTo(
        $instructor->user,
        InstructorGradingRequiredNotification::class
    );
});

it('finalizes immediately when essay questions exist but none were answered', function (): void {
    ['exam' => $exam, 'mcq' => $mcq] = createMixedExamContext();

    $start = test()->postJson('/api/exams/start', ['exam_id' => $exam->id])->assertCreated();
    $attemptId = $start->json('data.attempt_id');
    $mcqPayload = collect($start->json('data.questions'))->firstWhere('id', $mcq->id);
    $correctChoice = collect($mcqPayload['choices'])->firstWhere('choice_text', 'Correct');

    test()->postJson('/api/exams/save-answer', [
        'attempt_id' => $attemptId,
        'question_id' => $mcq->id,
        'choice_id' => $correctChoice['id'],
    ])->assertOk();

    test()->postJson("/api/exams/{$attemptId}/submit")
        ->assertOk()
        ->assertJsonPath('results.status', 'failed')
        ->assertJsonPath('results.is_passed', false);
});

it('rejects empty essay answer text', function (): void {
    ['exam' => $exam, 'essay' => $essay] = createMixedExamContext();

    $start = test()->postJson('/api/exams/start', ['exam_id' => $exam->id])->assertCreated();
    $attemptId = $start->json('data.attempt_id');

    test()->postJson('/api/exams/save-answer', [
        'attempt_id' => $attemptId,
        'question_id' => $essay->id,
        'answer_text' => '   ',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['answer_text']);
});

it('exposes awaiting_grading in my-results with null is_passed', function (): void {
    ['student' => $student, 'exam' => $exam, 'mcq' => $mcq, 'essay' => $essay] = createMixedExamContext();

    $start = test()->postJson('/api/exams/start', ['exam_id' => $exam->id])->assertCreated();
    $attemptId = $start->json('data.attempt_id');
    $mcqPayload = collect($start->json('data.questions'))->firstWhere('id', $mcq->id);
    $correctChoice = collect($mcqPayload['choices'])->firstWhere('choice_text', 'Correct');

    test()->postJson('/api/exams/save-answer', [
        'attempt_id' => $attemptId,
        'question_id' => $mcq->id,
        'choice_id' => $correctChoice['id'],
    ]);
    test()->postJson('/api/exams/save-answer', [
        'attempt_id' => $attemptId,
        'question_id' => $essay->id,
        'answer_text' => 'Essay response',
    ]);
    test()->postJson("/api/exams/{$attemptId}/submit");

    test()->getJson('/api/exams/my-results?exam_id='.$exam->id)
        ->assertOk()
        ->assertJsonPath('data.0.status', 'awaiting_grading')
        ->assertJsonPath('data.0.is_passed', null);
});

it('returns essay answer text in user_answers when resuming an ongoing attempt', function (): void {
    ['exam' => $exam, 'essay' => $essay] = createMixedExamContext();

    $start = test()->postJson('/api/exams/start', ['exam_id' => $exam->id])->assertCreated();
    $attemptId = $start->json('data.attempt_id');

    test()->postJson('/api/exams/save-answer', [
        'attempt_id' => $attemptId,
        'question_id' => $essay->id,
        'answer_text' => 'Saved essay text',
    ])->assertOk();

    $resume = test()->postJson('/api/exams/start', ['exam_id' => $exam->id])->assertOk();

    expect($resume->json('data.user_answers.'.$essay->id))->toBe('Saved essay text');
});
