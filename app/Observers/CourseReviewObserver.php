<?php

namespace App\Observers;

use App\Events\ReviewSubmitted;
use App\Models\CourseReview;
use App\Support\HomePageCache;

class CourseReviewObserver
{
    /**
     * Handle the CourseReview "created" event.
     * لما الطالب يضيف تقييم جديد
     */
    public function created(CourseReview $review): void
    {
        HomePageCache::forget();
        // نطلق الحدث عشان الـ Queue يحدث الإحصائيات
        event(new ReviewSubmitted($review));
    }

    /**
     * Handle the CourseReview "updated" event.
     * لما الطالب يعدل تقييمه
     */
    public function updated(CourseReview $review): void
    {
        if ($review->wasChanged(['rating', 'review_status', 'status', 'overall_comment'])) {
            HomePageCache::forget();
        }

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
        HomePageCache::forget();
        // نطلق الحدث برضه عشان ينقص العدد ويعيد حساب المتوسط
        event(new ReviewSubmitted($review));
    }
}
