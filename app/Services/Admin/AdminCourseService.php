<?php

namespace App\Services\Admin;

use App\Models\Course;
use App\Models\Instructor;
use App\Traits\HandleImageUploadTrait;
use App\Http\Resources\Admin\Course\CourseFieldList;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Storage;

class AdminCourseService
{
    use HandleImageUploadTrait;

    /**
     * Get all instructors paginated.
     */
    public function index(int $perPage = 10): LengthAwarePaginator
    {
        // use a central list of fields for the admin listing
        $fields = CourseFieldList::fieldsForList();

        // ensure foreign keys are present if relations are requested
        $fieldsForSelect = array_unique(array_merge($fields, ['category_id', 'instructor_id']));

        return Course::select($fieldsForSelect)->with([
            // include id so Eloquent can match the related model when selecting specific columns
            'instructor:id,full_name,phone,field',
            'category:id,name,icon',
        ])
            ->latest()
            ->paginate($perPage);
    }

    /**
     * Get a single course with all columns and all relationships loaded.
     * Uses a fresh query with eager loading to avoid N+1 queries.
     */
    /**
     * Get a single course by id with all relationships loaded.
     */
    public function show($id): Course
    {
        return Course::with(
            // include id in relation selects so Eloquent can match related models
            'instructor:id,full_name,phone,avatar,field,bio,gender,status',
            'category:id,name,slug,description,icon,image,parent_id,sort_order,status'
        )
            ->findOrFail($id);
    }

    /**
     * Create a new course and handle uploaded files.
     */
    public function create(array $data): Course
    {
        // handle image uploads
        if (!empty($data['thumbnail']) && $data['thumbnail'] instanceof UploadedFile) {
            $data['thumbnail'] = $this->uploadImage($data['thumbnail'], 'courses/thumbnails');
        }

        if (!empty($data['cover_image']) && $data['cover_image'] instanceof UploadedFile) {
            $data['cover_image'] = $this->uploadImage($data['cover_image'], 'courses/covers');
        }

        // create course
        $course = Course::create($data);

        // return fresh instance with relations loaded to avoid N+1 later
        return $this->freshWithRelations($course->id);
    }

    /**
     * Update an existing course and manage uploaded file replacements.
     */
    public function update($id, array $data): Course
    {
        $course = Course::findOrFail($id);

        if (array_key_exists('thumbnail', $data) && $data['thumbnail'] instanceof UploadedFile) {
            $data['thumbnail'] = $this->uploadImage(
                $data['thumbnail'],
                'courses/thumbnails',
                $course->getRawOriginal('thumbnail')
            );
        } else {
            unset($data['thumbnail']);
        }

        if (array_key_exists('cover_image', $data) && $data['cover_image'] instanceof UploadedFile) {
            $data['cover_image'] = $this->uploadImage(
                $data['cover_image'],
                'courses/covers',
                $course->getRawOriginal('cover_image')
            );
        } else {
            unset($data['cover_image']);
        }

        $course->update($data);

        return $this->freshWithRelations($course->id);
    }

    /**
     * Return a fresh Course with relations selected to avoid N+1 when serializing.
     */
    protected function freshWithRelations($id): Course
    {
        $fields = CourseFieldList::fieldsForDetail();
        $fieldsForSelect = array_unique(array_merge($fields, ['category_id', 'instructor_id']));

        return Course::select($fieldsForSelect)
            ->with([
                'instructor:id,full_name,phone,avatar,field,bio,gender,status',
                'category:id,name,slug,description,icon,image,parent_id,sort_order,status',
            ])
            ->findOrFail($id);
    }


    /**
     * Delete the given course by id and remove related uploaded files from storage.
     */
    public function destroy($id): void
    {
        $course = Course::findOrFail($id);

        // remove known uploaded files if they exist on the public disk
        foreach (['thumbnail', 'cover_image', 'preview_video'] as $field) {
            $path = $course->getRawOriginal($field);
            if ($path && Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
            }
        }

        $course->delete();
    }
}
