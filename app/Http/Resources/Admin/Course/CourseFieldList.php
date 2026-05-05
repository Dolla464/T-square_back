<?php

namespace App\Http\Resources\Admin\Course;

class CourseFieldList
{
    /**
     * Fields used for listing (index).
     * Update this single list when you want columns to appear in the admin index.
     */
    public static function fieldsForList(): array
    {
        return [
            'id',
            'title',
            'slug',
            'short_description',
            'thumbnail',
            'cover_image',
            'preview_video',
            'google_drive_link',
            'attendance_type',
            'price',
            'level',
            'language',
            'duration_weeks',
            'duration_hours',
            'status',
            'is_featured',
            'is_free',
            'category_id',
            'instructor_id',
            'published_at',
            'avg_rating',
            'total_reviews',
            'total_students',
            'total_revenue',
        ];
    }

    /**
     * Fields used for a detailed resource (show).
     * You can include more fields here if you need them in the detail response.
     */
    public static function fieldsForDetail(): array
    {
        return array_merge(self::fieldsForList(), [
            'description',
            'price_before',
            'discount_price',
            'created_at',
            'updated_at',
            'deleted_at',
        ]);
    }
}
