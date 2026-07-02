<?php

namespace App\Services\User;

use App\Http\Requests\Api\Student\StoreCourseReviewRequest;
use App\Models\Course;
use App\Models\CourseReview;
use App\Models\Enrollment;
use App\Models\Student;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class CourseReviewService
{
    public function __construct(
        private readonly CertificateService $certificateService,
    ) {}

    /**
     * Latest accepted public reviews with rating >= 4 (max 5).
     */
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
            ->with([
                'student:id,avatar,full_name',
                'course:id,title',
                'instructor:id,full_name',
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

    /**
     * هات reviews الخاصة بكورس معين
     * مفيش اي n+1 problem
     */
    public function getCourseReviews(int $courseId): Collection
    {
        return CourseReview::active()
            ->with([
                'student:id,avatar,full_name',
                'instructor:id,full_name',
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
            ->first();

        $existingReview = $this->getStudentReviewForCourse($student, $courseId);

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
            ->select(['id', 'instructor_id'])
            ->findOrFail($courseId);

        if (! $course->instructor_id) {
            throw new UnprocessableEntityHttpException('Course instructor is not configured.');
        }

        $contentRating = $this->averageRatings($ratings, StoreCourseReviewRequest::courseQuestionIds());
        $centerRating = $this->averageRatings($ratings, StoreCourseReviewRequest::centerQuestionIds());
        $instructorRating = $this->averageRatings($ratings, StoreCourseReviewRequest::instructorQuestionIds());

        return DB::transaction(function () use (
            $student,
            $course,
            $enrollment,
            $contentRating,
            $centerRating,
            $instructorRating,
            $validated
        ) {
            $review = CourseReview::create([
                'course_id' => $course->id,
                'student_id' => $student->id,
                'instructor_id' => $course->instructor_id,
                'content_rating' => $contentRating,
                'center_rating' => $centerRating,
                'instructor_rating' => $instructorRating,
                'overall_comment' => $validated['overall_comment'],
                'review_status' => CourseReview::REVIEW_STATUS_PENDING,
            ]);

            $certificateIssued = $this->certificateService->issueCertificateAndNotify($enrollment);

            $review->certificate_issued = $certificateIssued;

            return [
                'review' => $review,
                'certificate_issued' => $certificateIssued,
            ];
        });
    }

    /**
     * @param  array<string, int>  $ratings
     * @param  array<int, string>  $questionIds
     */
    private function averageRatings(array $ratings, array $questionIds): float
    {
        $values = array_map(
            fn(string $id) => (int) ($ratings[$id] ?? 0),
            $questionIds
        );

        return round(array_sum($values) / count($values), 2);
    }
}
