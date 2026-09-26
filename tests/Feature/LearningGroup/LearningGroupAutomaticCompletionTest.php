<?php

use App\Models\Course;
use App\Models\CourseReview;
use App\Models\Enrollment;
use App\Models\Instructor;
use App\Models\LearningGroup;
use App\Models\Order;
use App\Models\Student;
use App\Notifications\CourseReviewRequired;
use App\Services\Admin\AdminLearningGroupService;
use App\Services\Admin\LearningGroupCompletionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->instructor = Instructor::factory()->create();
    $this->course = Course::factory()->create([
        'instructor_id' => $this->instructor->id,
        'duration_weeks' => 4,
    ]);
});

function autoCompletionGroup(
    Course $course,
    Instructor $instructor,
    string $status,
    ?string $endDate,
    string $name = 'Auto Batch',
): LearningGroup {
    return LearningGroup::create([
        'group_name' => $name,
        'course_id' => $course->id,
        'course_instructor_id' => courseInstructorIdFor($course, $instructor),
        'start_date' => now()->subWeeks(8)->toDateString(),
        'end_date' => $endDate,
        'status' => $status,
        'enrolled_students' => 0,
    ]);
}

function autoCompletionEnrollment(LearningGroup $group, bool $isCompleted = false): Enrollment
{
    $student = Student::factory()->create();

    $order = Order::create([
        'student_id' => $student->id,
        'total_amount' => 500,
        'status' => 'completed',
        'billing_name' => 'Test Billing',
        'billing_email' => 'billing@test.com',
        'billing_phone' => '01000000000',
    ]);

    return Enrollment::create([
        'student_id' => $student->id,
        'course_id' => $group->course_id,
        'order_id' => $order->id,
        'group_id' => $group->id,
        'price_paid' => 500,
        'is_completed' => $isCompleted,
        'completed_at' => $isCompleted ? now() : null,
    ]);
}

it('completes an expired active group and its incomplete enrollments', function (): void {
    Carbon::setTestNow('2026-09-26 10:00:00');

    $group = autoCompletionGroup($this->course, $this->instructor, 'active', '2026-09-25');
    $enrollment = autoCompletionEnrollment($group);

    Artisan::call('learning-groups:complete-expired');

    $group->refresh();
    $enrollment->refresh();

    expect($group->status)->toBe('completed');
    expect($enrollment->is_completed)->toBeTrue();
    expect($enrollment->completed_at)->not->toBeNull();

    Carbon::setTestNow();
});

it('leaves a group with end date today active', function (): void {
    Carbon::setTestNow('2026-09-26 10:00:00');

    $group = autoCompletionGroup($this->course, $this->instructor, 'active', '2026-09-26');
    $enrollment = autoCompletionEnrollment($group);

    Artisan::call('learning-groups:complete-expired');

    $group->refresh();
    $enrollment->refresh();

    expect($group->status)->toBe('active');
    expect($enrollment->is_completed)->toBeFalse();

    Carbon::setTestNow();
});

it('leaves a future group active', function (): void {
    Carbon::setTestNow('2026-09-26 10:00:00');

    $group = autoCompletionGroup($this->course, $this->instructor, 'active', '2026-10-01');
    $enrollment = autoCompletionEnrollment($group);

    Artisan::call('learning-groups:complete-expired');

    $group->refresh();
    $enrollment->refresh();

    expect($group->status)->toBe('active');
    expect($enrollment->is_completed)->toBeFalse();

    Carbon::setTestNow();
});

it('ignores active groups with null end date', function (): void {
    Carbon::setTestNow('2026-09-26 10:00:00');

    $group = autoCompletionGroup($this->course, $this->instructor, 'active', null);

    Artisan::call('learning-groups:complete-expired');

    expect($group->fresh()->status)->toBe('active');

    Carbon::setTestNow();
});

it('ignores cancelled groups even when end date is in the past', function (): void {
    Carbon::setTestNow('2026-09-26 10:00:00');

    $group = autoCompletionGroup($this->course, $this->instructor, 'cancelled', '2026-09-01');

    Artisan::call('learning-groups:complete-expired');

    expect($group->fresh()->status)->toBe('cancelled');

    Carbon::setTestNow();
});

it('ignores groups that are already completed', function (): void {
    Carbon::setTestNow('2026-09-26 10:00:00');

    $group = autoCompletionGroup($this->course, $this->instructor, 'completed', '2026-09-01');
    $enrollment = autoCompletionEnrollment($group, true);

    Artisan::call('learning-groups:complete-expired');

    expect($group->fresh()->status)->toBe('completed');
    expect($enrollment->fresh()->is_completed)->toBeTrue();

    Carbon::setTestNow();
});

it('is idempotent on a second run', function (): void {
    Carbon::setTestNow('2026-09-26 10:00:00');
    Notification::fake();

    $group = autoCompletionGroup($this->course, $this->instructor, 'active', '2026-09-25');
    autoCompletionEnrollment($group);

    Artisan::call('learning-groups:complete-expired');
    Artisan::call('learning-groups:complete-expired');

    expect($group->fresh()->status)->toBe('completed');
    Notification::assertSentTimes(CourseReviewRequired::class, 1);

    Carbon::setTestNow();
});

it('processes multiple expired active groups independently', function (): void {
    Carbon::setTestNow('2026-09-26 10:00:00');

    $groupA = autoCompletionGroup($this->course, $this->instructor, 'active', '2026-09-20', 'Batch A');
    $groupB = autoCompletionGroup($this->course, $this->instructor, 'active', '2026-09-21', 'Batch B');
    autoCompletionEnrollment($groupA);
    autoCompletionEnrollment($groupB);

    $result = app(LearningGroupCompletionService::class)->completeExpiredGroups(Carbon::today());

    expect($result->completed)->toBe(2);
    expect($groupA->fresh()->status)->toBe('completed');
    expect($groupB->fresh()->status)->toBe('completed');

    Carbon::setTestNow();
});

it('continues processing other groups when one group transaction fails', function (): void {
    Carbon::setTestNow('2026-09-26 10:00:00');
    Notification::fake();

    $groupA = autoCompletionGroup($this->course, $this->instructor, 'active', '2026-09-20', 'Batch A');
    $groupB = autoCompletionGroup($this->course, $this->instructor, 'active', '2026-09-21', 'Batch B');

    autoCompletionEnrollment($groupA);
    $failingEnrollment = autoCompletionEnrollment($groupB);

    $realService = app(AdminLearningGroupService::class);
    $mock = Mockery::mock($realService)->makePartial();
    $mock->shouldReceive('syncEnrollmentsWithGroupStatus')
        ->twice()
        ->andReturnUsing(function ($group, $oldStatus, $newStatus, $sendNotifications = true) use ($realService, $groupB) {
            if ($group->id === $groupB->id) {
                throw new RuntimeException('Simulated completion failure');
            }

            return $realService->syncEnrollmentsWithGroupStatus($group, $oldStatus, $newStatus, $sendNotifications);
        });

    $this->app->instance(AdminLearningGroupService::class, $mock);

    $result = app(LearningGroupCompletionService::class)->completeExpiredGroups(Carbon::today());

    expect($result->completed)->toBe(1);
    expect($result->failed)->toBe(1);
    expect($groupA->fresh()->status)->toBe('completed');
    expect($groupB->fresh()->status)->toBe('active');
    expect($failingEnrollment->fresh()->is_completed)->toBeFalse();
    Notification::assertSentTimes(CourseReviewRequired::class, 1);

    Carbon::setTestNow();
});

it('does not modify data during dry run', function (): void {
    Carbon::setTestNow('2026-09-26 10:00:00');
    Notification::fake();

    $group = autoCompletionGroup($this->course, $this->instructor, 'active', '2026-09-25');
    $enrollment = autoCompletionEnrollment($group);

    Artisan::call('learning-groups:complete-expired', ['--dry-run' => true]);

    expect($group->fresh()->status)->toBe('active');
    expect($enrollment->fresh()->is_completed)->toBeFalse();
    Notification::assertNothingSent();

    Carbon::setTestNow();
});

it('uses an explicit as-of date for eligibility', function (): void {
    $groupEligible = autoCompletionGroup($this->course, $this->instructor, 'active', '2026-09-25');
    $groupToday = autoCompletionGroup($this->course, $this->instructor, 'active', '2026-09-26', 'Today Batch');

    autoCompletionEnrollment($groupEligible);
    autoCompletionEnrollment($groupToday);

    Artisan::call('learning-groups:complete-expired', ['--date' => '2026-09-26']);

    expect($groupEligible->fresh()->status)->toBe('completed');
    expect($groupToday->fresh()->status)->toBe('active');
});

it('sends CourseReviewRequired for newly completed students and skips existing reviews', function (): void {
    Carbon::setTestNow('2026-09-26 10:00:00');
    Notification::fake();

    $group = autoCompletionGroup($this->course, $this->instructor, 'active', '2026-09-25');
    $needsReview = autoCompletionEnrollment($group);
    $alreadyReviewed = autoCompletionEnrollment($group);

    CourseReview::create([
        'course_id' => $this->course->id,
        'student_id' => $alreadyReviewed->student_id,
        'instructor_id' => $this->instructor->id,
        'content_rating' => 4,
        'instructor_rating' => 4,
        'center_rating' => 4,
        'overall_comment' => 'Already reviewed',
    ]);

    Artisan::call('learning-groups:complete-expired');

    Notification::assertSentTo(
        $needsReview->student->user,
        CourseReviewRequired::class
    );

    Notification::assertNotSentTo(
        $alreadyReviewed->student->user,
        CourseReviewRequired::class
    );

    Carbon::setTestNow();
});

it('rejects an invalid date option', function (): void {
    $exitCode = Artisan::call('learning-groups:complete-expired', ['--date' => 'not-a-date']);

    expect($exitCode)->toBe(1);
});
