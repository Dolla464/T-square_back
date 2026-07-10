<?php

/**
 * Feature tests for historical learning-group creation (is_historical flag).
 *
 * POST /api/admin/learning-groups
 */

use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Instructor;
use App\Models\LearningGroup;
use App\Models\Order;
use App\Models\Student;
use App\Models\User;
use App\Notifications\CourseReviewRequired;
use App\Notifications\InstructorGroupAssignedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $adminRole   = Role::create(['name' => 'admin', 'guard_name' => 'web']);
    $this->admin = User::factory()->create();
    $this->admin->assignRole($adminRole);
    Sanctum::actingAs($this->admin, ['*']);

    $this->instructor = Instructor::factory()->create();

    $this->course = Course::factory()->create([
        'instructor_id'  => $this->instructor->id,
        'duration_weeks' => 4,
    ]);
});

function groupCreatePayload(Course $course, Instructor $instructor, array $overrides = []): array
{
    return array_merge([
        'group_name'    => 'Historical Batch',
        'course_id'     => $course->id,
        'instructor_id' => $instructor->id,
        'start_date'    => now()->subWeeks(6)->toDateString(),
        'is_historical' => true,
        'schedules'     => [
            [
                'day_of_week' => 2,
                'start_time'  => '10:00',
                'end_time'    => '12:00',
                'room'        => 'A1',
            ],
        ],
    ], $overrides);
}

function enrollStudentForHistoricalCreate(Course $course): object
{
    $student = Student::factory()->create();

    $order = Order::create([
        'student_id'    => $student->id,
        'total_amount'  => 500,
        'status'        => 'completed',
        'billing_name'  => 'Test Billing',
        'billing_email' => 'billing@test.com',
        'billing_phone' => '01000000000',
    ]);

    Enrollment::create([
        'student_id'   => $student->id,
        'course_id'    => $course->id,
        'order_id'     => $order->id,
        'group_id'     => null,
        'price_paid'   => 500,
        'is_completed' => false,
        'completed_at' => null,
    ]);

    return (object) compact('student', 'order');
}

it('rejects normal create with a past start date', function (): void {
    $response = $this->postJson(
        '/api/admin/learning-groups',
        groupCreatePayload($this->course, $this->instructor, [
            'is_historical' => false,
            'start_date'    => now()->subWeeks(2)->toDateString(),
        ])
    );

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['start_date']);
});

it('creates a historical group with a past start date', function (): void {
    Notification::fake();

    $startDate = now()->subWeeks(6)->toDateString();

    $response = $this->postJson(
        '/api/admin/learning-groups',
        groupCreatePayload($this->course, $this->instructor, [
            'start_date' => $startDate,
        ])
    );

    $response
        ->assertCreated()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.start_date', $startDate)
        ->assertJsonPath('data.status', 'completed')
        ->assertJsonStructure([
            'data' => [
                'sync' => [
                    'historical_backfill' => [
                        'past_sessions_completed',
                        'today_upcoming',
                        'future_upcoming',
                    ],
                ],
            ],
        ]);

    $groupId = $response->json('data.id');
    expect($groupId)->not->toBeNull();

    $sessions = AttendanceSession::where('learning_group_id', $groupId)->get();
    expect($sessions)->not->toBeEmpty();

    $today = now()->toDateString();

    foreach ($sessions as $session) {
        $sessionDate = $session->session_date->format('Y-m-d');

        if ($sessionDate < $today) {
            expect($session->status)->toBe('completed');
        } else {
            expect($session->status)->toBe('upcoming');
        }
    }
});

it('rejects historical create when start date is in the future', function (): void {
    $response = $this->postJson(
        '/api/admin/learning-groups',
        groupCreatePayload($this->course, $this->instructor, [
            'start_date' => now()->addWeek()->toDateString(),
        ])
    );

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['start_date']);
});

it('sets active status when historical group end date is still in the future', function (): void {
    Notification::fake();

    $startDate = now()->subWeeks(2)->toDateString();

    $response = $this->postJson(
        '/api/admin/learning-groups',
        groupCreatePayload($this->course, $this->instructor, [
            'start_date' => $startDate,
        ])
    );

    $response
        ->assertCreated()
        ->assertJsonPath('data.status', 'active');

    $groupId = $response->json('data.id');

    $futureCount = AttendanceSession::where('learning_group_id', $groupId)
        ->where('status', 'upcoming')
        ->whereDate('session_date', '>', now()->toDateString())
        ->count();

    expect($futureCount)->toBeGreaterThan(0);
});

it('does not notify the instructor for historical groups', function (): void {
    Notification::fake();

    $this->postJson(
        '/api/admin/learning-groups',
        groupCreatePayload($this->course, $this->instructor)
    )->assertCreated();

    Notification::assertNotSentTo(
        $this->instructor->user,
        InstructorGroupAssignedNotification::class
    );
});

it('notifies the instructor for normal groups', function (): void {
    Notification::fake();

    $this->postJson(
        '/api/admin/learning-groups',
        groupCreatePayload($this->course, $this->instructor, [
            'is_historical' => false,
            'start_date'    => now()->toDateString(),
        ])
    )->assertCreated();

    Notification::assertSentTo(
        $this->instructor->user,
        InstructorGroupAssignedNotification::class
    );
});

it('marks enrollments completed and sends review notifications for completed historical groups', function (): void {
    Notification::fake();

    $s1 = enrollStudentForHistoricalCreate($this->course);
    $s2 = enrollStudentForHistoricalCreate($this->course);

    $response = $this->postJson(
        '/api/admin/learning-groups',
        groupCreatePayload($this->course, $this->instructor, [
            'student_ids'      => [$s1->student->id, $s2->student->id],
            'student_statuses' => [
                (string) $s1->student->id => false,
                (string) $s2->student->id => false,
            ],
        ])
    );

    $response
        ->assertCreated()
        ->assertJsonPath('data.sync.enrollments_completed', 2)
        ->assertJsonPath('data.sync.notifications_sent', 2);

    Notification::assertSentTo(
        $s1->student->user,
        CourseReviewRequired::class
    );

    Notification::assertSentTo(
        $s2->student->user,
        CourseReviewRequired::class
    );
});

it('does not create attendance records during historical backfill', function (): void {
    Notification::fake();

    $response = $this->postJson(
        '/api/admin/learning-groups',
        groupCreatePayload($this->course, $this->instructor)
    );

    $response->assertCreated();

    $groupId = $response->json('data.id');

    expect(
        AttendanceRecord::whereHas('session', fn ($q) => $q->where('learning_group_id', $groupId))->count()
    )->toBe(0);
});

it('generates exactly duration_weeks times schedule days sessions for historical groups', function (): void {
    Notification::fake();

    $this->course->update(['duration_weeks' => 24]);

    $response = $this->postJson(
        '/api/admin/learning-groups',
        groupCreatePayload($this->course, $this->instructor, [
            'start_date' => '2026-06-15',
            'schedules'  => [
                [
                    'day_of_week' => 2,
                    'start_time'  => '10:00',
                    'end_time'    => '12:00',
                    'room'        => 'A1',
                ],
                [
                    'day_of_week' => 4,
                    'start_time'  => '10:00',
                    'end_time'    => '12:00',
                    'room'        => 'A1',
                ],
            ],
        ])
    );

    $response
        ->assertCreated()
        ->assertJsonPath('data.end_date', '2026-11-29');

    $groupId = $response->json('data.id');

    expect(AttendanceSession::where('learning_group_id', $groupId)->count())->toBe(48);
});

it('respects explicit status in payload over historical defaults', function (): void {
    Notification::fake();

    $response = $this->postJson(
        '/api/admin/learning-groups',
        groupCreatePayload($this->course, $this->instructor, [
            'status' => 'active',
        ])
    );

    $response
        ->assertCreated()
        ->assertJsonPath('data.status', 'active');
});
