<?php

namespace App\Http\Resources\User\Courses;

use App\Models\CourseReview;
use App\Services\User\CertificateService;
use App\Support\CourseInstructorSync;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CourseDashboardResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // الـ enrollment الخاص بالطالب للكورس
        $enrollment = $this->enrollments->first();
        $instructors = CourseInstructorSync::resolveForEnrollment($this->resource, $enrollment);

        return [
            // ── بيانات الكورس الأساسية ─────────────────────────────────────
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'short_description' => $this->short_description,
            'description' => $this->description,
            'thumbnail' => $this->thumbnail,
            'cover_image' => $this->cover_image,
            'google_drive_link' => $this->google_drive_link,
            'instructor_id' => $this->instructor_id,

            // ── تفاصيل إضافية (تظهر في صفحة الكورس) ────────────────────────
            'price_before' => $this->whenNotNull($this->price_before),
            'discount_price' => $this->whenNotNull($this->discount_price),
            'price' => $this->whenNotNull($this->price),
            'is_free' => $this->when(isset($this->is_free), fn() => (bool) $this->is_free),
            'level' => $this->whenNotNull($this->level),
            'language' => $this->whenNotNull($this->language),
            'duration_weeks' => $this->whenNotNull($this->duration_weeks),
            'duration_hours' => $this->whenNotNull($this->duration_hours),
            'avg_rating' => $this->whenNotNull($this->avg_rating),
            'total_reviews' => $this->whenNotNull($this->total_reviews),
            'total_students' => $this->whenNotNull($this->total_students),

            'instructors' => $instructors,

            /** @deprecated Use instructors[]. Will be removed in v2. */
            'instructor' => $instructors[0] ?? $this->whenLoaded('instructor', fn () => [
                'id' => $this->instructor->id,
                'full_name' => $this->instructor->full_name,
                'field' => $this->instructor->field,
                'bio' => $this->instructor->bio,
                'avatar' => $this->instructor->avatar,
                'phone' => $this->instructor->phone,
            ]),

            // ── التصنيف ─────────────────────────────────────────────────────
            'category' => $this->whenLoaded('category', fn() => [
                'id' => $this->category->id,
                'name' => $this->category->name,
                'slug' => $this->category->slug,
            ]),

            'tags' => $this->whenLoaded('tags', fn() => $this->tags->map(fn($tag) => [
                'id' => $tag->id,
                'name' => $tag->name,
                'slug' => $tag->slug,
            ])->values(), []),

            'learnings' => $this->whenLoaded('learnings', fn() => $this->learnings->pluck('title'), []),

            'previews' => $this->whenLoaded('previews', fn() => $this->previews->map(fn($preview) => [
                'id' => $preview->id,
                'title' => $preview->title,
                'video_url' => $preview->video_url,
                'description' => $preview->description,
                'video_provider' => $preview->video_provider,
                'duration_seconds' => $preview->duration_seconds,
                'sort_order' => $preview->sort_order,
            ])->values(), []),

        // ── حالة الـ Enrollment ─────────────────────────────────────────
            'enrollment' => $enrollment ? (function () use ($enrollment) {
                $review = CourseReview::query()
                    ->where('course_id', $this->id)
                    ->where('student_id', $enrollment->student_id)
                    ->first(['review_status']);

                $hasReview = $review !== null;
                $certificateService = app(CertificateService::class);

                return [
                    'status' => $enrollment->is_completed ? 'completed' : 'in_progress',
                    'completed_at' => $enrollment->completed_at?->toDateTimeString(),
                    'group_id' => $enrollment->group_id,
                    'has_review' => $hasReview,
                    'review_status' => $review?->review_status,
                    'certificate_available' => $certificateService->enrollmentCanIssueCertificate($enrollment),
                ];
            })() : null,
        ];
    }
}
