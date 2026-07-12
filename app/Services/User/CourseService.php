<?php

namespace App\Services\User;

use App\Models\Course;

class CourseService
{
    /**
     * Get active courses with filters and search
     */
    public function getActiveCourses(array $filters)
    {
        $perPage = $filters['per_page'] ?? 12;

        return Course::publiclyVisible()
            ->with([
                'category:id,name,slug',
                'instructors:id,user_id,full_name,avatar',
                'instructors.user:id,name',
                'tags:id,name,slug',
                'previews:id,course_id,title,video_url,description,video_provider,duration_seconds,sort_order',
            ])
            ->when(isset($filters['category_id']), function ($query) use ($filters) {
                $query->whereHas('category', function ($q) use ($filters) {
                    $q->where(function ($subQ) use ($filters) {
                        $subQ->where('id', $filters['category_id'])
                            ->orWhere('parent_id', $filters['category_id']);
                    });
                });
            })
            ->when(isset($filters['level']), fn($q) => $q->where('level', $filters['level']))
            ->when(isset($filters['search']), fn($q) => $q->where('title', 'like', '%' . $filters['search'] . '%'))
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Get course details for a student with enrollment check and instructor ID
     */
    public function getCourseDetails($slug)
    {
        $course = Course::publiclyVisible()
            ->with(['category', 'instructors.user', 'learnings', 'previews', 'tags:id,name,slug'])
            ->where('slug', $slug)
            ->first();

        if (! $course) {
            abort(response()->json([
                'status' => 'error',
                'message' => 'Course not found',
            ], 404));
        }

        // Get 3 similar courses from the same category (excluding the current course)
        $relatedCourses = Course::publiclyVisible()
            // Added 'instructor_id' here to ensure the with([instructor]) works without issues
            ->select([
                'id',
                'title',
                'slug',
                'thumbnail',
                'duration_hours',
                'duration_weeks',
                'short_description',
                'price_before',
                'discount_price',
                'price',
                'is_free',
                'category_id',
                'instructor_id',
                'attendance_type'
            ])
            ->with([
                'category:id,name,slug',
                'instructors:id,user_id,full_name,avatar',
                'instructors.user:id,name',
                'tags:id,name,slug',
                'previews:id,course_id,title,video_url,description,video_provider,duration_seconds,sort_order',
            ])
            ->where('category_id', $course->category_id)
            ->where('id', '!=', $course->id)
            ->inRandomOrder()
            ->limit(3)
            ->get();

        return [
            'course' => $course,
            'related' => $relatedCourses,
        ];
    }
}
