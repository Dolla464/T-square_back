<?php

namespace App\Observers;

use App\Models\CourseReview;
use App\Events\ReviewSubmitted;

class CourseReviewObserver
{
    /**
     * Handle the CourseReview "created" event.
     * لما الطالب يضيف تقييم جديد
     */
    public function created(CourseReview $review): void
    {
        // نطلق الحدث عشان الـ Queue يحدث الإحصائيات
        event(new ReviewSubmitted($review));
    }

    /**
     * Handle the CourseReview "updated" event.
     * لما الطالب يعدل تقييمه
     */
    public function updated(CourseReview $review): void
    {
        // خطوة احترافية: نتأكد إنه غير عدد النجوم فعلاً 
        // عشان لو عدل نص الكومنت بس، منعملش لود على الداتابيز على الفاضي
        if ($review->wasChanged('rating')) {
            event(new ReviewSubmitted($review));
        }
    }

    /**
     * Handle the CourseReview "deleted" event.
     * لما التقييم يتمسح (سواء الطالب مسحه أو الأدمن)
     */
    public function deleted(CourseReview $review): void
    {
        // نطلق الحدث برضه عشان ينقص العدد ويعيد حساب المتوسط
        event(new ReviewSubmitted($review));
    }
}
