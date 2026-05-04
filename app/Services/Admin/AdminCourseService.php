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

   

    // /**
    //  * Update the instructor.
    //  */
    // public function update(Instructor $instructor, array $data): Instructor
    // {
    //     if (isset($data['avatar']) && $data['avatar'] instanceof UploadedFile) {
    //         $data['avatar'] = $this->uploadImage(
    //             $data['avatar'],
    //             'instructors/avatars',
    //             $instructor->getRawOriginal('avatar')
    //         );
    //     } else {
    //         // إزالة حقل الصورة من المصفوفة لو لم يتم رفع ملف جديد حتى لا يتم تفريغ الصورة القديمة
    //         unset($data['avatar']);
    //     }

    //     $instructor->update($data);

    //     return $instructor->load('user:id,email');
    // }

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
