<?php

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Instructor;
use App\Models\LearningGroup;
use App\Models\Order;
use App\Models\Student;
use App\Services\Admin\AdminLearningGroupService;
use App\Services\Enrollment\OnlineEnrollmentCompletionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->instructor = Instructor::factory()->create();

    $this->course = Course::factory()->create([
        'instructor_id' => $this->instructor->id,
        'duration_weeks' => 4,
    ]);
});

function createOnlineEnrollmentForCompletionTest(
    Course $course,
    string $orderStatus = 'completed',
    ?Carbon $statusChangedAt = null,
    ?int $groupId = null,
    bool $isCompleted = false,
    ?string $attendanceType = 'online',
): Enrollment {
    $student = Student::factory()->create();

    $order = Order::create([
        'student_id' => $student->id,
        'total_amount' => 500,
        'status' => $orderStatus,
        'billing_name' => 'Test Billing',
        'billing_email' => 'billing@test.com',
        'billing_phone' => '01000000000',
    ]);

    if ($statusChangedAt !== null) {
        $order->forceFill(['status_changed_at' => $statusChangedAt])->save();
    }

    return Enrollment::create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'order_id' => $order->id,
        'group_id' => $groupId,
        'attendance_type' => $attendanceType,
        'price_paid' => 500,
        'is_completed' => $isCompleted,
        'completed_at' => $isCompleted ? now() : null,
    ]);
}

it('auto-completes eligible online enrollments through the command', function (): void {
    Carbon::setTestNow('2026-03-01 12:00:00');

    $eligible = createOnlineEnrollmentForCompletionTest(
        $this->course,
        'completed',
        Carbon::parse('2026-01-01 12:00:00'),
        null,
        false,
        'online'
    );

    $this->artisan('enrollments:complete-online')
        ->assertSuccessful()
        ->expectsOutput('Completed 1 online enrollment(s).');

    $eligible->refresh();

    expect($eligible->is_completed)->toBeTrue();
    expect($eligible->completed_at)->not->toBeNull();
    expect($eligible->group_id)->toBeNull();
});

it('does not auto-complete online enrollments before duration has elapsed', function (): void {
    Carbon::setTestNow('2026-02-01 12:00:00');

    $notEligible = createOnlineEnrollmentForCompletionTest(
        $this->course,
        'completed',
        Carbon::parse('2026-01-15 12:00:00'),
        null,
        false,
        'online'
    );

    $this->artisan('enrollments:complete-online')->assertSuccessful();

    $notEligible->refresh();

    expect($notEligible->is_completed)->toBeFalse();
});

it('does not auto-complete online enrollments with pending orders', function (): void {
    Carbon::setTestNow('2026-03-01 12:00:00');

    $pending = createOnlineEnrollmentForCompletionTest(
        $this->course,
        'pending',
        Carbon::parse('2026-01-01 12:00:00'),
        null,
        false,
        'online'
    );

    $this->artisan('enrollments:complete-online')->assertSuccessful();

    $pending->refresh();

    expect($pending->is_completed)->toBeFalse();
});

it('does not auto-complete offline enrollments even when duration elapsed', function (): void {
    Carbon::setTestNow('2026-03-01 12:00:00');

    $offline = createOnlineEnrollmentForCompletionTest(
        $this->course,
        'completed',
        Carbon::parse('2026-01-01 12:00:00'),
        null,
        false,
        'offline'
    );

    $this->artisan('enrollments:complete-online')->assertSuccessful();

    $offline->refresh();

    expect($offline->is_completed)->toBeFalse();
});

it('does not auto-complete hybrid enrollments even when duration elapsed', function (): void {
    Carbon::setTestNow('2026-03-01 12:00:00');

    $hybrid = createOnlineEnrollmentForCompletionTest(
        $this->course,
        'completed',
        Carbon::parse('2026-01-01 12:00:00'),
        null,
        false,
        'hybrid'
    );

    $this->artisan('enrollments:complete-online')->assertSuccessful();

    $hybrid->refresh();

    expect($hybrid->is_completed)->toBeFalse();
});

it('does not process already completed online enrollments again', function (): void {
    Carbon::setTestNow('2026-03-01 12:00:00');

    $completed = createOnlineEnrollmentForCompletionTest(
        $this->course,
        'completed',
        Carbon::parse('2026-01-01 12:00:00'),
        null,
        true,
        'online'
    );

    $originalCompletedAt = $completed->completed_at;

    $this->artisan('enrollments:complete-online')
        ->assertSuccessful()
        ->expectsOutput('Completed 0 online enrollment(s).');

    $completed->refresh();

    expect($completed->is_completed)->toBeTrue();
    expect($completed->completed_at?->eq($originalCompletedAt))->toBeTrue();
});

it('processes mixed eligible and ineligible online enrollments in one run', function (): void {
    Carbon::setTestNow('2026-03-01 12:00:00');

    $eligible = createOnlineEnrollmentForCompletionTest(
        $this->course,
        'completed',
        Carbon::parse('2026-01-01 12:00:00'),
        null,
        false,
        'online'
    );
    $tooRecent = createOnlineEnrollmentForCompletionTest(
        $this->course,
        'completed',
        Carbon::parse('2026-02-15 12:00:00'),
        null,
        false,
        'online'
    );

    $this->artisan('enrollments:complete-online')
        ->assertSuccessful()
        ->expectsOutput('Completed 1 online enrollment(s).');

    $eligible->refresh();
    $tooRecent->refresh();

    expect($eligible->is_completed)->toBeTrue();
    expect($tooRecent->is_completed)->toBeFalse();
});

it('is idempotent when the command runs twice', function (): void {
    Carbon::setTestNow('2026-03-01 12:00:00');

    $eligible = createOnlineEnrollmentForCompletionTest(
        $this->course,
        'completed',
        Carbon::parse('2026-01-01 12:00:00'),
        null,
        false,
        'online'
    );

    $this->artisan('enrollments:complete-online')->assertSuccessful();
    $eligible->refresh();
    $firstCompletedAt = $eligible->completed_at;

    $this->artisan('enrollments:complete-online')
        ->assertSuccessful()
        ->expectsOutput('Completed 0 online enrollment(s).');

    $eligible->refresh();

    expect($eligible->is_completed)->toBeTrue();
    expect($eligible->completed_at?->eq($firstCompletedAt))->toBeTrue();
});

it('does not complete online enrollments through group status sync', function (): void {
    Carbon::setTestNow('2026-03-01 12:00:00');

    $group = LearningGroup::create([
        'group_name' => 'Legacy Online Group',
        'course_id' => $this->course->id,
        'course_instructor_id' => courseInstructorIdFor($this->course, $this->instructor),
        'start_date' => now()->toDateString(),
        'end_date' => now()->addWeeks(4)->toDateString(),
        'status' => 'active',
        'enrolled_students' => 1,
    ]);

    $onlineInGroup = createOnlineEnrollmentForCompletionTest(
        $this->course,
        'completed',
        Carbon::parse('2026-01-01 12:00:00'),
        $group->id,
        false,
        'online'
    );

    app(AdminLearningGroupService::class)
        ->syncEnrollmentsWithGroupStatus($group, 'active', 'completed');

    $onlineInGroup->refresh();

    expect($onlineInGroup->is_completed)->toBeFalse();
});

it('reports eligibility correctly through the service helper', function (): void {
    Carbon::setTestNow('2026-03-01 12:00:00');

    $eligible = createOnlineEnrollmentForCompletionTest(
        $this->course,
        'completed',
        Carbon::parse('2026-01-01 12:00:00'),
        null,
        false,
        'online'
    );

    $service = app(OnlineEnrollmentCompletionService::class);

    expect($service->isEligibleForAutoCompletion($eligible->load(['order', 'course'])))->toBeTrue();
    expect($service->completeEligibleEnrollments())->toBe(1);
});
