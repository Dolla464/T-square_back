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
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    Role::create(['name' => 'student', 'guard_name' => 'web']);
    Role::create(['name' => 'admin', 'guard_name' => 'web']);
    Role::create(['name' => 'instructor', 'guard_name' => 'web']);
});

function createExamSecurityContext(array $examOverrides = []): array
{
    $course = Course::factory()->create([
        'status' => 'published',
        'published_at' => now(),
    ]);

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

    $exam = Exam::factory()->create(array_merge([
        'course_id' => $course->id,
        'questions_per_attempt' => 2,
        'max_attempts' => 3,
        'shuffle_questions' => false,
    ], $examOverrides));

    DB::table('group_exam_activations')->insert([
        'exam_id' => $exam->id,
        'learning_group_id' => $group->id,
        'activated_at' => now(),
    ]);

    for ($i = 0; $i < 3; $i++) {
        $question = Question::factory()->create([
            'exam_id' => $exam->id,
            'marks' => 10,
        ]);

        Choice::factory()->create([
            'question_id' => $question->id,
            'choice_text' => 'Correct '.$i,
            'is_correct' => true,
        ]);

        Choice::factory()->count(2)->create([
            'question_id' => $question->id,
            'is_correct' => false,
        ]);
    }

    Sanctum::actingAs($student->user, ['*']);

    return compact('student', 'course', 'exam', 'group');
}

function startExamForSecurity(int $examId)
{
    return test()->postJson('/api/exams/start', ['exam_id' => $examId]);
}

it('rejects guest access to exam mutation endpoints', function (): void {
    $course = Course::factory()->create([
        'status' => 'published',
        'published_at' => now(),
    ]);
    $exam = Exam::factory()->create(['course_id' => $course->id]);

    $this->postJson('/api/exams/start', ['exam_id' => $exam->id])->assertUnauthorized();
    $this->postJson('/api/exams/save-answer', [
        'attempt_id' => 1,
        'question_id' => 1,
        'choice_id' => 1,
    ])->assertUnauthorized();
    $this->postJson('/api/exams/1/submit')->assertUnauthorized();
    $this->getJson('/api/exams/attempts/1/review')->assertUnauthorized();
    $this->postJson('/api/admin/exams', ['title' => 'Hack'])->assertUnauthorized();
    $this->postJson('/api/instructor/exams', ['title' => 'Hack'])->assertUnauthorized();

    ['exam' => $enrolledExam] = createExamSecurityContext();

    $this->postJson('/api/admin/exams', [
        'course_id' => $enrolledExam->course_id,
        'title' => 'Hack',
        'description' => 'Hack',
        'duration' => 30,
        'total_marks' => 100,
        'passing_mark' => 60,
        'max_attempts' => 1,
        'questions_per_attempt' => 1,
    ])->assertForbidden();
});

it('prevents cross-student attempt idor on save submit and review', function (): void {
    ['student' => $studentA, 'exam' => $exam] = createExamSecurityContext();

    $start = startExamForSecurity($exam->id)->assertCreated();
    $attemptId = $start->json('data.attempt_id');
    $question = $start->json('data.questions.0');

    $studentB = Student::factory()->create();
    $studentB->user->assignRole('student');
    Sanctum::actingAs($studentB->user, ['*']);

    $this->postJson('/api/exams/save-answer', [
        'attempt_id' => $attemptId,
        'question_id' => $question['id'],
        'choice_id' => $question['choices'][0]['id'],
    ])->assertForbidden();

    $this->postJson("/api/exams/{$attemptId}/submit")->assertForbidden();

    Sanctum::actingAs($studentA->user, ['*']);
    $this->postJson("/api/exams/{$attemptId}/submit")->assertOk();

    Sanctum::actingAs($studentB->user, ['*']);
    $this->getJson("/api/exams/attempts/{$attemptId}/review")->assertForbidden();
});

it('allows multiple historical attempts but only one ongoing attempt per student and exam', function (): void {
    ['student' => $student, 'exam' => $exam] = createExamSecurityContext(['max_attempts' => 3]);

    $failedAttempt = ExamAttempt::create([
        'student_id' => $student->id,
        'exam_id' => $exam->id,
        'duration_minutes' => $exam->duration,
        'started_at' => now()->subHour(),
        'finished_at' => now()->subMinutes(30),
    ]);
    $failedAttempt->forceFill(['score' => 10, 'status' => 'failed'])->save();

    $completedAttempt = ExamAttempt::create([
        'student_id' => $student->id,
        'exam_id' => $exam->id,
        'duration_minutes' => $exam->duration,
        'started_at' => now()->subMinutes(20),
        'finished_at' => now()->subMinutes(10),
    ]);
    $completedAttempt->forceFill(['score' => 20, 'status' => 'completed'])->save();

    $start = startExamForSecurity($exam->id)->assertCreated();
    expect(ExamAttempt::where('student_id', $student->id)->where('exam_id', $exam->id)->count())->toBe(3);
    expect(
        ExamAttempt::where('student_id', $student->id)
            ->where('exam_id', $exam->id)
            ->where('status', ExamAttempt::STATUS_ONGOING)
            ->count()
    )->toBe(1);

    expect($start->json('data.attempt_id'))->not->toBeNull();
});

it('enforces a single ongoing attempt at the database level', function (): void {
    ['student' => $student, 'exam' => $exam] = createExamSecurityContext();

    $firstOngoing = ExamAttempt::create([
        'student_id' => $student->id,
        'exam_id' => $exam->id,
        'duration_minutes' => $exam->duration,
        'started_at' => now(),
    ]);
    $firstOngoing->forceFill(['status' => ExamAttempt::STATUS_ONGOING])->save();

    expect(function () use ($student, $exam) {
        $secondOngoing = ExamAttempt::create([
            'student_id' => $student->id,
            'exam_id' => $exam->id,
            'duration_minutes' => $exam->duration,
            'started_at' => now(),
        ]);
        $secondOngoing->forceFill(['status' => ExamAttempt::STATUS_ONGOING])->save();
    })->toThrow(\Illuminate\Database\QueryException::class);
});

it('rejects save-answer after group exam deactivation and closes the attempt', function (): void {
    ['student' => $student, 'exam' => $exam, 'group' => $group] = createExamSecurityContext();

    $start = startExamForSecurity($exam->id)->assertCreated();
    $attemptId = $start->json('data.attempt_id');
    $question = $start->json('data.questions.0');

    DB::table('group_exam_activations')
        ->where('exam_id', $exam->id)
        ->where('learning_group_id', $group->id)
        ->delete();

    $this->postJson('/api/exams/save-answer', [
        'attempt_id' => $attemptId,
        'question_id' => $question['id'],
        'choice_id' => $question['choices'][0]['id'],
    ])->assertStatus(422);

    expect(ExamAttempt::find($attemptId)->status)->not->toBe(ExamAttempt::STATUS_ONGOING);
});

it('rejects submit after group exam deactivation', function (): void {
    ['exam' => $exam, 'group' => $group] = createExamSecurityContext();

    $start = startExamForSecurity($exam->id)->assertCreated();
    $attemptId = $start->json('data.attempt_id');

    DB::table('group_exam_activations')
        ->where('exam_id', $exam->id)
        ->where('learning_group_id', $group->id)
        ->delete();

    $this->postJson("/api/exams/{$attemptId}/submit")->assertStatus(422);
});

it('closes an ongoing attempt when the exam becomes inactive during save-answer', function (): void {
    ['exam' => $exam] = createExamSecurityContext();

    $start = startExamForSecurity($exam->id)->assertCreated();
    $attemptId = $start->json('data.attempt_id');
    $question = $start->json('data.questions.0');

    $exam->update(['is_active' => false]);

    $this->postJson('/api/exams/save-answer', [
        'attempt_id' => $attemptId,
        'question_id' => $question['id'],
        'choice_id' => $question['choices'][0]['id'],
    ])->assertStatus(422);

    expect(ExamAttempt::find($attemptId)->status)->not->toBe(ExamAttempt::STATUS_ONGOING);
});

it('rejects moving a question to another exam on update', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    Sanctum::actingAs($admin, ['*']);

    $course = Course::factory()->create([
        'status' => 'published',
        'published_at' => now(),
    ]);

    $examA = Exam::factory()->create(['course_id' => $course->id]);
    $examB = Exam::factory()->create(['course_id' => $course->id]);

    $question = Question::factory()->create([
        'exam_id' => $examA->id,
        'marks' => 5,
    ]);

    Choice::factory()->count(2)->create([
        'question_id' => $question->id,
        'is_correct' => false,
    ]);
    Choice::factory()->create([
        'question_id' => $question->id,
        'is_correct' => true,
    ]);

    $this->putJson("/api/admin/questions/{$question->id}", [
        'exam_id' => $examB->id,
        'type' => 'mcq',
        'question_text' => 'Updated question',
        'marks' => 5,
        'choices' => [
            ['choice_text' => 'A', 'is_correct' => true],
            ['choice_text' => 'B', 'is_correct' => false],
        ],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['exam_id']);

    expect($question->fresh()->exam_id)->toBe($examA->id);
});

it('finalizes concurrent submit requests idempotently', function (): void {
    ['exam' => $exam] = createExamSecurityContext(['questions_per_attempt' => 1]);

    $start = startExamForSecurity($exam->id)->assertCreated();
    $attemptId = $start->json('data.attempt_id');
    $question = $start->json('data.questions.0');
    $correctChoice = collect($question['choices'])->first(
        fn ($choice) => str_starts_with($choice['choice_text'], 'Correct')
    );

    $this->postJson('/api/exams/save-answer', [
        'attempt_id' => $attemptId,
        'question_id' => $question['id'],
        'choice_id' => $correctChoice['id'],
    ])->assertOk();

    $first = $this->postJson("/api/exams/{$attemptId}/submit")->assertOk();
    $second = $this->postJson("/api/exams/{$attemptId}/submit");

    expect($second->status())->toBeIn([200, 422]);
    expect(ExamAttempt::find($attemptId)->status)->not->toBe(ExamAttempt::STATUS_ONGOING);
    expect($first->json('results.status'))->toBe('passed');
});
