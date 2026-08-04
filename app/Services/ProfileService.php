<?php

namespace App\Services;

use App\Models\Student;
use App\Models\User;
use App\Traits\HandleImageUploadTrait;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

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
                    'student:id,user_id,full_name,avatar,gender,phone,age,qualification,guardian_phone,national_id,address,notes',
                ]);
            } elseif ($relation === 'instructor') {
                $user->load([
                    'instructor:id,user_id,full_name,avatar,gender,phone,field,bio,insta_url,linkedin_url,facebook_url',
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
            } elseif ($relation === 'instructor') {
                $user->load('instructor');
            }

            $profile = $relation ? $user->{$relation} : null;
            $avatarFolder = $relation === 'instructor' ? 'instructors/avatars' : 'students';

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
                $profileFields = [
                    'full_name' => $validated['full_name'] ?? null,
                    'gender' => $validated['gender'] ?? null,
                    'phone' => $validated['phone'] ?? null,
                    'field' => $validated['field'] ?? null,
                    'bio' => $validated['bio'] ?? null,
                    'insta_url' => $validated['insta_url'] ?? null,
                    'linkedin_url' => $validated['linkedin_url'] ?? null,
                    'facebook_url' => $validated['facebook_url'] ?? null,
                ];

                if ($relation === 'student') {
                    $profileFields = array_merge($profileFields, [
                        'age' => $validated['age'] ?? null,
                        'qualification' => $validated['qualification'] ?? null,
                        'guardian_phone' => $validated['guardian_phone'] ?? null,
                        'national_id' => $validated['national_id'] ?? null,
                        'address' => $validated['address'] ?? null,
                        'notes' => $validated['notes'] ?? null,
                    ]);
                }

                $profileData = array_filter(
                    $profileFields,
                    fn ($value) => ! is_null($value)
                );

                if (isset($validated['avatar']) && $validated['avatar'] instanceof UploadedFile) {
                    $existingAvatar = $relation === 'instructor'
                        ? $profile->getRawOriginal('avatar')
                        : $profile->avatar;

                    $profileData['avatar'] = $this->uploadImage(
                        $validated['avatar'],
                        $avatarFolder,
                        $existingAvatar
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
        $student = Student::firstOrNew(['user_id' => $user->id]);

        if ($student->exists) {
            return $student;
        }

        $student->fill([
            'full_name' => $user->name,
            'phone' => null,
            'avatar' => null,
            'gender' => null,
        ]);

        $student->forceFill([
            'enrollment_number' => 'TEMP-'.$user->id,
            'status' => 'active',
            'created_by' => 'site',
        ])->save();

        return $student;
    }

    /**
     * Update the user's password securely with current password verification.
     */
    public function updatePassword(User $user, array $validated): void
    {
        // 1. Verify the current password stored in the database
        if (! Hash::check($validated['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Current password is incorrect.']
            ]);
        }

        // 2. Update the password field and automatically hash the new password using the Casts feature available in the model
        $user->update([
            'password' => $validated['password']
        ]);
    }
}
