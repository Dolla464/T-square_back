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
 * @property int|null $choice_id
 * @property string|null $answer_text
 * @property bool|null $is_correct
 * @property numeric $marks_earned
 * @property \Illuminate\Support\Carbon|null $graded_at
 * @property int|null $graded_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\ExamAttempt $attempt
 * @property-read \App\Models\Choice|null $choice
 * @property-read \App\Models\Instructor|null $gradedBy
 * @property-read \App\Models\Question|null $question
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Answer newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Answer newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Answer query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Answer whereAnswerText($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Answer whereAttemptId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Answer whereChoiceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Answer whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Answer whereGradedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Answer whereGradedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Answer whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Answer whereIsCorrect($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Answer whereMarksEarned($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Answer whereQuestionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Answer whereUpdatedAt($value)
 */
	class Answer extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $device_id
 * @property string $name
 * @property bool $is_active
 * @property int|null $branch_id
 * @property int|null $instructor_id
 * @property \Illuminate\Support\Carbon|null $last_seen_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Branch|null $branch
 * @property-read \App\Models\Instructor|null $instructor
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceDevice newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceDevice newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceDevice query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceDevice whereBranchId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceDevice whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceDevice whereDeviceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceDevice whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceDevice whereInstructorId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceDevice whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceDevice whereLastSeenAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceDevice whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceDevice whereUpdatedAt($value)
 */
	class AttendanceDevice extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $session_id
 * @property int $student_id
 * @property string|null $student_qr_code
 * @property string $status
 * @property string $marked_by
 * @property \Illuminate\Support\Carbon|null $marked_at
 * @property \Illuminate\Support\Carbon|null $qr_expires_at
 * @property string|null $notes
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\AttendanceSession $session
 * @property-read \App\Models\Student $student
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceRecord newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceRecord newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceRecord query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceRecord whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceRecord whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceRecord whereMarkedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceRecord whereMarkedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceRecord whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceRecord whereQrExpiresAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceRecord whereSessionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceRecord whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceRecord whereStudentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceRecord whereStudentQrCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceRecord whereUpdatedAt($value)
 */
	class AttendanceRecord extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $learning_group_id
 * @property int $schedule_id
 * @property \Illuminate\Support\Carbon $session_date
 * @property \Illuminate\Support\Carbon|null $override_date
 * @property string|null $override_start_time
 * @property string|null $override_end_time
 * @property string|null $cancellation_reason
 * @property string|null $qr_code
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\AttendanceRecord> $attendanceRecords
 * @property-read int|null $attendance_records_count
 * @property-read \App\Models\LearningGroup $learningGroup
 * @property-read \App\Models\LearningGroupSchedule $schedule
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceSession newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceSession newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceSession query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceSession whereCancellationReason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceSession whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceSession whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceSession whereLearningGroupId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceSession whereOverrideDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceSession whereOverrideEndTime($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceSession whereOverrideStartTime($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceSession whereQrCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceSession whereScheduleId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceSession whereSessionDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceSession whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceSession whereUpdatedAt($value)
 */
	class AttendanceSession extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\AttendanceDevice> $attendanceDevices
 * @property-read int|null $attendance_devices_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\LearningGroup> $learningGroups
 * @property-read int|null $learning_groups_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Branch newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Branch newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Branch query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Branch whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Branch whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Branch whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Branch whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Branch whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Branch whereUpdatedAt($value)
 */
	class Branch extends \Eloquent {}
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
 * @property \Illuminate\Support\Carbon $issued_at
 * @property \App\Enums\CertificateStatus $status Allowed values: issued | pending | revoked
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Course|null $course
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Enrollment> $enrollments
 * @property-read int|null $enrollments_count
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
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Certificate whereStatus($value)
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
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Answer> $chosenBy
 * @property-read int|null $chosen_by_count
 * @property-read \App\Models\Question|null $question
 * @method static \Database\Factories\ChoiceFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Choice newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Choice newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Choice onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Choice query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Choice whereChoiceText($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Choice whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Choice whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Choice whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Choice whereIsCorrect($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Choice whereQuestionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Choice whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Choice withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Choice withoutTrashed()
 */
	class Choice extends \Eloquent {}
}

namespace App\Models{
/**
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ContactUs newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ContactUs newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ContactUs onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ContactUs query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ContactUs withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ContactUs withoutTrashed()
 */
	class ContactUs extends \Eloquent {}
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
 * @property int|null $google_storage_account_id
 * @property string|null $google_drive_folder_id
 * @property string $attendance_type
 * @property numeric|null $price_before
 * @property numeric $discount_price
 * @property numeric $price
 * @property string $level
 * @property string $language
 * @property int $duration_weeks
 * @property int $duration_hours
 * @property string $status
 * @property bool $is_featured
 * @property bool $is_free
 * @property int $category_id
 * @property int $instructor_id
 * @property numeric $avg_rating
 * @property int $total_reviews
 * @property int $total_students
 * @property numeric $total_revenue
 * @property \Illuminate\Support\Carbon|null $published_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Category $category
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Certificate> $certificates
 * @property-read int|null $certificates_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CourseInstructor> $courseInstructors
 * @property-read int|null $course_instructors_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Enrollment> $enrollments
 * @property-read int|null $enrollments_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Exam> $exams
 * @property-read int|null $exams_count
 * @property-read \App\Models\GoogleStorageAccount|null $googleStorageAccount
 * @property-read \App\Models\Instructor $instructor
 * @property-read \App\Models\CourseInstructor|null $pivot
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Instructor> $instructors
 * @property-read int|null $instructors_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\LearningGroup> $learningGroups
 * @property-read int|null $learning_groups_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CourseLearning> $learnings
 * @property-read int|null $learnings_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Lesson> $lessons
 * @property-read int|null $lessons_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CoursePreview> $previews
 * @property-read int|null $previews_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CourseReview> $reviews
 * @property-read int|null $reviews_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Student> $students
 * @property-read int|null $students_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Tag> $tags
 * @property-read int|null $tags_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Course active()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Course assignedToInstructor(int $instructorId)
 * @method static \Database\Factories\CourseFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Course featured()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Course findSimilarSlugs(string $attribute, array $config, string $slug)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Course newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Course newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Course onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Course publiclyVisible()
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
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Course whereGoogleDriveFolderId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Course whereGoogleDriveLink($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Course whereGoogleStorageAccountId($value)
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
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Course whereShortDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Course whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Course whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Course whereThumbnail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Course whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Course whereTotalRevenue($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Course whereTotalReviews($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Course whereTotalStudents($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Course whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Course withActiveCategory()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Course withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Course withUniqueSlugConstraints(\Illuminate\Database\Eloquent\Model $model, string $attribute, array $config, string $slug)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Course withoutTrashed()
 */
	class Course extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $course_id
 * @property int $instructor_id
 * @property int $sort_order
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Course|null $course
 * @property-read \App\Models\Instructor $instructor
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\LearningGroup> $learningGroups
 * @property-read int|null $learning_groups_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CourseReviewInstructorRating> $reviewRatings
 * @property-read int|null $review_ratings_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseInstructor newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseInstructor newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseInstructor query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseInstructor whereCourseId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseInstructor whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseInstructor whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseInstructor whereInstructorId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseInstructor whereSortOrder($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseInstructor whereUpdatedAt($value)
 */
	class CourseInstructor extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $course_id
 * @property string $title
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \App\Models\Course|null $course
 * @method static \Database\Factories\CourseLearningFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseLearning newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseLearning newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseLearning onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseLearning query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseLearning whereCourseId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseLearning whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseLearning whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseLearning whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseLearning whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseLearning whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseLearning withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseLearning withoutTrashed()
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
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \App\Models\Course|null $course
 * @method static \Database\Factories\CoursePreviewFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CoursePreview newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CoursePreview newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CoursePreview onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CoursePreview query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CoursePreview whereCourseId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CoursePreview whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CoursePreview whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CoursePreview whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CoursePreview whereDurationSeconds($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CoursePreview whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CoursePreview whereSortOrder($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CoursePreview whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CoursePreview whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CoursePreview whereVideoProvider($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CoursePreview whereVideoUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CoursePreview withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CoursePreview withoutTrashed()
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
 * @property string $status
 * @property string $review_status
 * @property-read \App\Models\Course|null $course
 * @property-read \App\Models\Instructor $instructor
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CourseReviewInstructorRating> $instructorRatings
 * @property-read int|null $instructor_ratings_count
 * @property-read \App\Models\Student $student
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseReview active()
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
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseReview whereReviewStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseReview whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseReview whereStudentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseReview whereUpdatedAt($value)
 */
	class CourseReview extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $course_review_id
 * @property int $course_instructor_id
 * @property numeric $instructor_rating
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\CourseInstructor $courseInstructor
 * @property-read \App\Models\CourseReview $review
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseReviewInstructorRating newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseReviewInstructorRating newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseReviewInstructorRating query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseReviewInstructorRating whereCourseInstructorId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseReviewInstructorRating whereCourseReviewId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseReviewInstructorRating whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseReviewInstructorRating whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseReviewInstructorRating whereInstructorRating($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseReviewInstructorRating whereUpdatedAt($value)
 */
	class CourseReviewInstructorRating extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $student_id
 * @property int $course_id
 * @property int|null $order_id
 * @property int|null $group_id
 * @property numeric $price_paid
 * @property bool $is_completed
 * @property \Illuminate\Support\Carbon|null $completed_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Certificate|null $certificate
 * @property-read \App\Models\Course|null $course
 * @property-read \App\Models\LearningGroup|null $learningGroup
 * @property-read \App\Models\Order|null $order
 * @property-read \App\Models\Student $student
 * @method static \Database\Factories\EnrollmentFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Enrollment newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Enrollment newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Enrollment query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Enrollment whereCompletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Enrollment whereCourseId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Enrollment whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Enrollment whereGroupId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Enrollment whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Enrollment whereIsCompleted($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Enrollment whereOrderId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Enrollment wherePricePaid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Enrollment whereStudentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Enrollment whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Enrollment withCompletedOrder()
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
 * @property int $max_attempts
 * @property int $questions_per_attempt
 * @property bool $shuffle_questions
 * @property int|null $updated_by
 * @property bool $is_final
 * @property float $total_marks
 * @property float $passing_mark
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\LearningGroup> $activatedGroups
 * @property-read int|null $activated_groups_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ExamAttempt> $attempts
 * @property-read int|null $attempts_count
 * @property-read \App\Models\Course|null $course
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\GroupExamActivation> $groupActivations
 * @property-read int|null $group_activations_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Question> $questions
 * @property-read int|null $questions_count
 * @property-read \App\Models\User|null $updatedBy
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
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Exam whereIsFinal($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Exam whereMaxAttempts($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Exam wherePassingMark($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Exam whereQuestionsPerAttempt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Exam whereShuffleQuestions($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Exam whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Exam whereTotalMarks($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Exam whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Exam whereUpdatedBy($value)
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
 * @property int|null $duration_minutes
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $started_at
 * @property \Illuminate\Support\Carbon|null $finished_at
 * @property \Illuminate\Support\Carbon|null $graded_at
 * @property int|null $graded_by
 * @property numeric $score Final Result
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $ongoing_slot
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Answer> $answers
 * @property-read int|null $answers_count
 * @property-read \App\Models\Exam|null $exam
 * @property-read mixed $duration
 * @property-read \App\Models\Instructor|null $gradedBy
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ExamAttemptIntegrityEvent> $integrityEvents
 * @property-read int|null $integrity_events_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Question> $questions
 * @property-read int|null $questions_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Question> $questionsWithTrashed
 * @property-read int|null $questions_with_trashed_count
 * @property-read \App\Models\Student $student
 * @method static \Database\Factories\ExamAttemptFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamAttempt newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamAttempt newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamAttempt query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamAttempt reviewable()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamAttempt whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamAttempt whereDurationMinutes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamAttempt whereExamId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamAttempt whereFinishedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamAttempt whereGradedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamAttempt whereGradedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamAttempt whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamAttempt whereOngoingSlot($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamAttempt whereScore($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamAttempt whereStartedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamAttempt whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamAttempt whereStudentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamAttempt whereUpdatedAt($value)
 */
	class ExamAttempt extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $exam_attempt_id
 * @property string $event_id
 * @property string $event_type
 * @property \Illuminate\Support\Carbon|null $client_at
 * @property \Illuminate\Support\Carbon $occurred_at
 * @property array<array-key, mixed>|null $metadata
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\ExamAttempt $attempt
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamAttemptIntegrityEvent newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamAttemptIntegrityEvent newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamAttemptIntegrityEvent query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamAttemptIntegrityEvent whereClientAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamAttemptIntegrityEvent whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamAttemptIntegrityEvent whereEventId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamAttemptIntegrityEvent whereEventType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamAttemptIntegrityEvent whereExamAttemptId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamAttemptIntegrityEvent whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamAttemptIntegrityEvent whereMetadata($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamAttemptIntegrityEvent whereOccurredAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamAttemptIntegrityEvent whereUpdatedAt($value)
 */
	class ExamAttemptIntegrityEvent extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $name
 * @property string|null $email
 * @property string|null $access_token
 * @property string|null $refresh_token
 * @property \Illuminate\Support\Carbon|null $token_expires_at
 * @property string|null $scope
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $last_checked_at
 * @property string|null $last_error
 * @property int|null $connected_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \App\Models\User|null $connectedBy
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Course> $courses
 * @property-read int|null $courses_count
 * @method static \Database\Factories\GoogleStorageAccountFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GoogleStorageAccount newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GoogleStorageAccount newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GoogleStorageAccount onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GoogleStorageAccount query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GoogleStorageAccount whereAccessToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GoogleStorageAccount whereConnectedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GoogleStorageAccount whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GoogleStorageAccount whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GoogleStorageAccount whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GoogleStorageAccount whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GoogleStorageAccount whereLastCheckedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GoogleStorageAccount whereLastError($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GoogleStorageAccount whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GoogleStorageAccount whereRefreshToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GoogleStorageAccount whereScope($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GoogleStorageAccount whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GoogleStorageAccount whereTokenExpiresAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GoogleStorageAccount whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GoogleStorageAccount withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GoogleStorageAccount withoutTrashed()
 */
	class GoogleStorageAccount extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $exam_id
 * @property int $learning_group_id
 * @property int|null $activated_by
 * @property \Illuminate\Support\Carbon $activated_at
 * @property-read \App\Models\Instructor|null $activatedBy
 * @property-read \App\Models\Exam|null $exam
 * @property-read \App\Models\LearningGroup $learningGroup
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GroupExamActivation newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GroupExamActivation newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GroupExamActivation query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GroupExamActivation whereActivatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GroupExamActivation whereActivatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GroupExamActivation whereExamId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GroupExamActivation whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GroupExamActivation whereLearningGroupId($value)
 */
	class GroupExamActivation extends \Eloquent {}
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
 * @property string|null $field
 * @property-read \App\Models\CourseInstructor|null $pivot
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Course> $assignedCourses
 * @property-read int|null $assigned_courses_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CourseInstructor> $courseInstructorAssignments
 * @property-read int|null $course_instructor_assignments_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Course> $courses
 * @property-read int|null $courses_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Enrollment> $enrollmentsViaCoursesRelation
 * @property-read int|null $enrollments_via_courses_relation_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\LearningGroup> $learningGroups
 * @property-read int|null $learning_groups_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CourseReview> $reviews
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
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Instructor whereField($value)
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
 * @property int|null $branch_id
 * @property int $course_instructor_id
 * @property \Illuminate\Support\Carbon|null $start_date
 * @property \Illuminate\Support\Carbon|null $end_date
 * @property string $status
 * @property int $enrolled_students
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Exam> $activatedExams
 * @property-read int|null $activated_exams_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\AttendanceSession> $attendanceSessions
 * @property-read int|null $attendance_sessions_count
 * @property-read \App\Models\Branch|null $branch
 * @property-read \App\Models\Course|null $course
 * @property-read \App\Models\CourseInstructor $courseInstructor
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Enrollment> $enrollments
 * @property-read int|null $enrollments_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\GroupExamActivation> $examActivations
 * @property-read int|null $exam_activations_count
 * @property-read \App\Models\Instructor|null $instructor
 * @property-read int|null $instructor_id
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\LearningGroupSchedule> $schedules
 * @property-read int|null $schedules_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Student> $students
 * @property-read int|null $students_count
 * @method static \Database\Factories\LearningGroupFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LearningGroup forInstructor(int $instructorId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LearningGroup newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LearningGroup newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LearningGroup query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LearningGroup whereBranchId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LearningGroup whereCourseId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LearningGroup whereCourseInstructorId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LearningGroup whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LearningGroup whereEndDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LearningGroup whereEnrolledStudents($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LearningGroup whereGroupName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LearningGroup whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LearningGroup whereStartDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LearningGroup whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LearningGroup whereUpdatedAt($value)
 */
	class LearningGroup extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $learning_group_id
 * @property int $day_of_week
 * @property \Illuminate\Support\Carbon $start_time
 * @property \Illuminate\Support\Carbon $end_time
 * @property string|null $room
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\AttendanceSession> $attendanceSessions
 * @property-read int|null $attendance_sessions_count
 * @property-read \App\Models\LearningGroup $learningGroup
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LearningGroupSchedule newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LearningGroupSchedule newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LearningGroupSchedule query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LearningGroupSchedule whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LearningGroupSchedule whereDayOfWeek($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LearningGroupSchedule whereEndTime($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LearningGroupSchedule whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LearningGroupSchedule whereLearningGroupId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LearningGroupSchedule whereRoom($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LearningGroupSchedule whereStartTime($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LearningGroupSchedule whereUpdatedAt($value)
 */
	class LearningGroupSchedule extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $course_id
 * @property string $title
 * @property string|null $description
 * @property int $sort_order
 * @property bool $is_active
 * @property string|null $video_source_type
 * @property string|null $google_drive_file_id
 * @property int|null $duration_seconds
 * @property string|null $drive_validation_status
 * @property string|null $drive_validation_message
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \App\Models\Course|null $course
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Lesson active()
 * @method static \Database\Factories\LessonFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Lesson newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Lesson newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Lesson onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Lesson ordered()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Lesson query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Lesson whereCourseId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Lesson whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Lesson whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Lesson whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Lesson whereDriveValidationMessage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Lesson whereDriveValidationStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Lesson whereDurationSeconds($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Lesson whereGoogleDriveFileId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Lesson whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Lesson whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Lesson whereSortOrder($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Lesson whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Lesson whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Lesson whereVideoSourceType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Lesson withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Lesson withoutTrashed()
 */
	class Lesson extends \Eloquent {}
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
 * @property \Illuminate\Support\Carbon|null $status_changed_at
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
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereStatusChangedAt($value)
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
 * @property string $type
 * @property string|null $question_text
 * @property string|null $question_image
 * @property string|null $question_code
 * @property string|null $question_code_language
 * @property float $marks
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Choice> $choices
 * @property-read int|null $choices_count
 * @property-read \App\Models\Choice|null $correctChoice
 * @property-read \App\Models\Exam|null $exam
 * @property-read mixed $question_image_url
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
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Question whereQuestionCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Question whereQuestionCodeLanguage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Question whereQuestionImage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Question whereQuestionText($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Question whereType($value)
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
 * @property string|null $avatar
 * @property string|null $gender
 * @property int|null $age
 * @property string|null $qualification
 * @property string|null $guardian_phone
 * @property string|null $national_id
 * @property string|null $address
 * @property string|null $notes
 * @property string $status
 * @property string $created_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\AttendanceRecord> $attendanceRecords
 * @property-read int|null $attendance_records_count
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
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection<int, \Illuminate\Notifications\DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
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
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Student whereAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Student whereAge($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Student whereAvatar($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Student whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Student whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Student whereEnrollmentNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Student whereFullName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Student whereGender($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Student whereGuardianPhone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Student whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Student whereNationalId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Student whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Student wherePhone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Student whereQualification($value)
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
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Solution> $solutions
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
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection<int, \Illuminate\Notifications\DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Permission\Models\Permission> $permissions
 * @property-read int|null $permissions_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Permission\Models\Role> $roles
 * @property-read int|null $roles_count
 * @property-read \App\Models\Student|null $student
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Permission\Models\Permission> $teams
 * @property-read int|null $teams_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Laravel\Sanctum\PersonalAccessToken> $tokens
 * @property-read int|null $tokens_count
 * @method static \Database\Factories\UserFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User permission($permissions, bool $without = false)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User role($roles, ?string $guard = null, bool $without = false)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User team($teams, bool $without = false)
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
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User withoutTeam($teams)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User withoutTrashed()
 */
	class User extends \Eloquent implements \Illuminate\Contracts\Auth\MustVerifyEmail {}
}

