<?php

namespace App\Http\Resources\Admin\Certificate;

class CertificateFieldList
{
    /**
     * Fields returned for the paginated listing (index).
     * Dot-notation paths are traversed by AdminCertificateResource::filterFields().
     * Prefix "enrollments." paths are resolved by the service hydration layer.
     */
    public static function fieldsForList(): array
    {
        return [
            'id',
            'certificate_num',
            'status',
            'issued_at',
            'student.full_name',
            'student.user.email',
            'course.title',
            'enrollments.is_completed',
            'enrollments.learningGroup.group_name',
        ];
    }

    /**
     * Fields returned for a single-record detail view (show).
     */
    public static function fieldsForDetail(): array
    {
        return array_merge(self::fieldsForList(), [
            'certificate_url',
            'created_at',
            'updated_at',
        ]);
    }
}
