<?php

namespace App\Services;

use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UserService
{
    public function registerStudent(array $data): User
    {
        $data['role'] = 'student';
        $data['created_by'] = 'site';

        return $this->handleUserCreation($data);
    }

    public function handleUserCreation(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $user = User::create(Arr::only($data, ['name', 'email', 'password']));

            $user->forceFill([
                'role' => $data['role'],
                'email_verified_at' => $data['verified'] ?? null,
                'last_login_at' => now(),
            ])->save();

            $user->assignRole($data['role']);

            if ($data['role'] === 'student') {
                $student = $user->student()->make(Arr::only($data, [
                    'full_name',
                    'phone',
                    'avatar',
                    'gender',
                    'age',
                    'qualification',
                    'guardian_phone',
                    'national_id',
                    'address',
                    'notes',
                ]));

                $student->forceFill([
                    'enrollment_number' => $this->generateEnrollmentNumber(),
                    'status' => 'active',
                    'created_by' => $data['created_by'] ?? 'admin',
                ])->save();
            } elseif ($data['role'] === 'instructor') {
                $instructor = $user->instructor()->make(Arr::only($data, [
                    'full_name',
                    'phone',
                    'bio',
                    'field',
                    'avatar',
                    'gender',
                    'insta_url',
                    'linkedin_url',
                    'facebook_url',
                ]));

                $instructor->forceFill([
                    'status' => $data['status'] ?? 'active',
                    'avg_rating' => 0,
                    'reviews_count' => 0,
                ])->save();
            }

            return $user;
        });
    }

    /**
     * generate the enrollment number: text part + random numbers
     */
    private function generateEnrollmentNumber(): string
    {
        do {
            $number = 'TSQ-'.strtoupper(Str::random(4)).'-'.date('Y');

            $exists = Student::where('enrollment_number', $number)->exists();
        } while ($exists);

        return $number;
    }
}
