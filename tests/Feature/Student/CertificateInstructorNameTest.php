<?php

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Instructor;
use App\Models\LearningGroup;
use App\Models\Order;
use App\Models\Student;
use App\Models\User;
use App\Services\User\CertificateService;
use App\Support\CourseInstructorSync;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createCertificateEnrollment(Course $course, ?int $groupId = null): Enrollment
{
    $student = Student::factory()->create(['user_id' => User::factory()->create()->id]);
    $order = Order::factory()->create([
        'student_id' => $student->id,
        'status' => 'completed',
    ]);

    return Enrollment::create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'order_id' => $order->id,
        'group_id' => $groupId,
        'price_paid' => 500,
        'is_completed' => true,
        'completed_at' => now(),
    ]);
}

function courseWithTwoInstructorsForCertificate(): array
{
    $primary = Instructor::factory()->create(['full_name' => 'Primary Instructor']);
    $secondary = Instructor::factory()->create(['full_name' => 'Secondary Instructor']);

    $course = Course::factory()->create([
        'instructor_id' => $primary->id,
        'status' => 'published',
        'published_at' => now(),
    ]);

    app(CourseInstructorSync::class)->sync($course, [$primary->id, $secondary->id]);

    return [
        'course' => $course,
        'primary' => $primary,
        'secondary' => $secondary,
        'secondaryPivotId' => courseInstructorIdFor($course, $secondary),
    ];
}

function resolveCertificateInstructorName(Enrollment $enrollment): string
{
    $service = app(CertificateService::class);
    $method = new ReflectionMethod(CertificateService::class, 'getInstructorNameForEnrollment');
    $method->setAccessible(true);

    return $method->invoke($service, $enrollment);
}

it('uses the group instructor on the certificate when enrollment has a group', function (): void {
    [
        'course' => $course,
        'primary' => $primary,
        'secondary' => $secondary,
        'secondaryPivotId' => $secondaryPivotId,
    ] = courseWithTwoInstructorsForCertificate();

    $group = LearningGroup::create([
        'group_name' => 'Certificate Batch',
        'course_id' => $course->id,
        'course_instructor_id' => $secondaryPivotId,
        'start_date' => now()->toDateString(),
        'end_date' => now()->addWeeks(4)->toDateString(),
        'status' => 'active',
    ]);

    $enrollment = createCertificateEnrollment($course, $group->id);

    expect(resolveCertificateInstructorName($enrollment))->toBe($secondary->full_name);
});

it('uses the primary course instructor on the certificate when enrollment has no group', function (): void {
    [
        'course' => $course,
        'primary' => $primary,
        'secondary' => $secondary,
    ] = courseWithTwoInstructorsForCertificate();

    $enrollment = createCertificateEnrollment($course);

    expect(resolveCertificateInstructorName($enrollment))->toBe($primary->full_name);
});
