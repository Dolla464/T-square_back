<?php

namespace App\Services\User;

use App\Models\Instructor;
use Illuminate\Pagination\LengthAwarePaginator;

class InstructorService
{
    /**
     * جلب قائمة المحاضرين النشطين بنظام الـ Pagination
     */
    public function getActiveInstructors(int $perPage = 10): LengthAwarePaginator
    {
        return Instructor::query()
            ->select([
                'id',
                'user_id',
                'full_name',
                'field',
                'avatar',
                'insta_url',
                'linkedin_url',
                'facebook_url',
                'status',
            ])
            ->where('status', 'active')
            ->with(['user:id,email'])
            ->paginate($perPage);
    }
}
