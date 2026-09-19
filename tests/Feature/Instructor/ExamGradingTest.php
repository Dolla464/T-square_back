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
use App\Notifications\StudentExamAttemptStatusNotification;
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

function createGradingScenario(): array
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

    $group = LearningGroup::factory()->create(['course_id' => $course->id]);

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
    $start = test()->postJson('/api/exams/start', ['exam_id' => $exam->id])->assertCreated();
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

    $attempt = ExamAttempt::find($attemptId);
    $essayAnswer = $attempt->answers()->where('question_id', $essay->id)->first();

    return compact(
        'instructor',
        'student',
        'course',
        'exam',
        'attempt',
        'essay',
        'essayAnswer',
    );
}

function actingAsInstructor(Instructor $instructor): void
{
    Sanctum::actingAs($instructor->user, ['*']);
}

it('lists pending grading attempts for assigned instructors', function (): void {
    ['instructor' => $instructor, 'attempt' => $attempt] = createGradingScenario();

    actingAsInstructor($instructor);

    test()->getJson('/api/instructor/exam-grading')
        ->assertOk()
        ->assertJsonPath('data.0.attempt_id', $attempt->id)
        ->assertJsonPath('data.0.status', 'awaiting_grading');
});

it('rejects partial essay grading payloads', function (): void {
    ['instructor' => $instructor, 'attempt' => $attempt] = createGradingScenario();

    actingAsInstructor($instructor);

    test()->postJson("/api/instructor/exam-grading/{$attempt->id}", [
        'answers' => [],
    ])->assertUnprocessable();

    expect($attempt->fresh()->status)->toBe('awaiting_grading');
});

it('finalizes attempt after grading all essay answers', function (): void {
    Notification::fake();

    ['instructor' => $instructor, 'attempt' => $attempt, 'essayAnswer' => $essayAnswer, 'student' => $student] = createGradingScenario();

    actingAsInstructor($instructor);

    test()->postJson("/api/instructor/exam-grading/{$attempt->id}", [
        'answers' => [
            ['answer_id' => $essayAnswer->id, 'marks_earned' => 8],
        ],
    ])->assertOk()
        ->assertJsonPath('data.status', 'passed')
        ->assertJsonPath('data.score', 18)
        ->assertJsonPath('data.is_passed', true);

    $attempt->refresh();
    expect($attempt->status)->toBe('passed')
        ->and($attempt->graded_by)->toBe($instructor->id);

    Notification::assertSentTo(
        $student->user,
        StudentExamAttemptStatusNotification::class
    );
});

it('rejects re-grading after finalization', function (): void {
    ['instructor' => $instructor, 'attempt' => $attempt, 'essayAnswer' => $essayAnswer] = createGradingScenario();

    actingAsInstructor($instructor);

    test()->postJson("/api/instructor/exam-grading/{$attempt->id}", [
        'answers' => [
            ['answer_id' => $essayAnswer->id, 'marks_earned' => 5],
        ],
    ])->assertOk();

    test()->postJson("/api/instructor/exam-grading/{$attempt->id}", [
        'answers' => [
            ['answer_id' => $essayAnswer->id, 'marks_earned' => 10],
        ],
    ])->assertStatus(409);
});

it('denies grading for instructors not assigned to the course', function (): void {
    ['attempt' => $attempt, 'essayAnswer' => $essayAnswer] = createGradingScenario();
    $outsider = Instructor::factory()->create();
    $outsider->user->assignRole('instructor');

    actingAsInstructor($outsider);

    test()->getJson("/api/instructor/exam-grading/{$attempt->id}")
        ->assertForbidden();

    test()->postJson("/api/instructor/exam-grading/{$attempt->id}", [
        'answers' => [
            ['answer_id' => $essayAnswer->id, 'marks_earned' => 5],
        ],
    ])->assertForbidden();
});
