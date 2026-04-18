<?php

namespace App\Services;

use App\Models\Student;
use App\Models\User;
use App\Models\Instructor;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class UserService
{
    public function handleUserCreation(array $data)
    {
        return DB::transaction(function () use ($data) {
            // 1. إنشاء سجل اليوزر (بيانات الدخول)
            $user = User::create([
                'name'              => $data['name'],
                'email'             => $data['email'],
                'password'          => Hash::make($data['password']),
                'role'              => $data['role'],
                'email_verified_at' => $data['verified'] ?? null,
                'last_login_at'     => now(),
            ]);

            $user->assignRole($data['role']);


            // 2. إنشاء السجلات الفرعية بناءً على النوع
            if ($data['role'] === 'student') {
                // create random enrollment number
                $enrollmentNumber = $this->generateEnrollmentNumber();

                $user->student()->create([
                    'full_name'         => $data['name'],
                    'phone'             => $data['phone'] ?? null,
                    'enrollment_number' => $enrollmentNumber,
                    'group_id'          => $data['group_id'] ?? null,
                    'avatar'            => $data['avatar'] ?? null,
                    'gender'            => $data['gender'] ?? null,
                    'status'            => 'active',
                ]);
            } elseif ($data['role'] === 'instructor') {
                $user->instructor()->create([
                    'full_name'    => $data['name'],
                    'phone'        => $data['phone'] ?? null,
                    'bio'          => $data['bio'] ?? null,
                    'avatar'       => $data['avatar'] ?? null,
                    'gender'       => $data['gender'] ?? null,
                    'insta_url'    => $data['insta_url'] ?? null,
                    'linkedin_url' => $data['linkedin_url'] ?? null,
                    'facebook_url' => $data['facebook_url'] ?? null,
                    'status'       => 'active',
                    'avg_rating'   => 0,
                    'reviews_count' => 0,
                ]);
            }

            return $user;
        });
    }

    /**
     * دالة توليد رقم القيد: جزء نصي + أرقام عشوائية
     */
    private function generateEnrollmentNumber()
    {
        do {
            // مثلاً: TSQ-2026-1234 (جزء نصي ثابت + السنه + 4 أرقام عشوائية)
            $number = 'TSQ-' . Str::random(4) . '-' . date('Y');

            // التأكد أن الرقم مش موجود قبل كدة في الداتابيز (بسبب الـ unique constraint)
            $exists = Student::where('enrollment_number', $number)->exists();
        } while ($exists);

        return $number;
    }
}
