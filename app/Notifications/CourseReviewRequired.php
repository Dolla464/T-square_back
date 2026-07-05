<?php

namespace App\Notifications;

use App\Models\Enrollment;
use App\Models\LearningGroup;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class CourseReviewRequired extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Enrollment $enrollment,
        public readonly ?LearningGroup $group = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $this->enrollment->loadMissing('course');
        $courseTitle = $this->enrollment->course?->title ?? 'your course';

        $message = $this->group
            ? 'Your group "'.$this->group->group_name.'" is complete. Leave a review for "'.$courseTitle.'" to get your certificate.'
            : 'Congratulations! You passed the final exam for "'.$courseTitle.'". Leave a review to get your certificate.';

        return [
            'type'          => 'course_review_required',
            'title'         => 'Course Completed',
            'message'       => $message,
            'course_id'     => $this->enrollment->course_id,
            'enrollment_id' => $this->enrollment->id,
            'group_id'      => $this->group?->id,
            'icon'          => 'star',
        ];
    }
}
