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
use App\Support\StudentExamJourneyLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    Role::create(['name' => 'student', 'guard_name' => 'web']);

    $path = storage_path('logs/test-student-exam-journey-feature.log');

    if (is_file($path)) {
        unlink($path);
    }

    Config::set('logging.channels.student_exam', [
        'driver' => 'single',
        'path' => $path,
        'level' => 'debug',
        'replace_placeholders' => true,
    ]);

    Config::set('exam_journey.enabled', true);
    Config::set('exam_journey.client_events_enabled', true);
});

function journeyFeatureLogContents(): string
{
    $path = storage_path('logs/test-student-exam-journey-feature.log');

    return is_file($path) ? (string) file_get_contents($path) : '';
}

function createJourneyExamContext(): array
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
        'questions_per_attempt' => 1,
        'duration' => 30,
        'max_attempts' => 3,
        'shuffle_questions' => false,
    ]);

    DB::table('group_exam_activations')->insert([
        'exam_id' => $exam->id,
        'learning_group_id' => $group->id,
        'activated_at' => now(),
    ]);

    $question = Question::factory()->create([
        'exam_id' => $exam->id,
        'marks' => 10,
    ]);

    Choice::factory()->create([
        'question_id' => $question->id,
        'is_correct' => true,
    ]);

    Choice::factory()->count(2)->create([
        'question_id' => $question->id,
        'is_correct' => false,
    ]);

    Sanctum::actingAs($student->user, ['*']);

    return compact('student', 'exam', 'question');
}

it('logs attempt started and sets correlation header on start', function (): void {
    ['exam' => $exam] = createJourneyExamContext();

    $response = test()->postJson('/api/exams/start', ['exam_id' => $exam->id]);

    $response->assertCreated();
    expect($response->headers->get('X-Exam-Journey-Correlation-Id'))->not->toBeEmpty();
    expect(journeyFeatureLogContents())->toContain(StudentExamJourneyLogger::EVENT_ATTEMPT_STARTED);
});

it('logs access denied once when start is blocked', function (): void {
    ['exam' => $exam, 'student' => $student] = createJourneyExamContext();

    Enrollment::query()
        ->where('student_id', $student->id)
        ->where('course_id', $exam->course_id)
        ->delete();

    test()->postJson('/api/exams/start', ['exam_id' => $exam->id])
        ->assertForbidden();

    $log = journeyFeatureLogContents();

    expect($log)->toContain(StudentExamJourneyLogger::EVENT_ACCESS_DENIED)
        ->and(substr_count($log, StudentExamJourneyLogger::EVENT_ACCESS_DENIED))->toBe(1);
});

it('accepts client journey events for owned attempt', function (): void {
    ['exam' => $exam] = createJourneyExamContext();

    $attemptId = test()->postJson('/api/exams/start', ['exam_id' => $exam->id])
        ->assertCreated()
        ->json('data.attempt_id');

    test()->postJson("/api/exams/attempts/{$attemptId}/journey-events", [
        'events' => [
            [
                'event_id' => (string) Str::uuid(),
                'type' => 'PAGE_MOUNT',
                'client_at' => now()->toIso8601String(),
            ],
        ],
    ])->assertCreated();

    expect(journeyFeatureLogContents())->toContain(StudentExamJourneyLogger::EVENT_CLIENT_EVENTS_BATCH);
});

it('logs attempt completed on submit', function (): void {
    ['exam' => $exam, 'question' => $question] = createJourneyExamContext();

    $start = test()->postJson('/api/exams/start', ['exam_id' => $exam->id])->assertCreated();
    $attemptId = $start->json('data.attempt_id');
    $choiceId = $start->json('data.questions.0.choices.0.id');

    test()->postJson('/api/exams/save-answer', [
        'attempt_id' => $attemptId,
        'question_id' => $question->id,
        'choice_id' => $choiceId,
    ])->assertOk();

    $path = storage_path('logs/test-student-exam-journey-feature.log');
    if (is_file($path)) {
        unlink($path);
    }

    test()->postJson("/api/exams/{$attemptId}/submit")->assertOk();

    expect(journeyFeatureLogContents())->toContain(StudentExamJourneyLogger::EVENT_ATTEMPT_COMPLETED)
        ->and(journeyFeatureLogContents())->toContain('student_submit');

    expect(ExamAttempt::find($attemptId)?->status)->not->toBe(ExamAttempt::STATUS_ONGOING);
});

it('runs full exam flow with journey logging disabled and no audit file', function (): void {
    Config::set('exam_journey.enabled', false);

    ['exam' => $exam, 'question' => $question] = createJourneyExamContext();

    $start = test()->postJson('/api/exams/start', ['exam_id' => $exam->id])->assertCreated();
    $attemptId = $start->json('data.attempt_id');
    $choiceId = $start->json('data.questions.0.choices.0.id');

    test()->postJson('/api/exams/save-answer', [
        'attempt_id' => $attemptId,
        'question_id' => $question->id,
        'choice_id' => $choiceId,
    ])->assertOk();

    test()->postJson("/api/exams/{$attemptId}/submit")->assertOk();

    expect(journeyFeatureLogContents())->toBe('');
    expect(ExamAttempt::find($attemptId)?->status)->not->toBe(ExamAttempt::STATUS_ONGOING);
});

it('still starts exam when student_exam log channel cannot write', function (): void {
    Config::set('exam_journey.enabled', true);
    Config::set('logging.channels.student_exam', [
        'driver' => 'single',
        'path' => 'Z:\\invalid\\student-exam-journey-unwritable.log',
        'level' => 'debug',
        'replace_placeholders' => true,
    ]);

    ['exam' => $exam] = createJourneyExamContext();

    test()->postJson('/api/exams/start', ['exam_id' => $exam->id])->assertCreated();
});

it('does not persist client events for another student attempt', function (): void {
    ['exam' => $exam] = createJourneyExamContext();

    $attemptId = test()->postJson('/api/exams/start', ['exam_id' => $exam->id])
        ->assertCreated()
        ->json('data.attempt_id');

    $other = Student::factory()->create();
    $other->user->assignRole('student');
    Sanctum::actingAs($other->user, ['*']);

    $path = storage_path('logs/test-student-exam-journey-feature.log');
    if (is_file($path)) {
        unlink($path);
    }

    test()->postJson("/api/exams/attempts/{$attemptId}/journey-events", [
        'events' => [
            [
                'event_id' => (string) Str::uuid(),
                'type' => 'PAGE_MOUNT',
            ],
        ],
    ])->assertCreated()
        ->assertJsonPath('data.recorded', 0);

    expect(journeyFeatureLogContents())->not->toContain(StudentExamJourneyLogger::EVENT_CLIENT_EVENTS_BATCH);
});

it('rejects invalid journey event types and oversized batches', function (): void {
    ['exam' => $exam] = createJourneyExamContext();

    $attemptId = test()->postJson('/api/exams/start', ['exam_id' => $exam->id])
        ->assertCreated()
        ->json('data.attempt_id');

    test()->postJson("/api/exams/attempts/{$attemptId}/journey-events", [
        'events' => [
            [
                'event_id' => (string) Str::uuid(),
                'type' => 'TAB_HIDDEN',
            ],
        ],
    ])->assertUnprocessable();

    $tooMany = [];
    for ($i = 0; $i < 21; $i++) {
        $tooMany[] = [
            'event_id' => (string) Str::uuid(),
            'type' => 'PAGE_MOUNT',
        ];
    }

    test()->postJson("/api/exams/attempts/{$attemptId}/journey-events", [
        'events' => $tooMany,
    ])->assertUnprocessable();
});

it('sanitizes sensitive keys from enabled server and client journey logs', function (): void {
    ['exam' => $exam] = createJourneyExamContext();

    $attemptId = test()->postJson('/api/exams/start', ['exam_id' => $exam->id])
        ->assertCreated()
        ->json('data.attempt_id');

    test()->postJson('/api/exams/save-answer', [
        'attempt_id' => $attemptId,
        'question_id' => Question::where('exam_id', $exam->id)->value('id'),
        'answer_text' => 'إجابة سرية لا يجب تسجيلها',
    ])->assertUnprocessable();

    test()->postJson("/api/exams/attempts/{$attemptId}/journey-events", [
        'events' => [
            [
                'event_id' => (string) Str::uuid(),
                'type' => 'START_API_FAIL',
                'metadata' => [
                    'authorization' => 'Bearer secret-token',
                    'answer_text' => 'leak',
                ],
            ],
        ],
    ])->assertCreated();

    $log = journeyFeatureLogContents();

    expect($log)->toContain('attempt_id')
        ->and($log)->toContain('correlation_id')
        ->and($log)->not->toContain('Bearer secret-token')
        ->and($log)->not->toContain('إجابة سرية')
        ->and($log)->not->toContain('leak');
});
