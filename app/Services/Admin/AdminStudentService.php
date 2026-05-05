<?php

namespace App\Services\Admin;

use App\Models\Student;
use App\Traits\HandleImageUploadTrait;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Storage;

class AdminStudentService
{
    use HandleImageUploadTrait;

    /**
     * Get all students paginated.
     */
    public function index(int $perPage = 10): LengthAwarePaginator
    {
        return Student::with('user:id,email')
            ->latest()
            ->paginate($perPage);
    }

    /**
     * Get a single student.
     */
    public function show(Student $student): Student
    {
        return $student->load('user:id,email');
    }

    /**
     * Update the student.
     */
    public function update(Student $student, array $data): Student
    {
        if (isset($data['avatar']) && $data['avatar'] instanceof UploadedFile) {
            $data['avatar'] = $this->uploadImage(
                $data['avatar'],
                'students',
                $student->getRawOriginal('avatar')
            );
        } else {
            // إزالة حقل الصورة من المصفوفة لو لم يتم رفع ملف جديد حتى لا يتم تفريغ الصورة القديمة
            unset($data['avatar']);
        }

        $student->update($data);
        
        return $student->load('user:id,email');
    }

    /**
     * Delete the student.
     */
    public function destroy(Student $student): void
    {
        $avatar = $student->getRawOriginal('avatar');
        if ($avatar && Storage::disk('public')->exists($avatar)) {
            Storage::disk('public')->delete($avatar);
        }

        $student->delete();
    }
}
