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

function createGroupExamResultsContext(): array
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

    $exam = Exam::factory()->create([
        'course_id' => $course->id,
        'questions_per_attempt' => 3,
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

    $questions = collect();
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

        $questions->push($question);
    }

    $attempt = ExamAttempt::create([
        'student_id' => $student->id,
        'exam_id' => $exam->id,
        'started_at' => now()->subMinutes(30),
        'finished_at' => now(),
    ]);
    $attempt->forceFill([
        'status' => 'passed',
        'score' => 25,
    ])->save();

    $sync = [];
    foreach ($questions->take(3)->values() as $index => $question) {
        $sync[$question->id] = ['sort_order' => $index + 1];
    }
    $attempt->questions()->sync($sync);

    $admin = User::factory()->create();
    $admin->assignRole('admin');
    Sanctum::actingAs($admin, ['*']);

    return compact('student', 'group', 'exam', 'attempt', 'admin');
}

it('marks a subset-passing student as passed in the group summary', function (): void {
    ['student' => $student, 'group' => $group, 'exam' => $exam] = createGroupExamResultsContext();

    $response = $this->getJson("/api/admin/learning-groups/{$group->id}/exams/{$exam->id}/results");

    $response->assertOk()
        ->assertJsonPath('data.students.0.student_id', $student->id)
        ->assertJsonPath('data.students.0.is_passed', true)
        ->assertJsonPath('data.students.0.highest_score', 25)
        ->assertJsonPath('data.students.0.highest_attempt_max_marks', 30)
        ->assertJsonPath('data.questions_per_attempt', 3);
});

it('returns attempt-scaled totals in the admin review payload', function (): void {
    ['student' => $student, 'group' => $group, 'attempt' => $attempt] = createGroupExamResultsContext();

    $response = $this->getJson(
        "/api/admin/learning-groups/{$group->id}/students/{$student->id}/exam-attempts/{$attempt->id}/review"
    );

    $response->assertOk()
        ->assertJsonPath('data.attempt_max_marks', 30)
        ->assertJsonPath('data.total_marks', 30)
        ->assertJsonPath('data.attempt_passing_mark', 18)
        ->assertJsonPath('data.exam_total_marks', 100)
        ->assertJsonCount(3, 'data.questions');
});

it('exports csv scores using attempt max marks as the denominator', function (): void {
    ['group' => $group, 'exam' => $exam] = createGroupExamResultsContext();

    $response = $this->getJson(
        "/api/admin/learning-groups/{$group->id}/exams/{$exam->id}/results/export?format=excel"
    );

    $response->assertOk();

    $content = base64_decode($response->json('data.content'), true);
    expect($content)->toContain('25 / 30')
        ->and($content)->toContain('Passed');
});
