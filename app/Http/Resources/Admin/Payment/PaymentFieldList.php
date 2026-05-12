<?php

namespace App\Http\Resources\Admin\Payment;

class PaymentFieldList
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
            'total_amount',
            'status',
            'enrollments.course.title',
            'billing_name',
        ];
    }

    /**
     * Fields used for a detailed resource (show).
     * You can include more fields here if you need them in the detail response.
     */
    public static function fieldsForDetail(): array
    {
        return array_merge(self::fieldsForList(), [
            'notes',
            'billing_email',
            'billing_phone',
        ]);
    }
}
