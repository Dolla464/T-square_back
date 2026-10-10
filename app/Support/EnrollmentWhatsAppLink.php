<?php

namespace App\Support;

use App\Models\Course;
use App\Models\Setting;
use App\Models\Student;

class EnrollmentWhatsAppLink
{
    public static function build(Student $student, Course $course): ?string
    {
        $student->loadMissing('user');

        $whatsappNumber = Setting::query()->where('key', 'whatsapp')->value('value');

        if (! is_string($whatsappNumber) || trim($whatsappNumber) === '') {
            return null;
        }

        $studentName = $student->user?->name ?? $student->full_name;
        $message = "أهلاً، أنا {$studentName}، لقد قمت بالتسجيل للتو في كورس '{$course->title}' وأرغب في استكمال التفاصيل.";

        return 'https://wa.me/'.trim($whatsappNumber).'?text='.urlencode($message);
    }
}
