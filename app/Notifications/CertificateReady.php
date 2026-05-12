<?php

namespace App\Notifications;

use App\Models\Enrollment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class CertificateReady extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public Enrollment $enrollment) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'enrollment_id' => $this->enrollment->id,
            'course_title' => $this->enrollment->course->title,
            'message' => 'تهانينا! شهادتك لدورة '.$this->enrollment->course->title.' متاحة الآن للتحميل.',
            'action_link' => route('certificate.download', ['enrollment' => $this->enrollment->id]),
        ];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }
}
