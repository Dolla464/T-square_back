<?php

use App\Models\Choice;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\ExamAttempt;
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
});

function createStudentExamContext(array $examOverrides = [], int $questionCount = 5): array
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
        'questions_per_attempt' => 3,
        'total_marks' => 100,
        'passing_mark' => 60,
        'max_attempts' => 3,
        'shuffle_questions' => false,
    ], $examOverrides));

    DB::table('group_exam_activations')->insert([
        'exam_id' => $exam->id,
        'learning_group_id' => $group->id,
        'activated_at' => now(),
    ]);

    for ($i = 0; $i < $questionCount; $i++) {
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

    return compact('student', 'course', 'exam', 'group');
}

function startExam(int $examId)
{
    return test()->postJson('/api/exams/start', ['exam_id' => $examId]);
}

it('samples questions_per_attempt from the bank when starting an attempt', function (): void {
    ['exam' => $exam] = createStudentExamContext(['questions_per_attempt' => 3], 5);

    $response = startExam($exam->id);

    $response->assertCreated()
        ->assertJsonPath('data.total_questions', 3)
        ->assertJsonPath('data.attempt_max_marks', 30)
        ->assertJsonPath('data.attempt_passing_mark', 18);
});

it('caps sampled questions at bank size when questions_per_attempt exceeds the bank', function (): void {
    ['exam' => $exam] = createStudentExamContext(['questions_per_attempt' => 10], 4);

    $response = startExam($exam->id);

    $response->assertCreated()
        ->assertJsonPath('data.total_questions', 4)
        ->assertJsonPath('data.attempt_max_marks', 40);
});

it('passes an attempt using the scaled passing mark for the sampled subset', function (): void {
    ['exam' => $exam] = createStudentExamContext(['questions_per_attempt' => 3], 5);

    $startResponse = startExam($exam->id);
    $attemptId = $startResponse->json('data.attempt_id');
    $questions = $startResponse->json('data.questions');

    foreach ($questions as $question) {
        $correctChoice = collect($question['choices'])->first(
            fn ($choice) => str_starts_with($choice['choice_text'], 'Correct')
        );

        $this->postJson('/api/exams/save-answer', [
            'attempt_id' => $attemptId,
            'question_id' => $question['id'],
            'choice_id' => $correctChoice['id'],
        ])->assertOk();
    }

    $submitResponse = $this->postJson("/api/exams/{$attemptId}/submit");

    $submitResponse->assertOk()
        ->assertJsonPath('results.score', 30)
        ->assertJsonPath('results.total_marks', 30)
        ->assertJsonPath('results.is_passed', true)
        ->assertJsonPath('results.status', 'passed');
});

it('exposes has_ongoing_attempt while a student has an active attempt', function (): void {
    ['exam' => $exam] = createStudentExamContext();

    startExam($exam->id)->assertCreated();

    $this->getJson('/api/exams')
        ->assertOk()
        ->assertJsonPath('data.0.has_ongoing_attempt', true)
        ->assertJsonPath('data.0.is_locked', false);
});

it('rejects questions_per_attempt greater than the question bank on exam update', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    Sanctum::actingAs($admin, ['*']);

    $course = Course::factory()->create([
        'status' => 'published',
        'published_at' => now(),
    ]);

    $exam = Exam::factory()->create([
        'course_id' => $course->id,
        'questions_per_attempt' => 2,
    ]);

    Question::factory()->count(2)->create(['exam_id' => $exam->id, 'marks' => 5]);

    $this->putJson("/api/admin/exams/{$exam->id}", [
        'course_id' => $course->id,
        'title' => $exam->title,
        'description' => $exam->description,
        'duration' => 60,
        'total_marks' => 100,
        'passing_mark' => 60,
        'is_active' => true,
        'is_final' => false,
        'max_attempts' => 2,
        'questions_per_attempt' => 5,
        'shuffle_questions' => false,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['questions_per_attempt']);
});

it('keeps attempt question order stable across resume requests', function (): void {
    ['exam' => $exam] = createStudentExamContext(['questions_per_attempt' => 3], 5);

    $firstStart = startExam($exam->id);
    $firstQuestionIds = collect($firstStart->json('data.questions'))->pluck('id')->all();

    $secondStart = startExam($exam->id);
    $secondQuestionIds = collect($secondStart->json('data.questions'))->pluck('id')->all();

    expect($secondQuestionIds)->toBe($firstQuestionIds);
});

it('auto-completes a timed-out ongoing attempt when resuming via start', function (): void {
    ['exam' => $exam] = createStudentExamContext(['duration' => 30], 5);

    $startResponse = startExam($exam->id);
    $attemptId = $startResponse->json('data.attempt_id');

    ExamAttempt::whereKey($attemptId)->update([
        'started_at' => now()->subMinutes(31),
    ]);

    $resumeResponse = startExam($exam->id);

    $resumeResponse->assertOk()
        ->assertJsonPath('data.status', 'timed_out')
        ->assertJsonPath('data.results.score', 0)
        ->assertJsonPath('data.results.status', 'timed_out')
        ->assertJsonStructure([
            'data' => [
                'results' => [
                    'score',
                    'total_marks',
                    'percentage',
                    'status',
                    'is_passed',
                ],
            ],
        ]);

    expect(ExamAttempt::find($attemptId)->status)->toBe('timed_out');
});
