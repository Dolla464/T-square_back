<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class AdminReviewCollection extends ResourceCollection
{
    // نغير اسم المفتاح الافتراضي من 'data' إلى 'reviews' ليتوافق مع رغبتك
    public static $wrap = 'reviews';

    public function toArray(Request $request): array
    {
        // نرجع المجموعة فقط، والـ Pagination سيُدمج تلقائياً في الأسفل
        return [
            'reviews' => $this->collection,
        ];
    }

    /**
     * دمج البيانات الإضافية (مثل success والـ stats) في جذر الـ JSON
     */
    public function with(Request $request): array
    {
        return [
            'success' => true,
            'stats'   => $this->additional['stats'] ?? null,
        ];
    }
}
