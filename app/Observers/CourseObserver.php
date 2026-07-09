<?php

namespace App\Observers;

use App\Models\Course;
use App\Support\HomePageCache;
use Illuminate\Support\Facades\Storage;

class CourseObserver
{
    public function saved(Course $course): void
    {
        HomePageCache::forget();
    }

    public function deleted(Course $course): void
    {
        HomePageCache::forget();
    }

    /**
     * Before deletion (soft or hard)
     */
    public function deleting(Course $course): void
    {
        if (! $course->isForceDeleting()) {
            // Soft delete state:
            // 1. Convert the status to draft
            $course->status = 'draft';
            $course->save();

            // 2. Delete the relationships temporarily (if other tables also use SoftDeletes)
            $course->previews()->delete();
            // Note: If the learnings table also uses SoftDeletes, the line below will be for ForceDelete only
        }
    }

    /**
     * After final deletion
     */
    public function forceDeleted(Course $course): void
    {
        // 1. Delete the physical files
        $this->deleteFiles($course);

        // 2. Delete all the relationships finally from the database
        $course->previews()->forceDelete();
        
        // ✅ Delete the learnings finally
        $course->learnings()->delete(); 
    }

    /**
     * When restoring from the trash
     */
    public function restored(Course $course): void
    {
        $course->status = 'draft';
        $course->save();

        // Restore the relationships that support Soft Delete
        $course->previews()->restore();
    }

    /**
     * Clean up images and videos
     */
    protected function deleteFiles(Course $course): void
    {
        // The main images
        foreach (['thumbnail', 'cover_image', 'preview_video'] as $field) {
            $path = $course->getRawOriginal($field);
            if ($path && Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
            }
        }

        // Uploaded course/preview videos
        $course->previews()->each(function ($preview) {
            if ($preview->video_provider === 'upload' && $preview->video_url) {
                if (Storage::disk('public')->exists($preview->video_url)) {
                    Storage::disk('public')->delete($preview->video_url);
                }
            }
        });
    }
}