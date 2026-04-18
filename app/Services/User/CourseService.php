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

        return Course::active() // Scope اللي عملناه (published)
            ->with(['category:id,name,slug', 'instructor:id,user_id,avatar', 'instructor.user:id,name'])
            ->when(isset($filters['category_id']), function ($query) use ($filters) {
                // الفلترة بالقسم الرئيسي أو الفرعي
                $query->whereHas('category', function ($q) use ($filters) {
                    $q->where('id', $filters['category_id'])
                        ->orWhere('parent_id', $filters['category_id']);
                });
            })
            ->when(isset($filters['level']), fn($q) => $q->where('level', $filters['level']))
            ->when(isset($filters['search']), fn($q) => $q->where('title', 'like', '%' . $filters['search'] . '%'))
            ->latest()
            ->paginate($perPage);
    }

    /**
     * جلب تفاصيل كورس معين للطالب
     */
    public function getCourseDetails($slug)
    {
        return Course::active()
            ->with(['category', 'instructor.user', 'learnings', 'previews'])
            ->where('slug', $slug)
            ->firstOrFail();
    }
}
