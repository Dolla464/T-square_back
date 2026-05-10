<?php

namespace App\Http\Resources\Admin\Certificate;

class CertificateFieldList
{
    /**
     * Fields used for listing (index).
     * Update this single list when you want columns to appear in the admin index.
     */
    public static function fieldsForList(): array
    {
        return [
            'id',
            'student.full_name',
            'student.user.email',
            'course.title',
            'issued_at',
            'enrollments.is_completed',
        ];
    }

    /**
     * Fields used for a detailed resource (show).
     * You can include more fields here if you need them in the detail response.
     */
    public static function fieldsForDetail(): array
    {
        return array_merge(self::fieldsForList(), [
            'certificate_url',
            'certificate_num',
        ]);
    }
}
