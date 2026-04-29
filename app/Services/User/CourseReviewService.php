<?php

namespace App\Services\User;

use App\Models\CourseReview;
use Illuminate\Database\Eloquent\Collection;

class CourseReviewService
{
    /**
     * هات اخر 5 reviews عموما
     * مفيش اي n+1 problem 
     */

     public function getLatestReviews(): Collection
     {
         return CourseReview::with([
                 'student:id,avatar,full_name',
                 'course:id,title',
                 'instructor:id,full_name',
             ])
             ->select([
                 'id',
                 'course_id',
                 'student_id',
                 'instructor_id',
                 'rating',
                 'overall_comment',
                 'created_at',
             ])
             ->latest()
             ->limit(5)
             ->get();
     } 

    /**
     * هات reviews الخاصة بكورس معين
     * مفيش اي n+1 problem
     */
    public function getCourseReviews(int $courseId): Collection
    {
        return CourseReview::with([
                'student:id,avatar,full_name',
                'instructor:id,full_name',
            ])
            ->where('course_id', $courseId)
            ->select([
                'id',
                'course_id',
                'student_id',
                'instructor_id',
                'rating',
                'overall_comment',
                'created_at',
            ])
            ->latest()
            ->limit(5)
            ->get();
    }

}
