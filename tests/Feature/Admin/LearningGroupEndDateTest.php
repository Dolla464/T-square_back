<?php

use App\Models\AttendanceSession;
use App\Models\Course;
use App\Models\Instructor;
use App\Models\LearningGroup;
use App\Models\User;
use App\Services\Admin\AdminLearningGroupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $adminRole = Role::create(['name' => 'admin', 'guard_name' => 'web']);
    $this->admin = User::factory()->create();
    $this->admin->assignRole($adminRole);
    Sanctum::actingAs($this->admin, ['*']);

    $this->instructor = Instructor::factory()->create();
    $this->course = Course::factory()->create([
        'instructor_id' => $this->instructor->id,
        'duration_weeks' => 4,
    ]);
    $this->courseInstructorId = courseInstructorIdFor($this->course, $this->instructor);
});

function endDateCreatePayload(Course $course, int $courseInstructorId, array $overrides = []): array
{
    return array_merge([
        'group_name' => 'End Date Test Batch',
        'course_id' => $course->id,
        'course_instructor_id' => $courseInstructorId,
        'start_date' => now()->subWeeks(6)->toDateString(),
        'is_historical' => true,
        'schedules' => [
            [
                'day_of_week' => 5,
                'start_time' => '10:00',
                'end_time' => '12:00',
                'room' => 'A1',
            ],
        ],
    ], $overrides);
}

it('sets end_date to the last generated session for a single schedule day', function (): void {
    $response = $this->postJson(
        '/api/admin/learning-groups',
        endDateCreatePayload($this->course, $this->courseInstructorId)
    );

    $response->assertCreated();

    $groupId = $response->json('data.id');
    $group = LearningGroup::query()->findOrFail($groupId);
    $lastSession = AttendanceSession::query()
        ->where('learning_group_id', $groupId)
        ->orderByRaw('DATE(COALESCE(override_date, session_date)) DESC')
        ->first();

    expect($lastSession)->not->toBeNull()
        ->and($group->end_date->toDateString())->toBe($lastSession->session_date->toDateString())
        ->and($response->json('data.end_date'))->toBe($lastSession->session_date->toDateString());
});

it('sets end_date to the latest schedule day in the final week for multiple schedule days', function (): void {
    $response = $this->postJson(
        '/api/admin/learning-groups',
        endDateCreatePayload($this->course, $this->courseInstructorId, [
            'start_date' => '2026-06-15',
            'schedules' => [
                [
                    'day_of_week' => 2,
                    'start_time' => '10:00',
                    'end_time' => '12:00',
                    'room' => 'A1',
                ],
                [
                    'day_of_week' => 4,
                    'start_time' => '10:00',
                    'end_time' => '12:00',
                    'room' => 'A1',
                ],
            ],
        ])
    );

    $response->assertCreated();

    $groupId = $response->json('data.id');
    $lastSession = AttendanceSession::query()
        ->where('learning_group_id', $groupId)
        ->orderByRaw('DATE(COALESCE(override_date, session_date)) DESC')
        ->first();

    expect($response->json('data.end_date'))->toBe($lastSession->session_date->toDateString())
        ->and(AttendanceSession::query()->where('learning_group_id', $groupId)->count())->toBe(8);
});

it('respects override_date when synchronizing end_date', function (): void {
    $response = $this->postJson(
        '/api/admin/learning-groups',
        endDateCreatePayload($this->course, $this->courseInstructorId)
    );

    $response->assertCreated();

    $groupId = $response->json('data.id');
    $lastSession = AttendanceSession::query()
        ->where('learning_group_id', $groupId)
        ->orderByRaw('DATE(session_date) DESC')
        ->first();

    $lastSession->update(['override_date' => $lastSession->session_date->copy()->addDays(7)->toDateString()]);

    app(AdminLearningGroupService::class)->fixGroupSessionBounds(
        LearningGroup::query()->with('course', 'schedules')->findOrFail($groupId)
    );

    $group = LearningGroup::query()->findOrFail($groupId);

    expect($group->end_date->toDateString())->toBe($lastSession->fresh()->override_date->toDateString());
});

it('falls back to the calendar formula when a group has no schedules', function (): void {
    $group = LearningGroup::create([
        'group_name' => 'No Schedule Group',
        'course_id' => $this->course->id,
        'course_instructor_id' => $this->courseInstructorId,
        'start_date' => '2026-01-01',
        'end_date' => '2026-01-31',
        'status' => 'active',
    ]);

    $result = app(AdminLearningGroupService::class)->fixGroupSessionBounds($group);

    expect($result['new_end_date'])->toBe('2026-01-28');
});
