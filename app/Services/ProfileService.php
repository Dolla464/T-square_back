<?php

namespace App\Services;

use App\Models\Student;
use App\Models\User;

class ProfileService
{
    public function show(User $user): User
    {
        $relation = $this->resolveProfileRelation($user->role);

        if ($relation) {
            if ($relation === 'student') {
                $this->ensureStudentProfile($user);
                $user->load([
                    'student:id,user_id,full_name,avatar,gender,phone',
                ]);
            } else {
                $user->load($relation);
            }
        }

        return $user;
    }

    public function update(User $user, array $validated): User
    {
        $relation = $this->resolveProfileRelation($user->role);
        if ($relation === 'student') {
            $this->ensureStudentProfile($user);
            $user->load('student');
        }
        $profile = $relation ? $user->{$relation} : null;

        $userData = array_filter([
            'name' => $validated['name'] ?? null,
            'password' => $validated['password'] ?? null,
        ], fn($value) => !is_null($value));

        if (!empty($userData)) {
            $user->update($userData);
        }

        if ($profile) {
            $profileData = array_filter([
                'full_name' => $validated['full_name'] ?? null,
                'gender' => $validated['gender'] ?? null,
                'avatar' => $validated['avatar'] ?? null,
            ], fn($value) => !is_null($value));

            if (!empty($profileData)) {
                $profile->update($profileData);
            }
        }

        return $this->show($user->fresh());
    }

    private function resolveProfileRelation(string $role): ?string
    {
        return match ($role) {
            'student' => 'student',
            'admin' => 'admin',
            'instructor' => 'instructor',
            default => null,
        };
    }

    private function ensureStudentProfile(User $user): Student
    {
        return Student::firstOrCreate(
            ['user_id' => $user->id],
            [
                'full_name' => $user->name,
                'phone' => null,
                'enrollment_number' => 'TEMP-' . $user->id,
                'group_id' => null,
                'avatar' => null,
                'gender' => null,
                'status' => 'active',
            ]
        );
    }
}
