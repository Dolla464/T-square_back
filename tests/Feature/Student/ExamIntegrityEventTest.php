<?php

use App\Models\Choice;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\ExamAttemptIntegrityEvent;
use App\Models\LearningGroup;
use App\Models\Order;
use App\Models\Question;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    Role::create(['name' => 'student', 'guard_name' => 'web']);
    Role::create(['name' => 'admin', 'guard_name' => 'web']);
});

function createIntegrityExamContext(array $examOverrides = []): array
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
        'duration' => 30,
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

function startIntegrityExam(int $examId)
{
    return test()->postJson('/api/exams/start', ['exam_id' => $examId]);
}

function integrityPayload(array $events): array
{
    return ['events' => $events];
}

function integrityEvent(string $type, ?string $eventId = null, ?string $clientAt = null, array $metadata = []): array
{
    return array_filter([
        'event_id' => $eventId ?? (string) Str::uuid(),
        'type' => $type,
        'client_at' => $clientAt,
        'metadata' => $metadata ?: null,
    ], fn ($value) => $value !== null);
}

function postIntegrityEvents(int $attemptId, array $payload)
{
    return test()->postJson("/api/exams/attempts/{$attemptId}/integrity-events", $payload);
}

it('records integrity events for an ongoing attempt before deadline', function (): void {
    ['exam' => $exam] = createIntegrityExamContext();

    $attemptId = startIntegrityExam($exam->id)->assertCreated()->json('data.attempt_id');

    $response = postIntegrityEvents($attemptId, integrityPayload([
        integrityEvent('tab_hidden', '11111111-1111-4111-8111-111111111111'),
        integrityEvent('tab_visible', '22222222-2222-4222-8222-222222222222', null, [
            'hidden_duration_seconds' => 12,
        ]),
    ]));

    $response->assertCreated()
        ->assertJsonPath('data.recorded', 2);

    expect(ExamAttemptIntegrityEvent::where('exam_attempt_id', $attemptId)->count())->toBe(2);
});

it('returns 403 when another student tries to record events', function (): void {
    ['student' => $studentA, 'exam' => $exam] = createIntegrityExamContext();

    $attemptId = startIntegrityExam($exam->id)->assertCreated()->json('data.attempt_id');

    $studentB = Student::factory()->create();
    $studentB->user->assignRole('student');
    Sanctum::actingAs($studentB->user, ['*']);

    postIntegrityEvents($attemptId, integrityPayload([
        integrityEvent('tab_hidden'),
    ]))->assertForbidden();

    expect(ExamAttemptIntegrityEvent::count())->toBe(0);
});

it('returns 422 when attempt is not ongoing', function (): void {
    ['student' => $student, 'exam' => $exam] = createIntegrityExamContext();

    $attemptId = startIntegrityExam($exam->id)->assertCreated()->json('data.attempt_id');

    ExamAttempt::query()->whereKey($attemptId)->update([
        'status' => 'passed',
        'finished_at' => now(),
    ]);

    postIntegrityEvents($attemptId, integrityPayload([
        integrityEvent('tab_hidden'),
    ]))->assertStatus(422);

    expect(ExamAttemptIntegrityEvent::count())->toBe(0);
});

it('returns 422 when attempt deadline has expired', function (): void {
    ['student' => $student, 'exam' => $exam] = createIntegrityExamContext(['duration' => 5]);

    $attemptId = startIntegrityExam($exam->id)->assertCreated()->json('data.attempt_id');

    ExamAttempt::query()->whereKey($attemptId)->update([
        'started_at' => now()->subMinutes(10),
    ]);

    postIntegrityEvents($attemptId, integrityPayload([
        integrityEvent('tab_hidden'),
    ]))->assertStatus(422);

    expect(ExamAttemptIntegrityEvent::count())->toBe(0);
});

it('is idempotent when the same event_id is sent twice', function (): void {
    ['exam' => $exam] = createIntegrityExamContext();

    $attemptId = startIntegrityExam($exam->id)->assertCreated()->json('data.attempt_id');
    $eventId = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';
    $payload = integrityPayload([
        integrityEvent('tab_hidden', $eventId),
    ]);

    postIntegrityEvents($attemptId, $payload)->assertCreated()->assertJsonPath('data.recorded', 1);
    postIntegrityEvents($attemptId, $payload)->assertCreated()->assertJsonPath('data.recorded', 0);

    expect(ExamAttemptIntegrityEvent::where('exam_attempt_id', $attemptId)->count())->toBe(1);
});

it('rejects more than twenty events in one request', function (): void {
    ['exam' => $exam] = createIntegrityExamContext();

    $attemptId = startIntegrityExam($exam->id)->assertCreated()->json('data.attempt_id');

    $events = [];
    for ($i = 0; $i < 21; $i++) {
        $events[] = integrityEvent('window_blur');
    }

    postIntegrityEvents($attemptId, integrityPayload($events))->assertStatus(422);
});

it('rejects invalid event types', function (): void {
    ['exam' => $exam] = createIntegrityExamContext();

    $attemptId = startIntegrityExam($exam->id)->assertCreated()->json('data.attempt_id');

    postIntegrityEvents($attemptId, integrityPayload([
        [
            'event_id' => (string) Str::uuid(),
            'type' => 'cheating_detected',
        ],
    ]))->assertStatus(422);
});

it('rejects missing event_id', function (): void {
    ['exam' => $exam] = createIntegrityExamContext();

    $attemptId = startIntegrityExam($exam->id)->assertCreated()->json('data.attempt_id');

    postIntegrityEvents($attemptId, integrityPayload([
        ['type' => 'tab_hidden'],
    ]))->assertStatus(422);
});

it('nullifies unreasonably future client_at values', function (): void {
    ['exam' => $exam] = createIntegrityExamContext();

    $attemptId = startIntegrityExam($exam->id)->assertCreated()->json('data.attempt_id');

    postIntegrityEvents($attemptId, integrityPayload([
        integrityEvent('tab_hidden', 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb', now()->addHour()->toIso8601String()),
    ]))->assertCreated();

    expect(ExamAttemptIntegrityEvent::first()->client_at)->toBeNull();
});

it('rejects client supplied occurred_at', function (): void {
    ['exam' => $exam] = createIntegrityExamContext();

    $attemptId = startIntegrityExam($exam->id)->assertCreated()->json('data.attempt_id');

    postIntegrityEvents($attemptId, [
        'events' => [[
            'event_id' => (string) Str::uuid(),
            'type' => 'tab_hidden',
            'occurred_at' => now()->toIso8601String(),
        ]],
    ])->assertStatus(422);
});

it('stores two real tab_hidden events close together without debounce drop', function (): void {
    ['exam' => $exam] = createIntegrityExamContext();

    $attemptId = startIntegrityExam($exam->id)->assertCreated()->json('data.attempt_id');

    postIntegrityEvents($attemptId, integrityPayload([
        integrityEvent('tab_hidden', 'cccccccc-cccc-4ccc-8ccc-cccccccccccc'),
        integrityEvent('tab_hidden', 'dddddddd-dddd-4ddd-8ddd-dddddddddddd'),
    ]))->assertCreated()->assertJsonPath('data.recorded', 2);

    expect(ExamAttemptIntegrityEvent::where('event_type', 'tab_hidden')->count())->toBe(2);
});

it('exposes integrity data to staff review but not student review', function (): void {
    ['student' => $student, 'group' => $group, 'exam' => $exam] = createIntegrityExamContext();

    $attemptId = startIntegrityExam($exam->id)->assertCreated()->json('data.attempt_id');

    postIntegrityEvents($attemptId, integrityPayload([
        integrityEvent('tab_hidden', 'eeeeeeee-eeee-4eee-8eee-eeeeeeeeeeee'),
    ]))->assertCreated();

    $this->postJson("/api/exams/{$attemptId}/submit")->assertOk();

    $studentReview = $this->getJson("/api/exams/attempts/{$attemptId}/review");
    $studentReview->assertOk()
        ->assertJsonMissingPath('data.integrity_events')
        ->assertJsonMissingPath('data.integrity_summary');

    $admin = User::factory()->create();
    $admin->assignRole('admin');
    Sanctum::actingAs($admin, ['*']);

    $staffReview = $this->getJson(
        "/api/admin/learning-groups/{$group->id}/students/{$student->id}/exam-attempts/{$attemptId}/review"
    );

    $staffReview->assertOk()
        ->assertJsonPath('data.integrity_summary.tab_hidden_count', 1)
        ->assertJsonCount(1, 'data.integrity_events');
});

it('deletes integrity events when the attempt is deleted', function (): void {
    ['exam' => $exam] = createIntegrityExamContext();

    $attemptId = startIntegrityExam($exam->id)->assertCreated()->json('data.attempt_id');

    postIntegrityEvents($attemptId, integrityPayload([
        integrityEvent('tab_hidden'),
    ]))->assertCreated();

    ExamAttempt::query()->whereKey($attemptId)->delete();

    expect(ExamAttemptIntegrityEvent::count())->toBe(0);
});

it('rate limits integrity event recording', function (): void {
    ['exam' => $exam] = createIntegrityExamContext();

    $attemptId = startIntegrityExam($exam->id)->assertCreated()->json('data.attempt_id');

    for ($i = 0; $i < 60; $i++) {
        postIntegrityEvents($attemptId, integrityPayload([
            integrityEvent('window_blur', (string) Str::uuid()),
        ]))->assertCreated();
    }

    postIntegrityEvents($attemptId, integrityPayload([
        integrityEvent('window_blur'),
    ]))->assertStatus(429);
});
