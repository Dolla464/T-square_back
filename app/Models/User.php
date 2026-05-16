<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'last_login_at',
        'email_verified_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'last_login_at' => 'datetime',
        ];
    }

    public function getRoleAttribute()
    {
        // بدلاً من الاستعلام عن الجداول، اسأل سباتي
        if ($this->hasRole('admin')) {
            return 'admin';
        }
        if ($this->hasRole('instructor')) {
            return 'instructor';
        }

        return 'student';
    }

    public function instructor()
    {
        return $this->hasOne(Instructor::class);
    }

    public function admin()
    {
        return $this->hasOne(Admin::class);
    }

    public function student()
    {
        return $this->hasOne(Student::class);
    }

    // علاقة تجيب الكورسات الخاصة بمدرب معين
    public function courses()
    {
        return $this->hasManyThrough(Course::class, Instructor::class);
    }

    // الوصول للمجموعات من خلال ملف المدرب
    public function instructorGroups()
    {
        return $this->hasManyThrough(
            LearningGroup::class,
            Instructor::class,
            'user_id',
            'instructor_id'
        );
    }
}
