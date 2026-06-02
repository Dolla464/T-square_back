<?php

namespace App\Models;

use App\Enums\CertificateStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Certificate extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'course_id',
        'certificate_url',
        'certificate_num',
        'issued_at',
        'status',
    ];

    protected $casts = [
        'issued_at'  => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'status'     => CertificateStatus::class,
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * All enrollments for this certificate's student.
     *
     * NOTE: The natural Eloquent relation cannot safely constrain by both
     * student_id AND course_id during eager-loading (SQLite + whereColumn).
     * The service layer manually hydrates the correct enrollment records
     * using a single batched query — see AdminCertificateService::hydrateEnrollments().
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class, 'student_id', 'student_id');
    }
}
