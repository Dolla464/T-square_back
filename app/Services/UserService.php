<?php

namespace App\Services;

use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UserService
{
    public function handleUserCreation(array $data)
    {
        return DB::transaction(function () use ($data) {
            // 1. create the user record (login data)
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'role' => $data['role'],
                'email_verified_at' => $data['verified'] ?? null,
                'last_login_at' => now(),
            ]);

            // assign the role (Spatie Permissions usually)
            $user->assignRole($data['role']);

            // 2. create the sub-records based on the type
            if ($data['role'] === 'student') {
                $user->student()->create([
                    'full_name' => $data['full_name'], // modify here: the original full name
                    'phone' => $data['phone'] ?? null,
                    'enrollment_number' => $this->generateEnrollmentNumber(),
                    'group_id' => $data['group_id'] ?? null,
                    'avatar' => $data['avatar'] ?? null,
                    'gender' => $data['gender'] ?? null,
                    'status' => 'active',
                ]);
            } elseif ($data['role'] === 'instructor') {
                $user->instructor()->create([
                    'full_name' => $data['full_name'], // modify here: the original full name
                    'phone' => $data['phone'] ?? null,
                    'bio' => $data['bio'] ?? null,
                    'field' => $data['field'] ?? null,
                    'avatar' => $data['avatar'] ?? null,
                    'gender' => $data['gender'] ?? null,
                    'insta_url' => $data['insta_url'] ?? null,
                    'linkedin_url' => $data['linkedin_url'] ?? null,
                    'facebook_url' => $data['facebook_url'] ?? null,
                    'status' => 'active',
                    'avg_rating' => 0,
                    'reviews_count' => 0,
                ]);
            }

            return $user;
        });
    }

    /**
     * generate the enrollment number: text part + random numbers
     */
    private function generateEnrollmentNumber()
    {
        do {
            // generate the number: TSQ-A1B2-2026
            $number = 'TSQ-'.strtoupper(Str::random(4)).'-'.date('Y');

            // check for duplication
            $exists = Student::where('enrollment_number', $number)->exists();
        } while ($exists);

        return $number;
    }
}
