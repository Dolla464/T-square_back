<?php

namespace App\Services\Admin;

use App\Models\Course;
use App\Traits\HandleImageUploadTrait;
use App\Traits\HandleVideoUploadTrait;
use App\Http\Resources\Admin\Course\CourseFieldList;
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
            ->when(!empty($filters['search']), function ($query) use ($filters) {
                $term = $filters['search'];
                $query->where(function ($q) use ($term) {
                    $q->where('title', 'like', "%{$term}%")
                        ->orWhere('slug', 'like', "%{$term}%")
                        ->orWhereHas(
                            'instructor',
                            fn($q) =>
                            $q->where('full_name', 'like', "%{$term}%")
                        );
                });
            })
            ->when(
                !empty($filters['status']),
                fn($q) =>
                $q->where('status', $filters['status'])
            )
            ->when(!empty($filters['category_id']), function ($query) use ($filters) {
                $parentId = (int) $filters['category_id'];
                $query->whereHas(
                    'category',
                    fn($q) =>
                    $q->where('id', $parentId)
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
        // Images – thumbnail (max 800px) and cover (max 1920px)
        if (!empty($data['thumbnail']) && $data['thumbnail'] instanceof UploadedFile) {
            $data['thumbnail'] = $this->uploadImage($data['thumbnail'], 'courses/thumbnails', null, 800);
        }

        if (!empty($data['cover_image']) && $data['cover_image'] instanceof UploadedFile) {
            $data['cover_image'] = $this->uploadImage($data['cover_image'], 'courses/covers', null, 1920);
        }

        // Extract relationships before creating the model
        $tags     = $data['tags'] ?? null;
        $previews = $data['previews'] ?? [];
        unset($data['tags'], $data['previews']);

        $course = Course::create($data);

        // Sync tags
        if ($tags !== null) {
            $course->tags()->sync($tags);
        }

        // Create preview videos
        $this->syncPreviews($course, $previews, []);

        return $this->freshWithRelations($course->id);
    }

    /**
     * Update an existing course, manage uploaded file replacements, sync tags, and update previews.
     */
    public function update($id, array $data): Course
    {
        $course = Course::findOrFail($id);

        // Images
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

        // Extract relationships before updating the model
        $tags     = array_key_exists('tags', $data) ? $data['tags'] : false;
        $previews = $data['previews'] ?? null;
        unset($data['tags'], $data['previews']);

        $course->update($data);

        // Sync tags when provided
        if ($tags !== false) {
            $course->tags()->sync($tags ?? []);
        }

        // Sync previews when provided
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
        $course = Course::with('previews')->findOrFail($id);

        // Remove course images
        foreach (['thumbnail', 'cover_image', 'preview_video'] as $field) {
            $path = $course->getRawOriginal($field);
            if ($path && Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
            }
        }

        // Remove uploaded preview videos stored locally
        foreach ($course->previews as $preview) {
            if (
                $preview->video_provider === 'local' &&
                $preview->video_url &&
                Storage::disk('public')->exists($preview->video_url)
            ) {
                Storage::disk('public')->delete($preview->video_url);
            }
        }

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
     * @param  Course  $course
     * @param  array   $previews         Input preview items
     * @param  array   $existingVideoUrls Map of id => video_url for existing previews
     */
    private function syncPreviews(Course $course, array $previews, array $existingVideoUrls): void
    {
        $keptIds = [];

        foreach ($previews as $index => $item) {
            // تحويل الملف المرفوع مباشرة إلى مصفوفة لتوحيد المنطق
            if ($item instanceof \Illuminate\Http\UploadedFile) {
                $item = ['video' => $item];
            }

            if (!is_array($item)) {
                continue;
            }

            $previewId    = $item['id'] ?? null;
            $oldVideoUrl  = $previewId ? ($existingVideoUrls[$previewId] ?? null) : null;
            $videoPayload = [];

            // 1. معالجة الفيديو المرفوع (Upload)
            if (!empty($item['video']) && $item['video'] instanceof \Illuminate\Http\UploadedFile) {
                $videoData = $this->uploadVideo($item['video'], 'courses/previews', $oldVideoUrl);

                $videoPayload = [
                    'video_url'        => $videoData['path'],
                    'video_provider'   => 'upload',
                    'duration_seconds' => $videoData['duration'],
                ];
            }
            // 2. معالجة روابط الفيديو الخارجية (YouTube, Vimeo, etc.)
            elseif (!empty($item['video_url'])) {
                $allowed   = ['youtube', 'vimeo', 'upload', 'external'];
                $provider  = $item['video_provider'] ?? 'external';
                $videoPayload = [
                    'video_url'      => $item['video_url'],
                    'video_provider' => in_array($provider, $allowed, true) ? $provider : 'external',
                ];
            }

            // تحضير البيانات الأساسية
            $titleDefault = $item['title'] ?? (
                !empty($item['video']) ? $item['video']->getClientOriginalName() : 'Preview ' . ($index + 1)
            );

            $attributes = array_filter([
                'title'       => $titleDefault,
                'description' => $item['description'] ?? null,
                'sort_order'  => $item['sort_order'] ?? $index,
            ], fn($v) => $v !== null);

            $attributes = array_merge($attributes, $videoPayload);

            // التحديث أو الإنشاء
            if ($previewId && isset($existingVideoUrls[$previewId])) {
                $course->previews()->where('id', $previewId)->update($attributes);
                $keptIds[] = $previewId;
            } else {
                $preview   = $course->previews()->create($attributes);
                $keptIds[] = $preview->id;
            }
        }

        /*
    |--------------------------------------------------------------------------
    | الحذف الاختياري فقط
    |--------------------------------------------------------------------------
    | الكود الآن لن يحذف أي فيديو قديم لم يرسل في مصفوفة previews.
    | سيقوم بالحذف فقط إذا أرسلت مصفوفة باسم deleted_preview_ids تحتوي على الأرقام التعريفية.
    */
        $idsToDelete = request()->input('deleted_preview_ids', []);

        if (!empty($idsToDelete) && is_array($idsToDelete)) {
            $toDelete = $course->previews()
                ->whereIn('id', $idsToDelete)
                ->get();

            foreach ($toDelete as $preview) {
                // حذف الملف الفيزيائي من التخزين إذا كان مرفوعاً محلياً
                if (
                    $preview->video_provider === 'upload' &&
                    $preview->video_url &&
                    \Illuminate\Support\Facades\Storage::disk('public')->exists($preview->video_url)
                ) {
                    \Illuminate\Support\Facades\Storage::disk('public')->delete($preview->video_url);
                }
                $preview->delete();
            }
        }
    }

    /**
     * Return a fresh Course with all relations for serialisation.
     */
    protected function freshWithRelations($id): Course
    {
        $fields         = CourseFieldList::fieldsForDetail();
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
}
