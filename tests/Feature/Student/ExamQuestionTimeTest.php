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

function createQuestionTimeContext(int $questionCount = 3): array
{
    $course = Course::factory()->create([
        'status' => 'published',
        'published_at' => now(),
    ]);

    $student = Student::factory()->create();
    $student->user->assignRole('student');

    $otherStudent = Student::factory()->create();
    $otherStudent->user->assignRole('student');

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
        'questions_per_attempt' => $questionCount,
        'total_marks' => 100,
        'passing_mark' => 60,
        'max_attempts' => 3,
        'shuffle_questions' => false,
    ]);

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

        Choice::factory()->count(2)->create([
            'question_id' => $question->id,
            'is_correct' => false,
        ]);
    }

    Sanctum::actingAs($student->user, ['*']);

    $startResponse = test()->postJson('/api/exams/start', ['exam_id' => $exam->id]);
    $attemptId = $startResponse->json('data.attempt_id');
    $questions = $startResponse->json('data.questions');

    $admin = User::factory()->create();
    $admin->assignRole('admin');

    return compact(
        'student',
        'otherStudent',
        'group',
        'exam',
        'attemptId',
        'questions',
        'admin',
    );
}

function recordQuestionTime(int $attemptId, int $questionId, int $seconds)
{
    return test()->postJson('/api/exams/record-question-time', [
        'attempt_id' => $attemptId,
        'question_id' => $questionId,
        'time_spent_seconds' => $seconds,
    ]);
}

function pivotTime(int $attemptId, int $questionId): ?int
{
    $value = DB::table('attempt_questions')
        ->where('exam_attempt_id', $attemptId)
        ->where('question_id', $questionId)
        ->value('time_spent_seconds');

    return $value === null ? null : (int) $value;
}

it('records question timing on the attempt pivot', function (): void {
    ['attemptId' => $attemptId, 'questions' => $questions] = createQuestionTimeContext();
    $questionId = $questions[0]['id'];

    recordQuestionTime($attemptId, $questionId, 12)->assertOk();

    expect(pivotTime($attemptId, $questionId))->toBe(12);
});

it('accumulates timing for the same question across visits', function (): void {
    ['attemptId' => $attemptId, 'questions' => $questions] = createQuestionTimeContext();
    $questionId = $questions[0]['id'];

    recordQuestionTime($attemptId, $questionId, 12)->assertOk();
    recordQuestionTime($attemptId, $questionId, 8)->assertOk();

    expect(pivotTime($attemptId, $questionId))->toBe(20);
});

it('records timing independently for different questions', function (): void {
    ['attemptId' => $attemptId, 'questions' => $questions] = createQuestionTimeContext();
    $questionOneId = $questions[0]['id'];
    $questionTwoId = $questions[1]['id'];

    recordQuestionTime($attemptId, $questionOneId, 12)->assertOk();
    recordQuestionTime($attemptId, $questionTwoId, 8)->assertOk();

    expect(pivotTime($attemptId, $questionOneId))->toBe(12)
        ->and(pivotTime($attemptId, $questionTwoId))->toBe(8);
});

it('exposes recorded timing in the staff review payload', function (): void {
    [
        'student' => $student,
        'group' => $group,
        'attemptId' => $attemptId,
        'questions' => $questions,
        'admin' => $admin,
    ] = createQuestionTimeContext();

    $questionOneId = $questions[0]['id'];
    $questionTwoId = $questions[1]['id'];

    recordQuestionTime($attemptId, $questionOneId, 12)->assertOk();
    recordQuestionTime($attemptId, $questionTwoId, 8)->assertOk();

    $attempt = ExamAttempt::findOrFail($attemptId);
    $attempt->forceFill([
        'status' => 'passed',
        'score' => 20,
        'finished_at' => now(),
    ])->save();

    Sanctum::actingAs($admin, ['*']);

    $response = test()->getJson(
        "/api/admin/learning-groups/{$group->id}/students/{$student->id}/exam-attempts/{$attemptId}/review"
    );

    $response->assertOk()
        ->assertJsonPath('data.questions.0.time_spent_seconds', 12)
        ->assertJsonPath('data.questions.1.time_spent_seconds', 8);
});

it('forbids recording timing for another students attempt', function (): void {
    ['otherStudent' => $otherStudent, 'attemptId' => $attemptId, 'questions' => $questions] = createQuestionTimeContext();

    Sanctum::actingAs($otherStudent->user, ['*']);

    recordQuestionTime($attemptId, $questions[0]['id'], 12)->assertForbidden();
});

it('rejects timing for a question that does not belong to the attempt', function (): void {
    ['attemptId' => $attemptId, 'exam' => $exam] = createQuestionTimeContext(2);

    $foreignQuestion = Question::factory()->create([
        'exam_id' => $exam->id,
        'marks' => 10,
    ]);

    recordQuestionTime($attemptId, $foreignQuestion->id, 12)->assertForbidden();
});

it('rejects timing on a closed attempt', function (): void {
    ['attemptId' => $attemptId, 'questions' => $questions] = createQuestionTimeContext();

    $attempt = ExamAttempt::findOrFail($attemptId);
    $attempt->forceFill([
        'status' => 'passed',
        'score' => 10,
        'finished_at' => now(),
    ])->save();

    recordQuestionTime($attemptId, $questions[0]['id'], 12)->assertForbidden();
});

it('returns null timing for old attempts without recorded values', function (): void {
    [
        'student' => $student,
        'group' => $group,
        'exam' => $exam,
        'admin' => $admin,
    ] = createQuestionTimeContext();

    $attempt = ExamAttempt::create([
        'student_id' => $student->id,
        'exam_id' => $exam->id,
        'started_at' => now()->subMinutes(30),
        'finished_at' => now(),
        'status' => 'passed',
        'score' => 10,
    ]);

    $questions = Question::where('exam_id', $exam->id)->take(2)->get();
    $sync = [];
    foreach ($questions as $index => $question) {
        $sync[$question->id] = ['sort_order' => $index + 1];
    }
    $attempt->questions()->sync($sync);

    Sanctum::actingAs($admin, ['*']);

    $response = test()->getJson(
        "/api/admin/learning-groups/{$group->id}/students/{$student->id}/exam-attempts/{$attempt->id}/review"
    );

    $response->assertOk()
        ->assertJsonPath('data.questions.0.time_spent_seconds', null)
        ->assertJsonPath('data.questions.1.time_spent_seconds', null);
});
