<?php

use App\Models\AttendanceDevice;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Branch;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Instructor;
use App\Models\LearningGroup;
use App\Models\LearningGroupSchedule;
use App\Models\Order;
use App\Models\Student;
use App\Models\User;
use App\Services\Attendance\StudentAttendanceCardLifecycleService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    Role::create(['name' => 'admin', 'guard_name' => 'web']);
    Role::create(['name' => 'student', 'guard_name' => 'web']);
    Role::create(['name' => 'instructor', 'guard_name' => 'web']);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');

    $this->cardService = app(StudentAttendanceCardLifecycleService::class);

    $this->device = AttendanceDevice::create([
        'device_id' => 'scan-device-1',
        'name' => 'Lobby Scanner',
        'is_active' => true,
    ]);
});

afterEach(function (): void {
    Carbon::setTestNow();
});

function scanFixture(array $sessionOverrides = [], ?Carbon $now = null): array
{
    if ($now) {
        Carbon::setTestNow($now);
    }

    $instructor = Instructor::factory()->create();
    $course = Course::factory()->create(['instructor_id' => $instructor->id]);

    $group = LearningGroup::factory()->create([
        'course_id' => $course->id,
        'course_instructor_id' => courseInstructorIdFor($course, $instructor),
        'start_date' => now()->subWeeks(4)->toDateString(),
        'end_date' => now()->addWeek()->toDateString(),
        'status' => 'active',
    ]);

    $schedule = LearningGroupSchedule::create([
        'learning_group_id' => $group->id,
        'day_of_week' => (int) now()->dayOfWeek,
        'start_time' => '10:00',
        'end_time' => '12:00',
        'room' => 'A1',
    ]);

    $session = AttendanceSession::create(array_merge([
        'learning_group_id' => $group->id,
        'schedule_id' => $schedule->id,
        'session_date' => now()->toDateString(),
        'status' => 'active',
        'qr_code' => 'sess_'.Str::random(16),
    ], $sessionOverrides));

    $student = Student::factory()->create(['status' => 'active']);
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
        'course_id' => $course->id,
        'order_id' => $order->id,
        'group_id' => $group->id,
        'price_paid' => 500,
        'is_completed' => false,
    ]);

    return compact('instructor', 'course', 'group', 'schedule', 'session', 'student');
}

function scanPost(string $qrCode, ?string $deviceId = 'scan-device-1')
{
    return test()->postJson('/api/attendance/scan', [
        'qr_code' => $qrCode,
        'device_id' => $deviceId,
    ]);
}

function issueCardToken(Student $student, User $admin): string
{
    return app(StudentAttendanceCardLifecycleService::class)
        ->issue($admin, $student)
        ->plainTextToken;
}

it('records attendance for a valid permanent card with exactly one eligible session', function (): void {
    $now = Carbon::parse('2026-01-02 11:00:00');
    ['session' => $session, 'student' => $student] = scanFixture([], $now);

    $token = issueCardToken($student, $this->admin);

    $response = scanPost($token)->assertOk();

    expect($response->json('data.status'))->toBe('present')
        ->and($response->json('data'))->not->toHaveKey('session_id')
        ->and($response->json('data'))->not->toHaveKey('token_hash');

    $record = AttendanceRecord::query()
        ->where('session_id', $session->id)
        ->where('student_id', $student->id)
        ->first();

    expect($record)->not->toBeNull()
        ->and($record->status)->toBe('present')
        ->and($record->marked_by)->toBe('student_card');
});

it('rejects malformed unknown and revoked card tokens without writing attendance', function (): void {
    $now = Carbon::parse('2026-01-02 11:00:00');
    ['student' => $student] = scanFixture([], $now);

    scanPost('card_tooshort')->assertStatus(400)
        ->assertJsonPath('code', 'INVALID_FORMAT');
    expect(AttendanceRecord::query()->count())->toBe(0);

    $unknown = 'card_'.str_repeat('a', 64);
    scanPost($unknown)->assertStatus(404)
        ->assertJsonPath('code', 'NOT_FOUND');
    expect(AttendanceRecord::query()->count())->toBe(0);

    $token = issueCardToken($student, $this->admin);
    $card = app(StudentAttendanceCardLifecycleService::class)->findActiveCardForStudent($student);
    app(StudentAttendanceCardLifecycleService::class)->revoke($this->admin, $card);

    scanPost($token)->assertStatus(404)
        ->assertJsonPath('code', 'NOT_FOUND');
    expect(AttendanceRecord::query()->count())->toBe(0);
});

it('rejects inactive students for card att and sess automatic flows without writing attendance', function (): void {
    $now = Carbon::parse('2026-01-02 11:00:00');
    ['session' => $session, 'student' => $student] = scanFixture([], $now);

    $student->forceFill(['status' => 'inactive'])->save();

    $token = issueCardToken($student, $this->admin);
    scanPost($token)->assertStatus(403)->assertJsonPath('code', 'STUDENT_INACTIVE');
    expect(AttendanceRecord::query()->count())->toBe(0);

    $attCode = 'att_'.Str::random(16);
    AttendanceRecord::create([
        'session_id' => $session->id,
        'student_id' => $student->id,
        'student_qr_code' => $attCode,
        'status' => 'absent',
        'marked_by' => 'student_qr',
        'qr_expires_at' => $now->copy()->addHour(),
    ]);

    scanPost($attCode)->assertStatus(403)->assertJsonPath('code', 'STUDENT_INACTIVE');
    expect(
        AttendanceRecord::query()->where('session_id', $session->id)->where('student_id', $student->id)->value('status')
    )->toBe('absent');

    Sanctum::actingAs($student->user, ['*']);
    $this->postJson('/api/student/attendance/check-in', ['qr_code' => $session->qr_code])
        ->assertStatus(403)
        ->assertJsonPath('code', 'STUDENT_INACTIVE');
    expect(AttendanceRecord::query()->where('session_id', $session->id)->where('student_id', $student->id)->count())->toBe(1);
});

it('rejects card scan when no eligible session exists', function (): void {
    $now = Carbon::parse('2026-01-02 08:00:00');
    ['student' => $student] = scanFixture(['status' => 'active'], $now);

    $token = issueCardToken($student, $this->admin);

    scanPost($token)->assertStatus(422)->assertJsonPath('code', 'NO_ELIGIBLE_SESSION');
    expect(AttendanceRecord::query()->count())->toBe(0);
});

it('returns MULTIPLE_SESSIONS without writing attendance when more than one session matches', function (): void {
    $now = Carbon::parse('2026-01-02 11:00:00');
    Carbon::setTestNow($now);

    $instructor = Instructor::factory()->create();
    $courseA = Course::factory()->create(['instructor_id' => $instructor->id]);
    $courseB = Course::factory()->create(['instructor_id' => $instructor->id]);

    $groupA = LearningGroup::factory()->create([
        'course_id' => $courseA->id,
        'course_instructor_id' => courseInstructorIdFor($courseA, $instructor),
        'start_date' => now()->subWeek()->toDateString(),
        'end_date' => now()->addWeek()->toDateString(),
        'status' => 'active',
    ]);
    $groupB = LearningGroup::factory()->create([
        'course_id' => $courseB->id,
        'course_instructor_id' => courseInstructorIdFor($courseB, $instructor),
        'start_date' => now()->subWeek()->toDateString(),
        'end_date' => now()->addWeek()->toDateString(),
        'status' => 'active',
    ]);

    $scheduleA = LearningGroupSchedule::create([
        'learning_group_id' => $groupA->id,
        'day_of_week' => (int) now()->dayOfWeek,
        'start_time' => '10:00',
        'end_time' => '12:00',
        'room' => 'R1',
    ]);
    $scheduleB = LearningGroupSchedule::create([
        'learning_group_id' => $groupB->id,
        'day_of_week' => (int) now()->dayOfWeek,
        'start_time' => '10:00',
        'end_time' => '12:00',
        'room' => 'R1',
    ]);

    $sessionA = AttendanceSession::create([
        'learning_group_id' => $groupA->id,
        'schedule_id' => $scheduleA->id,
        'session_date' => now()->toDateString(),
        'status' => 'active',
    ]);
    $sessionB = AttendanceSession::create([
        'learning_group_id' => $groupB->id,
        'schedule_id' => $scheduleB->id,
        'session_date' => now()->toDateString(),
        'status' => 'active',
    ]);

    $student = Student::factory()->create(['status' => 'active']);
    $student->user->assignRole('student');

    foreach ([$courseA, $courseB] as $index => $course) {
        $order = Order::create([
            'student_id' => $student->id,
            'total_amount' => 500,
            'status' => 'completed',
            'billing_name' => 'Billing',
            'billing_email' => 'b@test.com',
            'billing_phone' => '01000000000',
        ]);

        Enrollment::create([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'order_id' => $order->id,
            'group_id' => $index === 0 ? $groupA->id : $groupB->id,
            'price_paid' => 500,
            'is_completed' => false,
        ]);
    }

    $token = issueCardToken($student, $this->admin);

    $response = scanPost($token)->assertStatus(422)->assertJsonPath('code', 'MULTIPLE_SESSIONS');

    expect($response->json('data'))->toBeNull()
        ->and(AttendanceRecord::query()->count())->toBe(0)
        ->and($sessionA->id)->not->toBe($sessionB->id);
});

it('rejects inactive or unauthorized devices and respects branch restrictions', function (): void {
    $now = Carbon::parse('2026-01-02 11:00:00');
    ['group' => $group, 'student' => $student] = scanFixture([], $now);

    $token = issueCardToken($student, $this->admin);

    AttendanceDevice::query()->where('device_id', 'scan-device-1')->update(['is_active' => false]);
    scanPost($token, 'scan-device-1')->assertStatus(403);

    AttendanceDevice::query()->where('device_id', 'scan-device-1')->update(['is_active' => true]);

    $branchA = Branch::create(['name' => 'Branch A', 'slug' => 'branch-a']);
    $branchB = Branch::create(['name' => 'Branch B', 'slug' => 'branch-b']);
    $group->update(['branch_id' => $branchA->id]);

    $branchDevice = AttendanceDevice::create([
        'device_id' => 'branch-device',
        'name' => 'Branch Device',
        'is_active' => true,
        'branch_id' => $branchB->id,
    ]);

    scanPost($token, $branchDevice->device_id)->assertStatus(422)
        ->assertJsonPath('code', 'NO_ELIGIBLE_SESSION');
});

it('does not overwrite excused or late records when scanning a permanent card', function (): void {
    $now = Carbon::parse('2026-01-02 11:00:00');
    ['session' => $session, 'student' => $student] = scanFixture([], $now);

    AttendanceRecord::create([
        'session_id' => $session->id,
        'student_id' => $student->id,
        'status' => 'excused',
        'marked_by' => 'instructor_manual',
        'marked_at' => $now,
    ]);

    $token = issueCardToken($student, $this->admin);
    scanPost($token)->assertOk();

    expect(AttendanceRecord::query()->where('session_id', $session->id)->where('student_id', $student->id)->count())->toBe(1)
        ->and(AttendanceRecord::query()->first()->status)->toBe('excused');

    AttendanceRecord::query()->delete();

    AttendanceRecord::create([
        'session_id' => $session->id,
        'student_id' => $student->id,
        'status' => 'late',
        'marked_by' => 'instructor_manual',
        'marked_at' => $now,
    ]);

    scanPost($token)->assertOk();

    expect(AttendanceRecord::query()->first()->status)->toBe('late');
});

it('keeps duplicate card scans idempotent without duplicate records', function (): void {
    $now = Carbon::parse('2026-01-02 11:00:00');
    ['session' => $session, 'student' => $student] = scanFixture([], $now);

    $token = issueCardToken($student, $this->admin);

    scanPost($token)->assertOk();
    scanPost($token)->assertOk();

    expect(AttendanceRecord::query()->where('session_id', $session->id)->where('student_id', $student->id)->count())->toBe(1);
});

it('still records attendance for active students using att scan and sess check-in', function (): void {
    $now = Carbon::parse('2026-01-02 11:00:00');
    ['session' => $session, 'student' => $student] = scanFixture([], $now);

    $attCode = 'att_'.Str::random(16);
    AttendanceRecord::create([
        'session_id' => $session->id,
        'student_id' => $student->id,
        'student_qr_code' => $attCode,
        'status' => 'absent',
        'marked_by' => 'student_qr',
        'qr_expires_at' => $now->copy()->addHour(),
    ]);

    scanPost($attCode)->assertOk()->assertJsonPath('data.status', 'present');

    Sanctum::actingAs($student->user, ['*']);
    $this->postJson('/api/student/attendance/check-in', ['qr_code' => $session->qr_code])
        ->assertOk()
        ->assertJsonPath('data.status', 'present');
});
