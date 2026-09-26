<?php

use App\Models\AttendanceSession;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Instructor;
use App\Models\LearningGroup;
use App\Models\LearningGroupSchedule;
use App\Models\Order;
use App\Models\Student;
use App\Services\Admin\AdminScheduleService;
use App\Services\Attendance\AttendanceSessionCompletionService;
use App\Services\Attendance\AttendanceSessionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    Role::create(['name' => 'admin', 'guard_name' => 'web']);
    Role::create(['name' => 'student', 'guard_name' => 'web']);

    $this->sessionService = app(AttendanceSessionService::class);
    $this->instructor = Instructor::factory()->create();
    $this->course = Course::factory()->create(['instructor_id' => $this->instructor->id]);
});

function createAttendanceFixture(array $sessionOverrides = [], ?Carbon $now = null): array
{
    if ($now) {
        Carbon::setTestNow($now);
    }

    $group = LearningGroup::factory()->create([
        'course_id' => test()->course->id,
        'course_instructor_id' => courseInstructorIdFor(test()->course, test()->instructor),
        'start_date' => now()->subWeeks(4)->toDateString(),
        'end_date' => now()->addWeek()->toDateString(),
        'status' => 'active',
    ]);

    $schedule = LearningGroupSchedule::create([
        'learning_group_id' => $group->id,
        'day_of_week' => 2,
        'start_time' => '10:00',
        'end_time' => '12:00',
        'room' => 'A1',
    ]);

    $session = AttendanceSession::create(array_merge([
        'learning_group_id' => $group->id,
        'schedule_id' => $schedule->id,
        'session_date' => now()->toDateString(),
        'status' => 'upcoming',
    ], $sessionOverrides));

    $student = Student::factory()->create();
    $student->user->assignRole('student');

    $order = Order::create([
        'student_id' => $student->id,
        'total_amount' => 500,
        'status' => 'completed',
        'billing_name' => 'Test Billing',
        'billing_email' => 'billing@test.com',
        'billing_phone' => '01000000000',
    ]);

    Enrollment::create([
        'student_id' => $student->id,
        'course_id' => test()->course->id,
        'order_id' => $order->id,
        'group_id' => $group->id,
        'price_paid' => 500,
        'is_completed' => false,
    ]);

    return compact('group', 'schedule', 'session', 'student');
}

afterEach(function (): void {
    Carbon::setTestNow();
});

it('keeps cancelled sessions cancelled even after end time', function (): void {
    ['session' => $session] = createAttendanceFixture([
        'session_date' => now()->subDay()->toDateString(),
        'status' => 'cancelled',
    ], Carbon::parse('2026-01-02 14:00:00'));

    expect($this->sessionService->resolveLifecycleStatus($session))->toBe('cancelled');
});

it('keeps completed sessions completed even after end time', function (): void {
    ['session' => $session] = createAttendanceFixture([
        'session_date' => now()->subDay()->toDateString(),
        'status' => 'completed',
    ], Carbon::parse('2026-01-02 14:00:00'));

    expect($this->sessionService->resolveLifecycleStatus($session))->toBe('completed');
});

it('returns active when end plus grace has not passed', function (): void {
    $now = Carbon::parse('2026-01-02 11:00:00');
    ['session' => $session] = createAttendanceFixture([
        'session_date' => '2026-01-02',
        'status' => 'active',
    ], $now);

    expect($this->sessionService->resolveLifecycleStatus($session, $now))->toBe('active');
});

it('returns completed for active session after effective end plus 30 minutes', function (): void {
    $now = Carbon::parse('2026-01-02 13:00:00');
    ['session' => $session] = createAttendanceFixture([
        'session_date' => '2026-01-02',
        'status' => 'active',
    ], $now);

    expect($this->sessionService->resolveLifecycleStatus($session, $now))->toBe('completed');
});

it('returns completed for upcoming session after effective end plus 30 minutes', function (): void {
    $now = Carbon::parse('2026-01-02 13:00:00');
    ['session' => $session] = createAttendanceFixture([
        'session_date' => '2026-01-02',
        'status' => 'upcoming',
    ], $now);

    expect($this->sessionService->resolveLifecycleStatus($session, $now))->toBe('completed');
});

it('completes stale active sessions from previous days via completion service', function (): void {
    $now = Carbon::parse('2026-01-03 10:00:00');
    ['session' => $session] = createAttendanceFixture([
        'session_date' => '2026-01-01',
        'status' => 'active',
        'qr_code' => 'sess_test123',
    ], $now);

    $completed = app(AttendanceSessionCompletionService::class)->completeEligibleSessions($now);

    expect($completed)->toBe(1);
    expect($session->fresh()->status)->toBe('completed');
});

it('does not complete cancelled sessions', function (): void {
    $now = Carbon::parse('2026-01-03 10:00:00');
    ['session' => $session] = createAttendanceFixture([
        'session_date' => '2026-01-01',
        'status' => 'cancelled',
    ], $now);

    app(AttendanceSessionCompletionService::class)->completeEligibleSessions($now);

    expect($session->fresh()->status)->toBe('cancelled');
});

it('returns effective session_status in group history without mutating db', function (): void {
    $now = Carbon::parse('2026-01-03 10:00:00');
    ['group' => $group, 'session' => $session, 'student' => $student] = createAttendanceFixture([
        'session_date' => '2026-01-01',
        'status' => 'active',
    ], $now);

    Sanctum::actingAs($student->user, ['*']);

    $response = $this->getJson("/api/student/attendance/groups/{$group->id}")
        ->assertOk();

    expect($response->json('data.sessions.0.session_status'))->toBe('completed')
        ->and($response->json('data.sessions.0.status'))->toBe('not_marked')
        ->and($session->fresh()->status)->toBe('active');
});

it('returns effective lifecycle status on today endpoint while keeping student_status semantics', function (): void {
    $now = Carbon::parse('2026-01-02 13:00:00');
    ['session' => $session, 'student' => $student] = createAttendanceFixture([
        'session_date' => '2026-01-02',
        'status' => 'active',
    ], $now);

    Sanctum::actingAs($student->user, ['*']);

    $response = $this->getJson('/api/student/attendance/today')
        ->assertOk();

    expect($response->json('data.0.status'))->toBe('completed')
        ->and($response->json('data.0.student_status'))->toBe('absent');
});

it('resets active session to upcoming and clears qr when rescheduled to the future', function (): void {
    $now = Carbon::parse('2026-01-02 10:00:00');
    Carbon::setTestNow($now);

    ['session' => $session] = createAttendanceFixture([
        'session_date' => '2026-01-02',
        'status' => 'active',
        'qr_code' => 'sess_old_qr',
    ], $now);

    app(AdminScheduleService::class)->rescheduleSession($session, [
        'date' => '2026-01-10',
        'start_time' => '10:00',
        'end_time' => '12:00',
    ]);

    $session->refresh();

    expect($session->status)->toBe('upcoming')
        ->and($session->qr_code)->toBeNull();
});

it('repair stale command uses the same completion logic as attendance complete', function (): void {
    $now = Carbon::parse('2026-01-03 10:00:00');
    Carbon::setTestNow($now);

    ['session' => $session] = createAttendanceFixture([
        'session_date' => '2026-01-01',
        'status' => 'upcoming',
    ], $now);

    $this->artisan('attendance:repair-stale')->assertSuccessful();

    expect($session->fresh()->status)->toBe('completed');
});
