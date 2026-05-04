<?php

namespace App\Services\Admin;

use App\Models\Course;
use App\Models\Instructor;
use App\Traits\HandleImageUploadTrait;
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
    return Course::with([
        // include id so Eloquent can match the related model when selecting specific columns
        'instructor:id,full_name,phone,field',
        'category:id,name,icon',
        ])
        ->latest()
        ->paginate($perPage);
    }

    // /**
    //  * Get a single instructor.
    //  */
    // public function show(Instructor $instructor): Instructor
    // {
    //     return $instructor->load('user:id,email');
    // }

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

    // /**
    //  * Delete the instructor.
    //  */
    // public function destroy(Instructor $instructor): void
    // {
    //     $avatar = $instructor->getRawOriginal('avatar');
    //     if ($avatar && Storage::disk('public')->exists($avatar)) {
    //         Storage::disk('public')->delete($avatar);
    //     }

    //     $instructor->delete();
    // }
}
