<?php

namespace App\Services\User;

use App\Models\Course;

class CourseService
{
    /**
     * جلب الكورسات النشطة مع الفلترة والبحث
     */
    public function getActiveCourses(array $filters)
    {
        $perPage = $filters['per_page'] ?? 12;

        return Course::active()
            ->with([
                'category:id,name,slug',
                'instructor:id,user_id,avatar',
                'instructor.user:id,name',
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
     * جلب تفاصيل كورس معين للطالب
     */
    public function getCourseDetails($slug)
    {
        $course = Course::active()
            ->with(['category', 'instructor.user', 'learnings', 'previews', 'tags:id,name,slug'])
            ->where('slug', $slug)
            ->first();

        if (!$course) {
            abort(response()->json([
                'status' => 'error',
                'message' => 'Course not found'
            ], 404));
        }

        // جلب 3 كورسات مشابهة من نفس القسم (بعيداً عن الكورس الحالي)
        $relatedCourses = Course::active()
            ->select(['id', 'title', 'slug', 'thumbnail', 'duration_hours', 'duration_weeks', 'short_description', 'price_before', 'discount_price', 'price', 'category_id', 'attendance_type'])
            ->with([
                'category:id,name,slug',
                'instructor:id,user_id,avatar',
                'instructor.user:id,name',
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
            'related' => $relatedCourses
        ];
    }
}
