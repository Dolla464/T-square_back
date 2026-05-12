<?php

namespace App\Observers;

use App\Models\Course;
use Illuminate\Support\Facades\Storage;

class CourseObserver
{
    /**
     * Handle the Course "created" event.
     */
    public function created(Course $course): void
    {
        //
    }

    /**
     * Handle the Course "updated" event.
     */
    public function updated(Course $course): void
    {
        //
    }

    /**
     * Handle the Course "deleted" event.
     */
    public function deleting(Course $course): void
    {
        if ($course->isForceDeleting()) {
            $this->deleteFiles($course);
            $course->previews()->forceDelete(); // حذف نهائي للعلاقات
        } else {
            $course->previews()->delete(); // حذف مؤقت للعلاقات
        }
    }

    protected function deleteFiles(Course $course): void
    {
        // حذف صور الكورس
        foreach (['thumbnail', 'cover_image', 'preview_video'] as $field) {
            $path = $course->getRawOriginal($field);
            if ($path) {
                Storage::disk('public')->delete($path);
            }
        }

        // حذف فيديوهات المعاينة
        $course->previews()->each(function ($preview) {
            if ($preview->video_provider === 'local' && $preview->video_url) {
                Storage::disk('public')->delete($preview->video_url);
            }
        });
    }

    /**
     * Handle the Course "restored" event.
     */
    public function restored(Course $course): void
    {
        //
    }

    /**
     * Handle the Course "force deleted" event.
     */
    public function forceDeleted(Course $course): void
    {
        //
    }
}
