<?php

namespace App\Services\User;

use App\Http\Requests\Api\Student\StoreCourseReviewRequest;
use App\Models\Course;
use App\Models\CourseInstructor;
use App\Models\CourseReview;
use App\Models\CourseReviewInstructorRating;
use App\Models\Enrollment;
use App\Models\Student;
use App\Support\CourseInstructorSync;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class CourseReviewService
{
    public function __construct(
        private readonly CertificateService $certificateService,
    ) {}

    public function getPublicFeaturedReviews(): Collection
    {
        return $this->publicReviewQuery()
            ->where('rating', '>=', 4)
            ->latest()
            ->limit(5)
            ->get();
    }

    private function publicReviewQuery()
    {
        return CourseReview::active()
            ->whereHas('course.category', fn ($q) => $q->where('status', 'active'))
            ->with([
                'student:id,avatar,full_name',
                'course:id,title',
                'instructor:id,full_name',
                'instructorRatings.courseInstructor.instructor:id,full_name',
            ])
            ->select([
                'id',
                'course_id',
                'student_id',
                'instructor_id',
                'rating',
                'overall_comment',
                'created_at',
                'review_status',
                'status',
            ]);
    }

    public function getCourseReviews(int $courseId): Collection
    {
        return CourseReview::active()
            ->with([
                'student:id,avatar,full_name',
                'instructor:id,full_name',
                'instructorRatings.courseInstructor.instructor:id,full_name',
            ])
            ->where('course_id', $courseId)
            ->select([
                'id',
                'course_id',
                'student_id',
                'instructor_id',
                'rating',
                'overall_comment',
                'created_at',
                'review_status',
            ])
            ->latest()
            ->limit(5)
            ->get();
    }

    public function getStudentReviewForCourse(Student $student, int $courseId): ?CourseReview
    {
        return CourseReview::query()
            ->where('student_id', $student->id)
            ->where('course_id', $courseId)
            ->first();
    }

    public function getReviewEligibility(Student $student, int $courseId): array
    {
        $enrollment = Enrollment::query()
            ->where('student_id', $student->id)
            ->where('course_id', $courseId)
            ->with(['learningGroup:id,course_id,course_instructor_id'])
            ->first();

        $existingReview = $this->getStudentReviewForCourse($student, $courseId);

        $course = Course::query()
            ->with(['instructors:id,full_name,avatar,field,bio,phone'])
            ->find($courseId);

        return [
            'course_id' => $courseId,
            'is_enrolled' => $enrollment !== null,
            'is_completed' => (bool) ($enrollment?->is_completed),
            'has_review' => $existingReview !== null,
            'review_status' => $existingReview?->review_status,
            'can_submit' => $enrollment?->is_completed && $existingReview === null,
            'certificate_available' => $enrollment
                ? $this->certificateService->enrollmentCanIssueCertificate($enrollment)
                : false,
            'instructors' => $course
                ? CourseInstructorSync::resolveForEnrollment($course, $enrollment)
                : [],
        ];
    }

    /**
     * @return array{review: CourseReview, certificate_issued: bool}
     */
    public function submitStudentReview(Student $student, array $validated): array
    {
        $courseId = (int) $validated['course_id'];
        $ratings = $validated['ratings'];

        $enrollment = Enrollment::query()
            ->where('student_id', $student->id)
            ->where('course_id', $courseId)
            ->where('is_completed', true)
            ->with(['learningGroup:id,course_id,course_instructor_id'])
            ->first();

        if (! $enrollment) {
            throw new UnprocessableEntityHttpException(
                'You must complete the course before submitting a review.'
            );
        }

        if ($this->getStudentReviewForCourse($student, $courseId)) {
            throw new ConflictHttpException('You have already submitted a review for this course.');
        }

        $course = Course::query()
            ->with(['courseInstructors', 'instructors:id,full_name,avatar,field,bio,phone'])
            ->findOrFail($courseId);

        if ($course->courseInstructors->isEmpty()) {
            throw new UnprocessableEntityHttpException('Course instructors are not configured.');
        }

        $contentRating = $this->averageRatings($ratings, StoreCourseReviewRequest::courseQuestionIds());
        $centerRating = $this->averageRatings($ratings, StoreCourseReviewRequest::centerQuestionIds());
        $perInstructorRatings = $this->resolvePerInstructorRatings($course, $enrollment, $validated, $ratings);
        $instructorRating = round(
            array_sum($perInstructorRatings) / max(1, count($perInstructorRatings)),
            2
        );
        $resolvedInstructors = CourseInstructorSync::resolveForEnrollment($course, $enrollment);
        $primaryInstructorId = $resolvedInstructors[0]['id']
            ?? $course->courseInstructors->first()->instructor_id
            ?? $course->instructor_id;

        return DB::transaction(function () use (
            $student,
            $course,
            $enrollment,
            $contentRating,
            $centerRating,
            $instructorRating,
            $primaryInstructorId,
            $perInstructorRatings,
            $validated
        ) {
            $review = CourseReview::create([
                'course_id' => $course->id,
                'student_id' => $student->id,
                'instructor_id' => $primaryInstructorId,
                'content_rating' => $contentRating,
                'center_rating' => $centerRating,
                'instructor_rating' => $instructorRating,
                'overall_comment' => $validated['overall_comment'],
                'review_status' => CourseReview::REVIEW_STATUS_PENDING,
            ]);

            foreach ($perInstructorRatings as $courseInstructorId => $rating) {
                CourseReviewInstructorRating::create([
                    'course_review_id' => $review->id,
                    'course_instructor_id' => $courseInstructorId,
                    'instructor_rating' => $rating,
                ]);
            }

            $certificateIssued = $this->certificateService->issueCertificateAndNotify($enrollment);

            $review->certificate_issued = $certificateIssued;

            return [
                'review' => $review,
                'certificate_issued' => $certificateIssued,
            ];
        });
    }

    /**
     * @return array<int, float> keyed by course_instructor_id
     */
    private function resolvePerInstructorRatings(
        Course $course,
        ?Enrollment $enrollment,
        array $validated,
        array $ratings
    ): array {
        $allowedCourseInstructorIds = CourseInstructorSync::allowedCourseInstructorIdsForEnrollment(
            $course,
            $enrollment
        );

        if ($allowedCourseInstructorIds === []) {
            $allowedCourseInstructorIds = $course->courseInstructors
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        $entries = $validated['instructor_ratings'] ?? null;

        if (is_array($entries) && $entries !== []) {
            $resolved = [];

            foreach ($entries as $entry) {
                $courseInstructorId = (int) ($entry['course_instructor_id'] ?? 0);

                if (! in_array($courseInstructorId, $allowedCourseInstructorIds, true)) {
                    throw new UnprocessableEntityHttpException(
                        'One or more instructor ratings reference an instructor you are not assigned to.'
                    );
                }

                $resolved[$courseInstructorId] = $this->averageRatings(
                    $entry['ratings'] ?? [],
                    StoreCourseReviewRequest::instructorQuestionIds()
                );
            }

            if (count($resolved) !== count($allowedCourseInstructorIds)) {
                throw new UnprocessableEntityHttpException(
                    'Please rate every instructor assigned to you for this course.'
                );
            }

            return $resolved;
        }

        $legacyRating = $this->averageRatings($ratings, StoreCourseReviewRequest::instructorQuestionIds());

        return collect($allowedCourseInstructorIds)
            ->mapWithKeys(fn ($id) => [$id => $legacyRating])
            ->all();
    }

    private function averageRatings(array $ratings, array $questionIds): float
    {
        $values = array_map(
            fn (string $id) => (int) ($ratings[$id] ?? 0),
            $questionIds
        );

        return round(array_sum($values) / count($values), 2);
    }
}
