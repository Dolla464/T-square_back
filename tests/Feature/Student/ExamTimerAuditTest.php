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
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

const EXAM_TIMER_AUDIT_START = '2026-01-01 10:00:00';
const EXAM_TIMER_AUDIT_DURATION_MINUTES = 10;
const EXAM_TIMER_AUDIT_MAX_SECONDS = EXAM_TIMER_AUDIT_DURATION_MINUTES * 60;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    Role::create(['name' => 'student', 'guard_name' => 'web']);
    Role::create(['name' => 'admin', 'guard_name' => 'web']);
    Carbon::setTestNow(EXAM_TIMER_AUDIT_START);
});

afterEach(function (): void {
    Carbon::setTestNow();
});

function createExamTimerAuditContext(array $examOverrides = [], int $questionCount = 5): array
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
        'duration' => EXAM_TIMER_AUDIT_DURATION_MINUTES,
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

function startExamTimerAudit(int $examId)
{
    return test()->postJson('/api/exams/start', ['exam_id' => $examId]);
}

function getExamTimeStatusAudit(int $attemptId)
{
    return test()->getJson("/api/exams/attempts/{$attemptId}/time-status");
}

function parseExamTimerIso(?string $value): ?Carbon
{
    if ($value === null || $value === '') {
        return null;
    }

    return Carbon::parse($value);
}

function timerAuditDiagnostic(string $context, array $payload): string
{
    return implode("\n", [
        "Timer audit diagnostic [$context]",
        'duration='.json_encode($payload['duration'] ?? null),
        'started_at='.json_encode($payload['started_at'] ?? null),
        'deadline_at='.json_encode($payload['deadline_at'] ?? null),
        'server_time='.json_encode($payload['server_time'] ?? null),
        'remaining_seconds='.json_encode($payload['remaining_seconds'] ?? null),
        'is_timed_out='.json_encode($payload['is_timed_out'] ?? null),
        'status='.json_encode($payload['status'] ?? null),
    ]);
}

function assertServerTimeConsistency(array $payload, string $context): void
{
    $deadlineAt = $payload['deadline_at'] ?? null;
    $serverTime = $payload['server_time'] ?? null;
    $remainingSeconds = $payload['remaining_seconds'] ?? null;

    expect($deadlineAt)->not->toBeNull("$context: deadline_at missing");
    expect($serverTime)->not->toBeNull("$context: server_time missing");
    expect($remainingSeconds)->not->toBeNull("$context: remaining_seconds missing");

    $deadline = parseExamTimerIso($deadlineAt);
    $server = parseExamTimerIso($serverTime);

    $expectedRemaining = max(0, $deadline->getTimestamp() - $server->getTimestamp());

    expect($remainingSeconds)->toBe(
        $expectedRemaining,
        "$context: remaining_seconds ($remainingSeconds) != deadline-server ($expectedRemaining)\n".
        "deadline_at=$deadlineAt\nserver_time=$serverTime"
    );
}

function assertTimedAttemptInvariants(array $payload, string $context, int $durationMinutes = EXAM_TIMER_AUDIT_DURATION_MINUTES): void
{
    $maxSeconds = $durationMinutes * 60;
    $startedAt = parseExamTimerIso($payload['started_at'] ?? null);
    $deadlineAt = parseExamTimerIso($payload['deadline_at'] ?? null);
    $remainingSeconds = $payload['remaining_seconds'];

    expect($startedAt)->not->toBeNull("$context: started_at missing");
    expect($deadlineAt)->not->toBeNull("$context: deadline_at missing");

    $deadlineDelta = $deadlineAt->getTimestamp() - $startedAt->getTimestamp();
    expect($deadlineDelta)->toBe(
        $maxSeconds,
        "$context: deadline_at - started_at ($deadlineDelta) != $maxSeconds"
    );

    expect($remainingSeconds)->toBeGreaterThanOrEqual(0, "$context: negative remaining_seconds");
    expect($remainingSeconds)->toBeLessThanOrEqual(
        $maxSeconds,
        "$context: remaining_seconds ($remainingSeconds) exceeds duration ($maxSeconds)"
    );

    assertServerTimeConsistency($payload, $context);
}

it('starts a 10 minute exam with deterministic timing fields', function (): void {
    ['exam' => $exam] = createExamTimerAuditContext();

    $response = startExamTimerAudit($exam->id);

    $response->assertCreated()
        ->assertJsonPath('data.duration', EXAM_TIMER_AUDIT_DURATION_MINUTES)
        ->assertJsonPath('data.status', ExamAttempt::STATUS_ONGOING)
        ->assertJsonPath('data.is_timed_out', false)
        ->assertJsonPath('data.remaining_seconds', EXAM_TIMER_AUDIT_MAX_SECONDS)
        ->assertJsonPath('data.started_at', Carbon::parse(EXAM_TIMER_AUDIT_START)->toIso8601String());

    assertTimedAttemptInvariants($response->json('data'), 'start-10m');
});

it('does not return more than 600 remaining seconds for a 10 minute exam at start', function (): void {
    ['exam' => $exam] = createExamTimerAuditContext();

    $payload = startExamTimerAudit($exam->id)->json('data');

    expect($payload['remaining_seconds'])->toBeLessThanOrEqual(
        EXAM_TIMER_AUDIT_MAX_SECONDS,
        timerAuditDiagnostic('backend-start-regression', $payload)
    );
    expect($payload['remaining_seconds'])->not->toBe(4361)
        ->and($payload['remaining_seconds'])->not->toBe(4362);
});

it('never returns negative remaining_seconds for an ongoing timed attempt', function (): void {
    ['exam' => $exam] = createExamTimerAuditContext();

    $attemptId = startExamTimerAudit($exam->id)->json('data.attempt_id');

    foreach ([0, 1, 30, 60, 300, 540, 599] as $offsetSeconds) {
        Carbon::setTestNow(Carbon::parse(EXAM_TIMER_AUDIT_START)->addSeconds($offsetSeconds));

        $statusPayload = getExamTimeStatusAudit($attemptId)->json('data');

        expect($statusPayload['remaining_seconds'])->toBeGreaterThanOrEqual(
            0,
            timerAuditDiagnostic("negative-check-offset-{$offsetSeconds}", $statusPayload)
        );
    }
});

it('resumes immediately without resetting started_at deadline or remaining time', function (): void {
    ['exam' => $exam] = createExamTimerAuditContext();

    $first = startExamTimerAudit($exam->id)->assertCreated()->json('data');
    Carbon::setTestNow(Carbon::parse(EXAM_TIMER_AUDIT_START)->addSeconds(15));

    $second = startExamTimerAudit($exam->id)->assertOk()->json('data');

    expect($second['attempt_id'])->toBe($first['attempt_id']);
    expect($second['started_at'])->toBe($first['started_at']);
    expect($second['deadline_at'])->toBe($first['deadline_at']);
    expect($second['remaining_seconds'])->toBe(585);
    expect($second['remaining_seconds'])->not->toBe(EXAM_TIMER_AUDIT_MAX_SECONDS);
});

it('resumes after 2 minutes with approximately 480 seconds remaining', function (): void {
    ['exam' => $exam] = createExamTimerAuditContext();

    startExamTimerAudit($exam->id)->assertCreated();
    Carbon::setTestNow(Carbon::parse(EXAM_TIMER_AUDIT_START)->addMinutes(2));

    $resumePayload = startExamTimerAudit($exam->id)->assertOk()->json('data');

    expect($resumePayload['remaining_seconds'])->toBe(480);
    expect($resumePayload['remaining_seconds'])->not->toBe(EXAM_TIMER_AUDIT_MAX_SECONDS);
    expect($resumePayload['remaining_seconds'])->toBeLessThanOrEqual(EXAM_TIMER_AUDIT_MAX_SECONDS);
});

it('keeps frozen duration_minutes after the exam duration is changed in the database', function (): void {
    ['exam' => $exam] = createExamTimerAuditContext();

    $startPayload = startExamTimerAudit($exam->id)->json('data');
    $attemptId = $startPayload['attempt_id'];

    $exam->update(['duration' => 90]);

    $statusPayload = getExamTimeStatusAudit($attemptId)->json('data');

    expect(ExamAttempt::find($attemptId)->duration_minutes)->toBe(EXAM_TIMER_AUDIT_DURATION_MINUTES);
    expect($statusPayload['deadline_at'])->toBe($startPayload['deadline_at']);
    expect($statusPayload['remaining_seconds'])->toBe(EXAM_TIMER_AUDIT_MAX_SECONDS);
    expect($statusPayload['remaining_seconds'])->toBeLessThanOrEqual(EXAM_TIMER_AUDIT_MAX_SECONDS);
    assertServerTimeConsistency($statusPayload, 'duration-freeze-time-status');
});

it('tracks remaining seconds at deterministic boundary offsets for a 10 minute exam', function (int $offsetSeconds, int $expectedRemaining, ?string $expectedStatus) {
    ['exam' => $exam] = createExamTimerAuditContext();

    $attemptId = startExamTimerAudit($exam->id)->json('data.attempt_id');

    Carbon::setTestNow(Carbon::parse(EXAM_TIMER_AUDIT_START)->addSeconds($offsetSeconds));

    $statusPayload = getExamTimeStatusAudit($attemptId)->json('data');

    expect($statusPayload['remaining_seconds'])->toBe(
        $expectedRemaining,
        timerAuditDiagnostic("boundary-offset-{$offsetSeconds}", $statusPayload)
    );

    if ($expectedStatus !== null) {
        expect($statusPayload['status'])->toBe($expectedStatus);
    }

    expect($statusPayload['remaining_seconds'])->toBeLessThanOrEqual(EXAM_TIMER_AUDIT_MAX_SECONDS);
})->with([
    'at start' => [0, 600, 'ongoing'],
    'plus 1 second' => [1, 599, 'ongoing'],
    'plus 30 seconds' => [30, 570, 'ongoing'],
    'plus 1 minute' => [60, 540, 'ongoing'],
    'plus 5 minutes' => [300, 300, 'ongoing'],
    'plus 9 minutes' => [540, 60, 'ongoing'],
    'plus 9 minutes 59 seconds' => [599, 1, 'ongoing'],
    'exactly plus 10 minutes' => [600, 0, 'timed_out'],
    'plus 10 minutes 1 second' => [601, 0, 'timed_out'],
    'plus 15 minutes' => [900, 0, 'timed_out'],
]);

it('marks timed out attempts with zero remaining seconds after the deadline', function (): void {
    ['exam' => $exam] = createExamTimerAuditContext();

    $attemptId = startExamTimerAudit($exam->id)->json('data.attempt_id');

    Carbon::setTestNow(Carbon::parse(EXAM_TIMER_AUDIT_START)->addMinutes(10));

    $statusPayload = getExamTimeStatusAudit($attemptId)->json('data');

    expect($statusPayload['remaining_seconds'])->toBe(0);
    expect($statusPayload['is_timed_out'])->toBeTrue();
    expect($statusPayload['status'])->toBe('timed_out');
});

it('does not reset timing when start is called twice on the same ongoing attempt', function (): void {
    ['student' => $student, 'exam' => $exam] = createExamTimerAuditContext();

    $first = startExamTimerAudit($exam->id)->assertCreated()->json('data');
    Carbon::setTestNow(Carbon::parse(EXAM_TIMER_AUDIT_START)->addMinute());

    $second = startExamTimerAudit($exam->id)->assertOk()->json('data');

    expect($second['attempt_id'])->toBe($first['attempt_id']);
    expect($second['started_at'])->toBe($first['started_at']);
    expect($second['deadline_at'])->toBe($first['deadline_at']);
    expect($second['remaining_seconds'])->toBe(540);

    expect(
        ExamAttempt::query()
            ->where('student_id', $student->id)
            ->where('exam_id', $exam->id)
            ->where('status', ExamAttempt::STATUS_ONGOING)
            ->count()
    )->toBe(1);
});

it('does not generate a timed countdown for an untimed exam', function (): void {
    ['exam' => $exam] = createExamTimerAuditContext(['duration' => 0]);

    $payload = startExamTimerAudit($exam->id)->json('data');

    expect($payload['duration'])->toBe(0);
    expect($payload['deadline_at'])->toBeNull();
    expect($payload['remaining_seconds'])->toBeNull();
    expect($payload['is_timed_out'])->toBeFalse();
    expect($payload['status'])->toBe(ExamAttempt::STATUS_ONGOING);
});
