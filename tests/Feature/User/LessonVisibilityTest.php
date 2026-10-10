<?php

use App\Models\AttendanceSession;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\GoogleStorageAccount;
use App\Models\Instructor;
use App\Models\LearningGroup;
use App\Models\LearningGroupSchedule;
use App\Models\Lesson;
use App\Models\Order;
use App\Models\Student;
use App\Models\User;
use App\Services\Admin\AdminScheduleService;
use App\Services\Google\GoogleDriveStreamingService;
use App\Services\User\LessonVisibilityService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    Role::create(['name' => 'admin', 'guard_name' => 'web']);
    Role::create(['name' => 'student', 'guard_name' => 'web']);

    $this->instructor = Instructor::factory()->create();
    $this->account = GoogleStorageAccount::factory()->create();
    $this->visibilityService = app(LessonVisibilityService::class);
});

afterEach(function (): void {
    Carbon::setTestNow();
});

function lessonVisibilityCourse(array $courseOverrides = []): Course
{
    return Course::factory()->create(array_merge([
        'instructor_id' => test()->instructor->id,
        'google_storage_account_id' => test()->account->id,
        'status' => 'published',
        'published_at' => now(),
        'attendance_types' => ['online', 'offline', 'hybrid'],
    ], $courseOverrides));
}

function lessonVisibilityGroup(Course $course, array $groupOverrides = []): LearningGroup
{
    return LearningGroup::factory()->create(array_merge([
        'course_id' => $course->id,
        'course_instructor_id' => courseInstructorIdFor($course, test()->instructor),
        'start_date' => now()->subWeeks(2)->toDateString(),
        'end_date' => now()->addWeeks(4)->toDateString(),
        'status' => 'active',
    ], $groupOverrides));
}

function lessonVisibilitySchedule(LearningGroup $group, array $overrides = []): LearningGroupSchedule
{
    return LearningGroupSchedule::create(array_merge([
        'learning_group_id' => $group->id,
        'day_of_week' => 2,
        'start_time' => '10:00',
        'end_time' => '12:00',
        'room' => 'A1',
    ], $overrides));
}

function lessonVisibilitySession(
    LearningGroup $group,
    LearningGroupSchedule $schedule,
    array $overrides = [],
): AttendanceSession {
    return AttendanceSession::create(array_merge([
        'learning_group_id' => $group->id,
        'schedule_id' => $schedule->id,
        'session_date' => now()->toDateString(),
        'status' => 'completed',
    ], $overrides));
}

function lessonVisibilityStudentEnrollment(
    Course $course,
    ?int $groupId = null,
    ?string $attendanceType = 'offline',
): array {
    $user = User::factory()->create();
    $user->assignRole('student');
    $student = Student::factory()->create(['user_id' => $user->id]);
    $order = Order::factory()->create([
        'student_id' => $student->id,
        'status' => 'completed',
    ]);
    $enrollment = Enrollment::factory()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'order_id' => $order->id,
        'group_id' => $groupId,
        'attendance_type' => $attendanceType,
    ]);

    return compact('user', 'student', 'order', 'enrollment');
}

function lessonVisibilityLessons(Course $course, int $count = 2): Collection
{
    return collect(range(1, $count))->map(function (int $index) use ($course) {
        return Lesson::factory()->create([
            'course_id' => $course->id,
            'sort_order' => $index,
            'is_active' => true,
        ]);
    });
}

it('returns all lessons for online enrollment in course details', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-01-02 15:00:00'));

    $course = lessonVisibilityCourse();
    $lessons = lessonVisibilityLessons($course, 2);
    ['user' => $user] = lessonVisibilityStudentEnrollment($course, null, 'online');
    Sanctum::actingAs($user, ['*']);

    $response = $this->getJson("/api/student/dashboard/courses/{$course->id}");

    $response->assertOk()
        ->assertJsonCount(2, 'data.lessons')
        ->assertJsonPath('data.lessons.0.id', $lessons[0]->id)
        ->assertJsonPath('data.lessons.1.id', $lessons[1]->id);
});

it('returns no lessons for offline enrollment without group', function (): void {
    $course = lessonVisibilityCourse();
    lessonVisibilityLessons($course, 2);
    ['user' => $user] = lessonVisibilityStudentEnrollment($course, null, 'offline');
    Sanctum::actingAs($user, ['*']);

    $this->getJson("/api/student/dashboard/courses/{$course->id}")
        ->assertOk()
        ->assertJsonCount(0, 'data.lessons');
});

it('returns no lessons for hybrid enrollment without group', function (): void {
    $course = lessonVisibilityCourse();
    lessonVisibilityLessons($course, 2);
    ['user' => $user] = lessonVisibilityStudentEnrollment($course, null, 'hybrid');
    Sanctum::actingAs($user, ['*']);

    $response = $this->getJson("/api/student/dashboard/courses/{$course->id}")
        ->assertOk()
        ->assertJsonCount(0, 'data.lessons');

    expect($response->json('data.enrollment.attendance_type'))->toBe('hybrid');
});

it('returns no lessons for null attendance type enrollment without group', function (): void {
    $course = lessonVisibilityCourse();
    $lessons = lessonVisibilityLessons($course, 2);
    ['user' => $user, 'enrollment' => $enrollment] = lessonVisibilityStudentEnrollment($course, null, null);
    Sanctum::actingAs($user, ['*']);

    $this->getJson("/api/student/dashboard/courses/{$course->id}")
        ->assertOk()
        ->assertJsonCount(0, 'data.lessons');

    expect($enrollment->attendance_type)->toBeNull()
        ->and(test()->visibilityService->isLessonAvailable($enrollment, $course, $lessons[0]))->toBeFalse();
});

it('unlocks offline lesson only after effective session end plus one hour', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-01-02 12:30:00'));

    $course = lessonVisibilityCourse();
    $lessons = lessonVisibilityLessons($course, 2);
    $group = lessonVisibilityGroup($course);
    $schedule = lessonVisibilitySchedule($group);
    lessonVisibilitySession($group, $schedule, [
        'session_date' => '2026-01-02',
        'status' => 'completed',
    ]);
    ['user' => $user, 'enrollment' => $enrollment] = lessonVisibilityStudentEnrollment($course, $group->id, 'offline');
    Sanctum::actingAs($user, ['*']);

    $this->getJson("/api/student/dashboard/courses/{$course->id}")
        ->assertOk()
        ->assertJsonCount(0, 'data.lessons');

    Carbon::setTestNow(Carbon::parse('2026-01-02 13:00:00'));

    $this->getJson("/api/student/dashboard/courses/{$course->id}")
        ->assertOk()
        ->assertJsonCount(1, 'data.lessons')
        ->assertJsonPath('data.lessons.0.id', $lessons[0]->id);

    expect(test()->visibilityService->isLessonAvailable($enrollment, $course, $lessons[0]))->toBeTrue()
        ->and(test()->visibilityService->isLessonAvailable($enrollment, $course, $lessons[1]))->toBeFalse();
});

it('unlocks hybrid enrollment lesson only after effective session end plus one hour', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-01-02 12:30:00'));

    $course = lessonVisibilityCourse();
    $lessons = lessonVisibilityLessons($course, 1);
    $group = lessonVisibilityGroup($course);
    $schedule = lessonVisibilitySchedule($group);
    lessonVisibilitySession($group, $schedule, [
        'session_date' => '2026-01-02',
        'status' => 'completed',
    ]);
    ['user' => $user] = lessonVisibilityStudentEnrollment($course, $group->id, 'hybrid');
    Sanctum::actingAs($user, ['*']);

    $this->getJson("/api/student/dashboard/courses/{$course->id}")
        ->assertOk()
        ->assertJsonCount(0, 'data.lessons');

    Carbon::setTestNow(Carbon::parse('2026-01-02 13:00:00'));

    $this->getJson("/api/student/dashboard/courses/{$course->id}")
        ->assertOk()
        ->assertJsonCount(1, 'data.lessons')
        ->assertJsonPath('data.lessons.0.id', $lessons[0]->id);
});

it('unlocks null attendance type enrollment lesson after effective session end plus one hour', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-01-02 12:30:00'));

    $course = lessonVisibilityCourse();
    $lessons = lessonVisibilityLessons($course, 1);
    $group = lessonVisibilityGroup($course);
    $schedule = lessonVisibilitySchedule($group);
    lessonVisibilitySession($group, $schedule, [
        'session_date' => '2026-01-02',
        'status' => 'completed',
    ]);
    ['user' => $user] = lessonVisibilityStudentEnrollment($course, $group->id, null);
    Sanctum::actingAs($user, ['*']);

    $this->getJson("/api/student/dashboard/courses/{$course->id}")
        ->assertOk()
        ->assertJsonCount(0, 'data.lessons');

    Carbon::setTestNow(Carbon::parse('2026-01-02 13:00:00'));

    $this->getJson("/api/student/dashboard/courses/{$course->id}")
        ->assertOk()
        ->assertJsonCount(1, 'data.lessons')
        ->assertJsonPath('data.lessons.0.id', $lessons[0]->id);
});

it('does not unlock lesson for cancelled session without release flag', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-01-02 14:00:00'));

    $course = lessonVisibilityCourse();
    $lessons = lessonVisibilityLessons($course, 1);
    $group = lessonVisibilityGroup($course);
    $schedule = lessonVisibilitySchedule($group);
    lessonVisibilitySession($group, $schedule, [
        'session_date' => '2026-01-02',
        'status' => 'cancelled',
        'release_lesson_on_cancel' => false,
    ]);
    ['user' => $user] = lessonVisibilityStudentEnrollment($course, $group->id, 'offline');
    Sanctum::actingAs($user, ['*']);

    $this->getJson("/api/student/dashboard/courses/{$course->id}")
        ->assertOk()
        ->assertJsonCount(0, 'data.lessons');

    $this->postJson("/api/student/lessons/{$lessons[0]->id}/playback")
        ->assertForbidden()
        ->assertJsonPath('message', 'This lesson is not yet available.');
});

it('unlocks lesson for cancelled session when release flag is true after effective end plus one hour', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-01-02 13:00:00'));

    $course = lessonVisibilityCourse();
    $lessons = lessonVisibilityLessons($course, 1);
    $group = lessonVisibilityGroup($course);
    $schedule = lessonVisibilitySchedule($group);
    lessonVisibilitySession($group, $schedule, [
        'session_date' => '2026-01-02',
        'status' => 'cancelled',
        'release_lesson_on_cancel' => true,
    ]);
    ['user' => $user] = lessonVisibilityStudentEnrollment($course, $group->id, 'offline');
    Sanctum::actingAs($user, ['*']);

    $this->getJson("/api/student/dashboard/courses/{$course->id}")
        ->assertOk()
        ->assertJsonCount(1, 'data.lessons')
        ->assertJsonPath('data.lessons.0.id', $lessons[0]->id);
});

it('preserves positional index when first session is cancelled without release', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-01-03 13:00:00'));

    $course = lessonVisibilityCourse();
    $lessons = lessonVisibilityLessons($course, 3);
    $group = lessonVisibilityGroup($course);
    $schedule = lessonVisibilitySchedule($group);

    lessonVisibilitySession($group, $schedule, [
        'session_date' => '2026-01-01',
        'status' => 'cancelled',
        'release_lesson_on_cancel' => false,
    ]);
    lessonVisibilitySession($group, $schedule, [
        'session_date' => '2026-01-02',
        'status' => 'completed',
    ]);
    lessonVisibilitySession($group, $schedule, [
        'session_date' => '2026-01-03',
        'status' => 'completed',
    ]);

    ['user' => $user, 'enrollment' => $enrollment] = lessonVisibilityStudentEnrollment($course, $group->id, 'offline');
    Sanctum::actingAs($user, ['*']);

    $this->getJson("/api/student/dashboard/courses/{$course->id}")
        ->assertOk()
        ->assertJsonCount(2, 'data.lessons')
        ->assertJsonPath('data.lessons.0.id', $lessons[1]->id)
        ->assertJsonPath('data.lessons.1.id', $lessons[2]->id);

    expect(test()->visibilityService->isLessonAvailable($enrollment, $course, $lessons[0]))->toBeFalse()
        ->and(test()->visibilityService->isLessonAvailable($enrollment, $course, $lessons[1]))->toBeTrue()
        ->and(test()->visibilityService->isLessonAvailable($enrollment, $course, $lessons[2]))->toBeTrue();
});

it('preserves positional index when first session is cancelled with release flag', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-01-03 13:00:00'));

    $course = lessonVisibilityCourse();
    $lessons = lessonVisibilityLessons($course, 3);
    $group = lessonVisibilityGroup($course);
    $schedule = lessonVisibilitySchedule($group);

    lessonVisibilitySession($group, $schedule, [
        'session_date' => '2026-01-01',
        'status' => 'cancelled',
        'release_lesson_on_cancel' => true,
    ]);
    lessonVisibilitySession($group, $schedule, [
        'session_date' => '2026-01-02',
        'status' => 'completed',
    ]);
    lessonVisibilitySession($group, $schedule, [
        'session_date' => '2026-01-03',
        'status' => 'completed',
    ]);

    ['user' => $user, 'enrollment' => $enrollment] = lessonVisibilityStudentEnrollment($course, $group->id, 'offline');
    Sanctum::actingAs($user, ['*']);

    $this->getJson("/api/student/dashboard/courses/{$course->id}")
        ->assertOk()
        ->assertJsonCount(3, 'data.lessons')
        ->assertJsonPath('data.lessons.0.id', $lessons[0]->id)
        ->assertJsonPath('data.lessons.1.id', $lessons[1]->id)
        ->assertJsonPath('data.lessons.2.id', $lessons[2]->id);

    expect(test()->visibilityService->isLessonAvailable($enrollment, $course, $lessons[0]))->toBeTrue();
});

it('hides lessons without a corresponding session when lessons exceed sessions', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-01-03 13:00:00'));

    $course = lessonVisibilityCourse();
    $lessons = lessonVisibilityLessons($course, 3);
    $group = lessonVisibilityGroup($course);
    $schedule = lessonVisibilitySchedule($group);

    lessonVisibilitySession($group, $schedule, [
        'session_date' => '2026-01-02',
        'status' => 'completed',
    ]);
    lessonVisibilitySession($group, $schedule, [
        'session_date' => '2026-01-03',
        'status' => 'completed',
    ]);

    ['user' => $user, 'enrollment' => $enrollment] = lessonVisibilityStudentEnrollment($course, $group->id, 'offline');
    Sanctum::actingAs($user, ['*']);

    $this->getJson("/api/student/dashboard/courses/{$course->id}")
        ->assertOk()
        ->assertJsonCount(2, 'data.lessons')
        ->assertJsonPath('data.lessons.0.id', $lessons[0]->id)
        ->assertJsonPath('data.lessons.1.id', $lessons[1]->id);

    expect(test()->visibilityService->isLessonAvailable($enrollment, $course, $lessons[2]))->toBeFalse();
});

it('ignores extra sessions when sessions exceed lessons', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-01-04 13:00:00'));

    $course = lessonVisibilityCourse();
    $lessons = lessonVisibilityLessons($course, 2);
    $group = lessonVisibilityGroup($course);
    $schedule = lessonVisibilitySchedule($group);

    lessonVisibilitySession($group, $schedule, [
        'session_date' => '2026-01-02',
        'status' => 'completed',
    ]);
    lessonVisibilitySession($group, $schedule, [
        'session_date' => '2026-01-03',
        'status' => 'completed',
    ]);
    lessonVisibilitySession($group, $schedule, [
        'session_date' => '2026-01-04',
        'status' => 'completed',
    ]);

    ['user' => $user] = lessonVisibilityStudentEnrollment($course, $group->id, 'offline');
    Sanctum::actingAs($user, ['*']);

    $this->getJson("/api/student/dashboard/courses/{$course->id}")
        ->assertOk()
        ->assertJsonCount(2, 'data.lessons')
        ->assertJsonPath('data.lessons.0.id', $lessons[0]->id)
        ->assertJsonPath('data.lessons.1.id', $lessons[1]->id);
});

it('persists release_lesson_on_cancel true on admin cancel endpoint', function (): void {
    $course = lessonVisibilityCourse();
    $group = lessonVisibilityGroup($course);
    $schedule = lessonVisibilitySchedule($group);
    $session = lessonVisibilitySession($group, $schedule, [
        'session_date' => now()->addDay()->toDateString(),
        'status' => 'upcoming',
    ]);

    $admin = User::factory()->create();
    $admin->assignRole('admin');
    Sanctum::actingAs($admin, ['*']);

    $this->deleteJson("/api/admin/schedule/{$session->id}", [
        'reason' => 'Weather',
        'release_lesson_on_cancel' => true,
    ])->assertOk();

    expect($session->fresh())
        ->status->toBe('cancelled')
        ->release_lesson_on_cancel->toBeTrue()
        ->cancellation_reason->toBe('Weather');
});

it('persists release_lesson_on_cancel false on admin cancel endpoint', function (): void {
    $course = lessonVisibilityCourse();
    $group = lessonVisibilityGroup($course);
    $schedule = lessonVisibilitySchedule($group);
    $session = lessonVisibilitySession($group, $schedule, [
        'session_date' => now()->addDay()->toDateString(),
        'status' => 'upcoming',
    ]);

    $admin = User::factory()->create();
    $admin->assignRole('admin');
    Sanctum::actingAs($admin, ['*']);

    $this->deleteJson("/api/admin/schedule/{$session->id}", [
        'release_lesson_on_cancel' => false,
    ])->assertOk();

    expect($session->fresh())
        ->status->toBe('cancelled')
        ->release_lesson_on_cancel->toBeFalse();
});

it('defaults release_lesson_on_cancel to false when omitted on admin cancel endpoint', function (): void {
    $course = lessonVisibilityCourse();
    $group = lessonVisibilityGroup($course);
    $schedule = lessonVisibilitySchedule($group);
    $session = lessonVisibilitySession($group, $schedule, [
        'session_date' => now()->addDay()->toDateString(),
        'status' => 'upcoming',
    ]);

    $admin = User::factory()->create();
    $admin->assignRole('admin');
    Sanctum::actingAs($admin, ['*']);

    $this->deleteJson("/api/admin/schedule/{$session->id}", [
        'reason' => 'No release',
    ])->assertOk();

    expect($session->fresh())
        ->status->toBe('cancelled')
        ->release_lesson_on_cancel->toBeFalse();
});

it('rejects invalid release_lesson_on_cancel values on admin cancel endpoint', function (): void {
    $course = lessonVisibilityCourse();
    $group = lessonVisibilityGroup($course);
    $schedule = lessonVisibilitySchedule($group);
    $session = lessonVisibilitySession($group, $schedule, [
        'session_date' => now()->addDay()->toDateString(),
        'status' => 'upcoming',
    ]);

    $admin = User::factory()->create();
    $admin->assignRole('admin');
    Sanctum::actingAs($admin, ['*']);

    $this->deleteJson("/api/admin/schedule/{$session->id}", [
        'release_lesson_on_cancel' => 'yes',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['release_lesson_on_cancel']);

    expect($session->fresh()->status)->toBe('upcoming');
});

it('reassigns lessons after reschedule changes session chronological order', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-01-02 13:00:00'));

    $course = lessonVisibilityCourse();
    $lessons = lessonVisibilityLessons($course, 2);
    $group = lessonVisibilityGroup($course);
    $schedule = lessonVisibilitySchedule($group);

    $sessionOne = lessonVisibilitySession($group, $schedule, [
        'session_date' => '2026-01-02',
        'status' => 'completed',
    ]);
    $sessionTwo = lessonVisibilitySession($group, $schedule, [
        'session_date' => '2026-01-04',
        'status' => 'upcoming',
    ]);

    ['user' => $user] = lessonVisibilityStudentEnrollment($course, $group->id, 'offline');
    Sanctum::actingAs($user, ['*']);

    $this->getJson("/api/student/dashboard/courses/{$course->id}")
        ->assertOk()
        ->assertJsonCount(1, 'data.lessons')
        ->assertJsonPath('data.lessons.0.id', $lessons[0]->id);

    app(AdminScheduleService::class)->rescheduleSession($sessionTwo, [
        'date' => '2026-01-01',
        'start_time' => '10:00',
        'end_time' => '12:00',
    ]);

    $this->getJson("/api/student/dashboard/courses/{$course->id}")
        ->assertOk()
        ->assertJsonCount(2, 'data.lessons')
        ->assertJsonPath('data.lessons.0.id', $lessons[0]->id)
        ->assertJsonPath('data.lessons.1.id', $lessons[1]->id);
});

it('returns false from isLessonAvailable when enrollment and lesson belong to different courses', function (): void {
    $courseA = lessonVisibilityCourse();
    $courseB = lessonVisibilityCourse();
    $lessonB = Lesson::factory()->create(['course_id' => $courseB->id, 'sort_order' => 1]);
    ['enrollment' => $enrollmentA] = lessonVisibilityStudentEnrollment($courseA, null, 'online');

    expect(test()->visibilityService->isLessonAvailable($enrollmentA, $courseA, $lessonB))->toBeFalse();
});

it('uses a single session query when resolving visible lessons', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-01-02 13:00:00'));

    $course = lessonVisibilityCourse();
    lessonVisibilityLessons($course, 3);
    $group = lessonVisibilityGroup($course);
    $schedule = lessonVisibilitySchedule($group);
    lessonVisibilitySession($group, $schedule, [
        'session_date' => '2026-01-02',
        'status' => 'completed',
    ]);
    lessonVisibilitySession($group, $schedule, [
        'session_date' => '2026-01-01',
        'status' => 'completed',
    ]);
    ['enrollment' => $enrollment] = lessonVisibilityStudentEnrollment($course, $group->id, 'offline');
    $allLessons = $course->lessons()->active()->ordered()->get();

    $sessionQueries = 0;

    DB::listen(function ($query) use (&$sessionQueries): void {
        if (str_contains($query->sql, 'attendance_sessions')) {
            $sessionQueries++;
        }
    });

    test()->visibilityService->resolveVisibleLessons($enrollment, $course, $allLessons);

    expect($sessionQueries)->toBe(1);
});

it('authorizes playback for online enrollment and blocks offline locked lesson', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-01-02 11:00:00'));

    $course = lessonVisibilityCourse();
    $lessons = lessonVisibilityLessons($course, 1);
    $group = lessonVisibilityGroup($course);
    $schedule = lessonVisibilitySchedule($group);
    lessonVisibilitySession($group, $schedule, [
        'session_date' => '2026-01-02',
        'status' => 'completed',
    ]);

    ['user' => $onlineUser] = lessonVisibilityStudentEnrollment($course, null, 'online');
    Sanctum::actingAs($onlineUser, ['*']);

    $this->mock(GoogleDriveStreamingService::class, function ($mock): void {
        $mock->shouldReceive('getFileMetadata')->andReturn([
            'id' => '1abcDEF_test_file_id',
            'mimeType' => 'video/mp4',
            'size' => 1024,
        ]);
        $mock->shouldReceive('assertPlayableMimeType')->with('video/mp4');
        $mock->shouldReceive('normalizePlayableMimeType')->with('video/mp4')->andReturn('video/mp4');
    });

    $this->postJson("/api/student/lessons/{$lessons[0]->id}/playback")
        ->assertOk();

    ['user' => $offlineUser] = lessonVisibilityStudentEnrollment($course, $group->id, 'offline');
    Sanctum::actingAs($offlineUser, ['*']);

    $this->postJson("/api/student/lessons/{$lessons[0]->id}/playback")
        ->assertForbidden()
        ->assertJsonPath('message', 'This lesson is not yet available.');
});
