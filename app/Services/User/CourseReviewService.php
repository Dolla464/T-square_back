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

}
