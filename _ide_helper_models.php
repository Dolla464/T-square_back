<?php

// @formatter:off
// phpcs:ignoreFile
/**
 * A helper file for your Eloquent Models
 * Copy the phpDocs from this file to the correct Model,
 * And remove them from this file, to prevent double declarations.
 *
 * @author Barry vd. Heuvel <barryvdh@gmail.com>
 */


namespace App\Models{
/**
 * @property int $id
 * @property int $user_id
 * @property string $full_name
 * @property string|null $phone
 * @property string|null $avatar
 * @property string|null $gender
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User|null $user
 * @method static \Database\Factories\AdminFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Admin newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Admin newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Admin query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Admin whereAvatar($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Admin whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Admin whereFullName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Admin whereGender($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Admin whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Admin wherePhone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Admin whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Admin whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Admin whereUserId($value)
 */
	class Admin extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $attempt_id
 * @property int $question_id
 * @property int $choice_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\ExamAttempt $attempt
 * @property-read \App\Models\Choice $choice
 * @property-read \App\Models\Question|null $question
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Answer newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Answer newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Answer query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Answer whereAttemptId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Answer whereChoiceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Answer whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Answer whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Answer whereQuestionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Answer whereUpdatedAt($value)
 */
	class Answer extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property string|null $icon
 * @property string|null $image
 * @property int|null $parent_id
 * @property int $sort_order
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Category> $children
 * @property-read int|null $children_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Course> $courses
 * @property-read int|null $courses_count
 * @property-read Category|null $parent
 * @method static \Database\Factories\CategoryFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category whereIcon($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category whereImage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category whereParentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category whereSortOrder($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category whereUpdatedAt($value)
 */
	class Category extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $student_id
 * @property int $course_id
 * @property string $certificate_url
 * @property string $certificate_num
 * @property string $issued_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Course|null $course
 * @property-read \App\Models\Student $student
 * @method static \Database\Factories\CertificateFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Certificate newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Certificate newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Certificate query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Certificate whereCertificateNum($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Certificate whereCertificateUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Certificate whereCourseId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Certificate whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Certificate whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Certificate whereIssuedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Certificate whereStudentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Certificate whereUpdatedAt($value)
 */
	class Certificate extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $question_id
 * @property string $choice_text
 * @property bool $is_correct
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Answer> $chosenBy
 * @property-read int|null $chosen_by_count
 * @property-read \App\Models\Question|null $question
 * @method static \Database\Factories\ChoiceFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Choice newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Choice newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Choice query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Choice whereChoiceText($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Choice whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Choice whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Choice whereIsCorrect($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Choice whereQuestionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Choice whereUpdatedAt($value)
 */
	class Choice extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $title
 * @property string $slug
 * @property string $short_description
 * @property string $description
 * @property string $thumbnail
 * @property string|null $cover_image
 * @property string|null $preview_video
 * @property string|null $google_drive_link
 * @property string $attendance_type
 * @property numeric $price_before
 * @property numeric $discount_price
 * @property numeric|null $price
 * @property string $level
 * @property string $language
 * @property int $duration_weeks
 * @property int $duration_hours
 * @property string $status
 * @property int $is_featured
 * @property int $is_free
 * @property int $category_id
 * @property int $instructor_id
 * @property numeric $avg_rating
 * @property-read int|null $reviews_count
 * @property int $total_reviews
 * @property int $total_students
 * @property numeric $total_revenue
 * @property string|null $published_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Category $category
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Certificate> $certificates
 * @property-read int|null $certificates_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Enrollment> $enrollments
 * @property-read int|null $enrollments_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Exam> $exams
 * @property-read int|null $exams_count
 * @property-read \App\Models\Instructor $instructor
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\LearningGroup> $learningGroups
 * @property-read int|null $learning_groups_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CourseLearning> $learnings
 * @property-read int|null $learnings_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CoursePreview> $previews
 * @property-read int|null $previews_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CourseReview> $reviews
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Student> $students
 * @property-read int|null $students_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Tag> $tags
 * @property-read int|null $tags_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Course active()
 * @method static \Database\Factories\CourseFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Course featured()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Course newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Course newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Course onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Course query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Course whereAttendanceType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Course whereAvgRating($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Course whereCategoryId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Course whereCoverImage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Course whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Course whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Course whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Course whereDiscountPrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Course whereDurationHours($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Course whereDurationWeeks($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Course whereGoogleDriveLink($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Course whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Course whereInstructorId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Course whereIsFeatured($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Course whereIsFree($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Course whereLanguage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Course whereLevel($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Course wherePreviewVideo($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Course wherePrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Course wherePriceBefore($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Course wherePublishedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Course whereReviewsCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Course whereShortDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Course whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Course whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Course whereThumbnail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Course whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Course whereTotalRevenue($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Course whereTotalReviews($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Course whereTotalStudents($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Course whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Course withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Course withoutTrashed()
 */
	class Course extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $course_id
 * @property string $title
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Course|null $course
 * @method static \Database\Factories\CourseLearningFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseLearning newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseLearning newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseLearning query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseLearning whereCourseId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseLearning whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseLearning whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseLearning whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseLearning whereUpdatedAt($value)
 */
	class CourseLearning extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $course_id
 * @property string $title
 * @property string $video_url
 * @property string|null $description
 * @property string $video_provider
 * @property int|null $duration_seconds
 * @property int $sort_order
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Course|null $course
 * @method static \Database\Factories\CoursePreviewFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CoursePreview newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CoursePreview newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CoursePreview query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CoursePreview whereCourseId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CoursePreview whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CoursePreview whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CoursePreview whereDurationSeconds($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CoursePreview whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CoursePreview whereSortOrder($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CoursePreview whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CoursePreview whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CoursePreview whereVideoProvider($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CoursePreview whereVideoUrl($value)
 */
	class CoursePreview extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $course_id
 * @property int $student_id
 * @property int $instructor_id
 * @property numeric $content_rating تقييم المحتوى
 * @property numeric $instructor_rating تقييم المدرب
 * @property numeric $center_rating تقييم المركز والخدمات
 * @property numeric $rating التقييم الكلي
 * @property string|null $overall_comment
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Course|null $course
 * @property-read \App\Models\Instructor $instructor
 * @property-read \App\Models\Student $student
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseReview newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseReview newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseReview query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseReview whereCenterRating($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseReview whereContentRating($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseReview whereCourseId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseReview whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseReview whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseReview whereInstructorId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseReview whereInstructorRating($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseReview whereOverallComment($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseReview whereRating($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseReview whereStudentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseReview whereUpdatedAt($value)
 */
	class CourseReview extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $student_id
 * @property int $course_id
 * @property int|null $order_id
 * @property numeric $price_paid
 * @property bool $is_completed
 * @property \Illuminate\Support\Carbon|null $completed_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Course|null $course
 * @property-read \App\Models\Order|null $order
 * @property-read \App\Models\Student $student
 * @method static \Database\Factories\EnrollmentFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Enrollment newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Enrollment newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Enrollment query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Enrollment whereCompletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Enrollment whereCourseId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Enrollment whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Enrollment whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Enrollment whereIsCompleted($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Enrollment whereOrderId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Enrollment wherePricePaid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Enrollment whereStudentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Enrollment whereUpdatedAt($value)
 */
	class Enrollment extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $course_id
 * @property string $title
 * @property string|null $description
 * @property int $duration Duration in minutes
 * @property numeric $total_marks
 * @property int $is_active
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ExamAttempt> $attempts
 * @property-read int|null $attempts_count
 * @property-read \App\Models\Course|null $course
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Question> $questions
 * @property-read int|null $questions_count
 * @method static \Database\Factories\ExamFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Exam newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Exam newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Exam onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Exam query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Exam whereCourseId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Exam whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Exam whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Exam whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Exam whereDuration($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Exam whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Exam whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Exam whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Exam whereTotalMarks($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Exam whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Exam withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Exam withoutTrashed()
 */
	class Exam extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $student_id
 * @property int $exam_id
 * @property \Illuminate\Support\Carbon|null $started_at
 * @property \Illuminate\Support\Carbon|null $finished_at
 * @property numeric $score Final Result
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Answer> $answers
 * @property-read int|null $answers_count
 * @property-read \App\Models\Exam|null $exam
 * @property-read mixed $duration
 * @property-read \App\Models\Student $student
 * @method static \Database\Factories\ExamAttemptFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamAttempt newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamAttempt newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamAttempt query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamAttempt whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamAttempt whereExamId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamAttempt whereFinishedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamAttempt whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamAttempt whereScore($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamAttempt whereStartedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamAttempt whereStudentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamAttempt whereUpdatedAt($value)
 */
	class ExamAttempt extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $user_id
 * @property string $full_name
 * @property string|null $phone
 * @property string|null $avatar
 * @property string $bio
 * @property string|null $gender
 * @property string|null $insta_url
 * @property string|null $linkedin_url
 * @property string|null $facebook_url
 * @property string $status
 * @property numeric $avg_rating
 * @property-read int|null $reviews_count
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Course> $courses
 * @property-read int|null $courses_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\LearningGroup> $learningGroups
 * @property-read int|null $learning_groups_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CourseReview> $reviews
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Student> $students
 * @property-read int|null $students_count
 * @property-read \App\Models\User|null $user
 * @method static \Database\Factories\InstructorFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Instructor newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Instructor newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Instructor query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Instructor whereAvatar($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Instructor whereAvgRating($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Instructor whereBio($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Instructor whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Instructor whereFacebookUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Instructor whereFullName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Instructor whereGender($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Instructor whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Instructor whereInstaUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Instructor whereLinkedinUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Instructor wherePhone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Instructor whereReviewsCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Instructor whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Instructor whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Instructor whereUserId($value)
 */
	class Instructor extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $group_name
 * @property int $course_id
 * @property int $instructor_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Course|null $course
 * @property-read \App\Models\Instructor $instructor
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Student> $students
 * @property-read int|null $students_count
 * @method static \Database\Factories\LearningGroupFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LearningGroup newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LearningGroup newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LearningGroup query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LearningGroup whereCourseId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LearningGroup whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LearningGroup whereGroupName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LearningGroup whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LearningGroup whereInstructorId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LearningGroup whereUpdatedAt($value)
 */
	class LearningGroup extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string $phone
 * @property string $title
 * @property string $content
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Database\Factories\MessageFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Message newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Message newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Message query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Message whereContent($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Message whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Message whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Message whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Message whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Message wherePhone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Message whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Message whereUpdatedAt($value)
 */
	class Message extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $student_id
 * @property numeric $total_amount
 * @property string $status
 * @property string $billing_name
 * @property string $billing_email
 * @property string $billing_phone
 * @property string|null $notes
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Enrollment> $enrollments
 * @property-read int|null $enrollments_count
 * @property-read \App\Models\Student $student
 * @method static \Database\Factories\OrderFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereBillingEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereBillingName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereBillingPhone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereStudentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereTotalAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereUpdatedAt($value)
 */
	class Order extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $exam_id
 * @property string $question_text
 * @property numeric $marks
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Choice> $choices
 * @property-read int|null $choices_count
 * @property-read \App\Models\Choice|null $correctChoice
 * @property-read \App\Models\Exam|null $exam
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Answer> $studentAnswers
 * @property-read int|null $student_answers_count
 * @method static \Database\Factories\QuestionFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Question newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Question newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Question onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Question query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Question whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Question whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Question whereExamId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Question whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Question whereMarks($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Question whereQuestionText($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Question whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Question withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Question withoutTrashed()
 */
	class Question extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $key
 * @property string|null $value
 * @property string $type string, boolean, image, json
 * @property string $group_name general, social, mail, etc.
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting whereGroupName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting whereKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting whereValue($value)
 */
	class Setting extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $title
 * @property string $description
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Tag> $tags
 * @property-read int|null $tags_count
 * @method static \Database\Factories\SolutionFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Solution newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Solution newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Solution query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Solution whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Solution whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Solution whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Solution whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Solution whereUpdatedAt($value)
 */
	class Solution extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $user_id
 * @property string $full_name
 * @property string|null $phone
 * @property string $enrollment_number
 * @property int|null $group_id
 * @property string|null $avatar
 * @property string|null $gender
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Exam> $availableExams
 * @property-read int|null $available_exams_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Certificate> $certificates
 * @property-read int|null $certificates_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Course> $courses
 * @property-read int|null $courses_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Enrollment> $enrollments
 * @property-read int|null $enrollments_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ExamAttempt> $examAttempts
 * @property-read int|null $exam_attempts_count
 * @property-read \App\Models\LearningGroup|null $learningGroup
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Order> $orders
 * @property-read int|null $orders_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Course> $reviewedCourses
 * @property-read int|null $reviewed_courses_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CourseReview> $reviews
 * @property-read int|null $reviews_count
 * @property-read \App\Models\User|null $user
 * @method static \Database\Factories\StudentFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Student newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Student newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Student query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Student whereAvatar($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Student whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Student whereEnrollmentNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Student whereFullName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Student whereGender($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Student whereGroupId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Student whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Student wherePhone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Student whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Student whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Student whereUserId($value)
 */
	class Student extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Course> $courses
 * @property-read int|null $courses_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Tag> $solutions
 * @property-read int|null $solutions_count
 * @method static \Database\Factories\TagFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tag newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tag newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tag query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tag whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tag whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tag whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tag whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tag whereUpdatedAt($value)
 */
	class Tag extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property \Illuminate\Support\Carbon|null $email_verified_at
 * @property string $password
 * @property string $role
 * @property \Illuminate\Support\Carbon|null $last_login_at
 * @property string|null $remember_token
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \App\Models\Admin|null $admin
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Course> $courses
 * @property-read int|null $courses_count
 * @property-read \App\Models\Instructor|null $instructor
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\LearningGroup> $instructorGroups
 * @property-read int|null $instructor_groups_count
 * @property-read \App\Models\LearningGroup|null $learningGroup
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection<int, \Illuminate\Notifications\DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Permission\Models\Permission> $permissions
 * @property-read int|null $permissions_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Permission\Models\Role> $roles
 * @property-read int|null $roles_count
 * @property-read \App\Models\Student|null $student
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Laravel\Sanctum\PersonalAccessToken> $tokens
 * @property-read int|null $tokens_count
 * @method static \Database\Factories\UserFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User permission($permissions, bool $without = false)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User role($roles, ?string $guard = null, bool $without = false)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmailVerifiedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereLastLoginAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRememberToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRole($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User withoutPermission($permissions)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User withoutRole($roles, ?string $guard = null)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User withoutTrashed()
 */
	class User extends \Eloquent implements \Illuminate\Contracts\Auth\MustVerifyEmail {}
}

