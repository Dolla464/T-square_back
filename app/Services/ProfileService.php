<?php

namespace App\Services;

use App\Models\Student;
use App\Models\User;
use App\Traits\HandleImageUploadTrait;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class ProfileService
{
    use HandleImageUploadTrait;

    public function show(User $user): User
    {
        $relation = $this->resolveProfileRelation($user);

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
        return DB::transaction(function () use ($user, $validated) {

            $relation = $this->resolveProfileRelation($user);

            if ($relation === 'student') {
                $this->ensureStudentProfile($user);
                $user->load('student');
            }

            $profile = $relation ? $user->{$relation} : null;

            $userData = [];
            if (isset($validated['full_name'])) {
                $fullName = trim($validated['full_name']);
                $nameParts = explode(' ', $fullName);
                $shortName = implode(' ', array_slice($nameParts, 0, 2));
                $userData['name'] = $shortName;
            }

            if (! empty($userData)) {
                $user->update($userData);
            }

            if ($profile) {
                $profileData = array_filter([
                    'full_name' => $validated['full_name'] ?? null,
                    'gender' => $validated['gender'] ?? null,
                    // لا نضع avatar هنا مباشرة لأنه قد يكون كائن ملف (File Object)
                ], fn ($value) => ! is_null($value));

                if (isset($validated['avatar']) && $validated['avatar'] instanceof UploadedFile) {
                    // استدعاء الـ Trait (الآن سيعمل بدون Intervention Image)
                    $profileData['avatar'] = $this->uploadImage(
                        $validated['avatar'],
                        'students',
                        $profile->avatar
                    );
                }

                if (! empty($profileData)) {
                    $profile->update($profileData);
                }
            }

            $user->refresh();

            return $this->show($user);
        });
    }

    private function resolveProfileRelation(User $user): ?string
    {
        // Spatie check
        return match (true) {
            $user->hasRole('student') => 'student',
            $user->hasRole('admin') => 'admin',
            $user->hasRole('instructor') => 'instructor',
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
                'enrollment_number' => 'TEMP-'.$user->id,
                'group_id' => null,
                'avatar' => null,
                'gender' => null,
                'status' => 'active',
            ]
        );
    }
}
