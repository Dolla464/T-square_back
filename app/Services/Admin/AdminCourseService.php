<?php

namespace App\Services\Admin;

use App\Http\Resources\Admin\Course\CourseFieldList;
use App\Models\Course;
use App\Models\CourseLearning;
use App\Traits\HandleImageUploadTrait;
use App\Traits\HandleVideoUploadTrait;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Storage;

class AdminCourseService
{
    use HandleImageUploadTrait;
    use HandleVideoUploadTrait;

    /**
     * Get paginated course listing for the admin panel.
     *
     * Accepted filters:
     *   search      – matches against title, slug, or instructor full_name
     *   status      – 'published' | 'draft'
     *   category_id – a parent category id; returns courses in that category
     *                 OR in any of its direct child categories
     */
    public function index(array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        return Course::select([
            'id',
            'title',
            'slug',
            'status',
            'total_revenue',
            'total_students',
            'category_id',
            'instructor_id',
        ])
            ->with([
                'instructor:id,full_name',
                'category:id,name,parent_id',
                'tags:id,name,slug',
            ])
            ->when(! empty($filters['search']), function ($query) use ($filters) {
                $term = $filters['search'];
                $query->where(function ($q) use ($term) {
                    $q->where('title', 'like', "%{$term}%")
                        ->orWhere('slug', 'like', "%{$term}%")
                        ->orWhereHas(
                            'instructor',
                            fn($q) => $q->where('full_name', 'like', "%{$term}%")
                        );
                });
            })
            ->when(
                ! empty($filters['status']),
                fn($q) => $q->where('status', $filters['status'])
            )
            ->when(! empty($filters['category_id']), function ($query) use ($filters) {
                $parentId = (int) $filters['category_id'];
                $query->whereHas(
                    'category',
                    fn($q) => $q->where('id', $parentId)
                        ->orWhere('parent_id', $parentId)
                );
            })
            ->latest()
            ->paginate($perPage);
    }

    /**
     * Get a single course by id with all relationships loaded.
     */
    public function show($id): Course
    {
        return Course::with([
            'instructor:id,full_name',
            'category:id,name,slug,parent_id',
            'tags:id,name,slug',
            'previews:id,course_id,title,video_url,description,video_provider,duration_seconds,sort_order',
            'learnings:id,course_id,title',
        ])
            ->findOrFail($id);
    }

    /**
     * Create a new course, handle uploaded files, sync tags, and create previews.
     */
    public function create(array $data): Course
    {
        $learnings = $data['learnings'] ?? [];

        // 1. معالجة الصور – thumbnail (max 800px) and cover (max 1920px)
        if (! empty($data['thumbnail']) && $data['thumbnail'] instanceof UploadedFile) {
            $data['thumbnail'] = $this->uploadImage($data['thumbnail'], 'courses/thumbnails', null, 800);
        }

        if (! empty($data['cover_image']) && $data['cover_image'] instanceof UploadedFile) {
            $data['cover_image'] = $this->uploadImage($data['cover_image'], 'courses/covers', null, 1920);
        }

        // 2. معالجة الحالة (Status) وتاريخ النشر (published_at) ليتوافق مع MySQL
        $status = $data['status'] ?? 'draft'; // القيمة الافتراضية مسودة إذا لم تُرسل

        if ($status === 'published') {
            // إذا تم إرسال تاريخ محدد من الفرونت إند نقوم بتهيئته، وإلا نضع تاريخ اللحظة الحالية (نشر فوري)
            $data['published_at'] = (! empty($data['published_at']))
                ? date('Y-m-d H:i:s', strtotime($data['published_at']))
                : now();
        } else {
            // إذا كان مسودة (draft)، نضمن أن تاريخ النشر فارغ تماماً
            $data['published_at'] = null;
        }

        // 3. استخراج العلاقات والبيانات الإضافية قبل إنشاء الموديل
        $tags = $data['tags'] ?? null;
        $previews = $data['previews'] ?? [];
        $learnings = $data['learnings'] ?? []; // استخراج الـ learnings هنا

        // تنظيف المصفوفة من الحقول التي لا تنتمي لجدول courses
        unset($data['tags'], $data['previews'], $data['learnings']);

        // 4. إنشاء الكورس الأساسي (سيأخذ الـ status والـ published_at تلقائياً من الـ $data)
        $course = Course::create($data);

        // 5. حفظ الـ Learnings (ماذا سيتعلم الطالب)
        if (! empty($learnings) && is_array($learnings)) {
            foreach ($learnings as $text) {
                if (! empty(trim($text))) {
                    $course->learnings()->create([
                        'title' => $text,
                    ]);
                }
            }
        }

        // 6. ربط الوسوم (Tags)
        if ($tags !== null) {
            $course->tags()->sync($tags);
        }

        // 7. إنشاء فيديوهات المعاينة (Previews)
        $this->syncPreviews($course, $previews, []);

        return $this->freshWithRelations($course->id);
    }

    /**
     * Update an existing course, manage uploaded file replacements, sync tags, and update previews.
     */
    public function update($id, array $data): Course
    {
        $course = Course::findOrFail($id);

        // 1. معالجة الصور (كما هي)
        if (array_key_exists('thumbnail', $data) && $data['thumbnail'] instanceof UploadedFile) {
            $data['thumbnail'] = $this->uploadImage(
                $data['thumbnail'],
                'courses/thumbnails',
                $course->getRawOriginal('thumbnail'),
                800
            );
        } else {
            unset($data['thumbnail']);
        }

        if (array_key_exists('cover_image', $data) && $data['cover_image'] instanceof UploadedFile) {
            $data['cover_image'] = $this->uploadImage(
                $data['cover_image'],
                'courses/covers',
                $course->getRawOriginal('cover_image'),
                1920
            );
        } else {
            unset($data['cover_image']);
        }

        // 2. استخراج البيانات قبل الـ Unset لضمان عدم ضياعها
        // أضفنا (array) لضمان تحويلها لمصفوفة حتى لو وصلت بشكل غريب
        $tags = isset($data['tags']) ? (array) $data['tags'] : false;
        $previews = isset($data['previews']) ? (array) $data['previews'] : null;
        $learnings = isset($data['learnings']) ? (array) $data['learnings'] : null;

        // 3. تنظيف مصفوفة الـ data قبل التحديث المباشر
        unset($data['tags'], $data['previews'], $data['learnings'], $data['_method']);

        if (! empty($data['published_at'])) {
            try {
                // تحويل التاريخ لـ Carbon لضمان التنسيق الصحيح لـ MySQL
                $data['published_at'] = Carbon::parse($data['published_at'])->toDateTimeString();
            } catch (\Exception $e) {
                // If the date is invalid or in a weird format, it's better to delete it or set it to null
                unset($data['published_at']);
            }
        }

        // 4. Update the main table (courses)
        $course->update($data);

        // 5. Sync Tags
        if ($tags !== false) {
            $course->tags()->sync($tags);
        }

        // 6. Sync Learnings (fix save issue)
        if ($learnings !== null) {
            // Delete old goals associated with this course
            $course->learnings()->delete();

            foreach ($learnings as $text) {
                if (! empty(trim($text))) {
                    $course->learnings()->create([
                        'title' => $text, // Ensure the CourseLearning model contains 'title' in the fillable
                    ]);
                }
            }
        }

        // 7. مزامنة الـ Previews
        if ($previews !== null) {
            $existingPreviews = $course->previews()->pluck('video_url', 'id')->toArray();
            $this->syncPreviews($course, $previews, $existingPreviews);
        }

        return $this->freshWithRelations($course->id);
    }

    /**
     * Delete the given course by id and remove related uploaded files from storage.
     */
    public function destroy($id): void
    {
        $course = Course::findOrFail($id);
        $course->delete();
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Create or update course_previews rows.
     *
     * Each item in $previews may contain:
     *   id (int|null), title, description, video (UploadedFile|null),
     *   video_url (string|null), video_provider, sort_order
     *
     * Rows whose id is not present in $previews are deleted.
     *
     * @param  array  $previews  Input preview items
     * @param  array  $existingVideoUrls  Map of id => video_url for existing previews
     */
    private function syncPreviews(Course $course, array $previews, array $existingVideoUrls): void
    {
        $keptIds = [];

        foreach ($previews as $index => $item) {
            $hasTitle = ! empty(trim($item['title'] ?? ''));
            $hasVideo = (! empty($item['video']) && $item['video'] instanceof UploadedFile)
                || ! empty($item['video_url']);

            // Skip completely empty rows that would violate the NOT NULL constraint
            if (! $hasTitle && ! $hasVideo) {
                continue;
            }

            $previewId = $item['id'] ?? null;
            $oldVideoUrl = $previewId ? ($existingVideoUrls[$previewId] ?? null) : null;
            $videoPayload = [];

            // 1. Process video: uploaded file takes priority over a URL string
            if (! empty($item['video']) && $item['video'] instanceof UploadedFile) {
                $videoData = $this->uploadVideo($item['video'], 'courses/previews', $oldVideoUrl);
                $videoPayload = [
                    'video_url'        => $videoData['path'],
                    'video_provider'   => 'upload',
                    'duration_seconds' => $videoData['duration'],
                ];
            } elseif (! empty($item['video_url'])) {
                $videoPayload = [
                    'video_url'      => $item['video_url'],
                    'video_provider' => $item['video_provider'] ?? 'upload',
                ];
            }

            // Ensure video_url always has a value so the DB NOT NULL constraint is satisfied
            if (empty($videoPayload)) {
                $videoPayload = ['video_url' => ''];
            }

            $attributes = array_filter([
                'title'       => $item['title'] ?? 'Preview ' . ($index + 1),
                'description' => $item['description'] ?? null,
                'sort_order'  => $item['sort_order'] ?? $index,
            ], fn($v) => $v !== null);

            $attributes = array_merge($attributes, $videoPayload);

            // 2. Update existing or create new
            if ($previewId && isset($existingVideoUrls[$previewId])) {
                $course->previews()->where('id', $previewId)->update($attributes);
                $keptIds[] = $previewId;
            } else {
                $newPreview = $course->previews()->create($attributes);
                $keptIds[] = $newPreview->id;
            }
        }

        // 🔥 الخطوة السحرية: حذف أي سجل في الداتابيز ليس ضمن الـ keptIds 🔥
        $toDelete = $course->previews()->whereNotIn('id', $keptIds)->get();

        foreach ($toDelete as $oldPreview) {
            // حذف الملف الفيزيائي إذا كان مرفوعاً
            if ($oldPreview->video_provider === 'upload' && $oldPreview->video_url) {
                Storage::disk('public')->delete($oldPreview->video_url);
            }
            $oldPreview->delete();
        }
    }

    /**
     * Return a fresh Course with all relations for serialisation.
     */
    protected function freshWithRelations($id): Course
    {
        $fields = CourseFieldList::fieldsForDetail();
        $fieldsForSelect = array_unique(array_merge($fields, ['category_id', 'instructor_id']));

        return Course::select($fieldsForSelect)
            ->with([
                'instructor:id,full_name,phone,avatar,field,bio,gender,status',
                'category:id,name,slug,description,icon,image,parent_id,sort_order,status',
                'tags:id,name,slug',
                'previews:id,course_id,title,video_url,description,video_provider,duration_seconds,sort_order',
                'learnings:id,course_id,title',
            ])
            ->findOrFail($id);
    }

    /**
     * Get trashed courses
     */
    public function getTrashedCourses($perPage = 10)
    {
        $query = Course::onlyTrashed()
            ->with(['category', 'instructor'])
            ->latest('deleted_at');

        // Filter by the selected period from the frontend
        if (request()->has('period')) {
            $period = request('period');

            $date = match ($period) {
                'today' => now()->startOfDay(),
                'month' => now()->subMonth(),
                'year'  => now()->subYear(),
                default => null
            };

            if ($date) {
                $query->where('deleted_at', '>=', $date);
            }
        }

        // Support search by name inside the trash
        if (request()->has('search')) {
            $query->where('title', 'like', '%' . request('search') . '%');
        }

        return $query->paginate($perPage);
    }

    /**
     * Restore trashed course
     */
    public function restoreCourse($id)
    {
        $course = Course::onlyTrashed()->findOrFail($id);
        $course->restore();
        return $course;
    }

    /**
     * Force delete course
     */
    public function forceDeleteCourse($id)
    {
        $course = Course::onlyTrashed()->findOrFail($id);

        // 1. Delete images from storage before final deletion
        if ($course->thumbnail) {
            Storage::disk('public')->delete($course->thumbnail);
        }
        if ($course->cover_image) {
            Storage::disk('public')->delete($course->cover_image);
        }

        // 2. You can also delete preview videos here
        $course->previews()->each(fn($p) => Storage::disk('public')->delete($p->video_url));

        return $course->forceDelete();
    }
}
