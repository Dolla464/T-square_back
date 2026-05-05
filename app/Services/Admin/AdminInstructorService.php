<?php

namespace App\Services\Admin;

use App\Models\Instructor;
use App\Traits\HandleImageUploadTrait;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Storage;

class AdminInstructorService
{
    use HandleImageUploadTrait;

    /**
     * Get all instructors paginated.
     */
    public function index(int $perPage = 10, array $filters = []): LengthAwarePaginator
    {
        return Instructor::with('user:id,email')
            ->when(isset($filters['search']), function ($query) use ($filters) {
                $search = $filters['search'];
                $query->where(function ($q) use ($search) {
                    $q->where('full_name', 'like', "%{$search}%")
                        ->orWhereHas('user', function ($u) use ($search) {
                            $u->where('email', 'like', "%{$search}%");
                        });
                });
            })
            ->when(isset($filters['status']), function ($query) use ($filters) {
                // assume that the status field is in the instructors table
                $query->where('status', $filters['status']);
            })
            ->latest()
            ->paginate($perPage);
    }

    /**
     * Get a single instructor.
     */
    public function show(Instructor $instructor): Instructor
    {
        return $instructor->load('user:id,email');
    }

    /**
     * Update the instructor.
     */
    public function update(Instructor $instructor, array $data): Instructor
    {
        if (isset($data['avatar']) && $data['avatar'] instanceof UploadedFile) {
            $data['avatar'] = $this->uploadImage(
                $data['avatar'],
                'instructors/avatars',
                $instructor->getRawOriginal('avatar')
            );
        } else {
            // remove the avatar field from the array if no new file is uploaded to avoid deleting the old image
            unset($data['avatar']);
        }

        $instructor->update($data);

        return $instructor->load('user:id,email');
    }

    /**
     * Delete the instructor.
     */
    public function destroy(Instructor $instructor): void
    {
        if ($instructor->courses()->exists()) {
            throw new \Exception('Instructor has active courses, cannot be deleted.Please delete the courses first.');
        }
        $avatar = $instructor->getRawOriginal('avatar');
        if ($avatar && Storage::disk('public')->exists($avatar)) {
            Storage::disk('public')->delete($avatar);
        }

        $instructor->delete();
    }
}
