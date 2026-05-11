<?php

namespace App\Services\Admin;

use App\Http\Resources\Admin\Certificate\CertificateFieldList;
use App\Models\Certificate;
use App\Models\Enrollment;
use App\Services\User\CertificateService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class AdminCertificateService
{
    public function __construct(private readonly CertificateService $certificateService)
    {
    }

    public function index(array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        $fields = CertificateFieldList::fieldsForList();

        $query = Certificate::query()
            ->select($this->selectColumns($fields))
            ->with($this->relationsForFields($fields))
            ->latest();

        if (! empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $query->whereHas('student', function (Builder $q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%");
            });
        }

        $paginator = $query->paginate($perPage);
        $this->hydrateEnrollments($paginator->getCollection(), $fields);

        return $paginator;
    }

    public function show(int $id): Certificate
    {
        $fields = CertificateFieldList::fieldsForDetail();

        $certificate = Certificate::query()
            ->select($this->selectColumns($fields))
            ->with($this->relationsForFields($fields))
            ->findOrFail($id);

        $this->hydrateEnrollments(collect([$certificate]), $fields);

        return $certificate;
    }

    public function update(int $id, array $data): Certificate
    {
        return DB::transaction(function () use ($id, $data) {
            $certificate = Certificate::query()->lockForUpdate()->findOrFail($id);

            $studentId = $certificate->student_id;
            $courseId = $certificate->course_id;

            if (array_key_exists('is_completed', $data)) {
                $isCompleted = (bool) $data['is_completed'];

                Enrollment::query()
                    ->where('student_id', $studentId)
                    ->where('course_id', $courseId)
                    ->update([
                        'is_completed' => $isCompleted,
                        'completed_at' => $isCompleted ? now() : null,
                    ]);
            }

            $reissue = (bool) ($data['reissue'] ?? false);
            if (! $reissue) {
                return $this->show($certificate->id);
            }

            $enrollment = Enrollment::query()
                ->with(['student', 'course'])
                ->where('student_id', $studentId)
                ->where('course_id', $courseId)
                ->orderByDesc('id')
                ->first();

            if (! $enrollment || ! $enrollment->is_completed) {
                throw ValidationException::withMessages([
                    'reissue' => ['Cannot re-issue a certificate unless the course is completed.'],
                ]);
            }

            // Remove old PDF file (best effort).
            if (! empty($certificate->certificate_url)) {
                Storage::disk('public')->delete($certificate->certificate_url);
            }

            // Delete old certificate record to allow re-issue logic to create a new one.
            Certificate::query()->whereKey($certificate->id)->delete();

            $newCertificate = $this->certificateService->issueCertificate($enrollment);

            return $this->show($newCertificate->id);
        });
    }

    public function destroy(int $id): void
    {
        Certificate::query()->findOrFail($id);
        Certificate::query()->whereKey($id)->delete();
    }

    // /**
    //  * @return array<int, string>
    //  */
    private function selectColumns(array $fields): array
    {
        // Always include FKs for eager-loaded relations.
        $columns = ['id', 'student_id', 'course_id'];

        foreach ($fields as $field) {
            if (str_contains($field, '.')) {
                continue;
            }
            $columns[] = $field;
        }

        return array_values(array_unique($columns));
    }

    // /**
    //  * @return array<int, string>
    //  */
    private function relationsForFields(array $fields): array
    {
        $with = [];

        foreach ($fields as $field) {
            if (str_starts_with($field, 'student.')) {
                $with['student'] = function ($q) {
                    $q->select(['id', 'user_id', 'full_name']);
                };
            }
            if (str_starts_with($field, 'student.user.')) {
                $with['student.user'] = function ($q) {
                    $q->select(['id', 'email']);
                };
            }
            if (str_starts_with($field, 'course.')) {
                $with['course'] = function ($q) {
                    $q->select(['id', 'title']);
                };
            }
        }

        return $with;
    }

    /**
     * Hydrate enrollments for a set of certificates without N+1.
     *
     * The admin list needs `enrollments.is_completed` but the natural Eloquent
     * relation can't be constrained by both student_id and course_id safely in
     * eager-loading for SQLite. We load them in one query and attach them.
     *
     * @param  Collection<int, Certificate>  $certificates
     * @param  array<int, string>  $fields
     */
    private function hydrateEnrollments(Collection $certificates, array $fields): void
    {
        if ($certificates->isEmpty()) {
            return;
        }

        $needsEnrollments = collect($fields)->contains(fn ($f) => str_starts_with($f, 'enrollments.'));
        if (! $needsEnrollments) {
            return;
        }

        // Build OR-scoped pairs (student_id, course_id) for current page only.
        $pairs = $certificates
            ->map(fn (Certificate $c) => ['student_id' => $c->student_id, 'course_id' => $c->course_id])
            ->unique(fn ($p) => $p['student_id'] . ':' . $p['course_id'])
            ->values();

        $enrollments = Enrollment::query()
            ->select(['id', 'student_id', 'course_id', 'is_completed'])
            ->where(function ($q) use ($pairs) {
                foreach ($pairs as $pair) {
                    $q->orWhere(function ($qq) use ($pair) {
                        $qq->where('student_id', $pair['student_id'])
                            ->where('course_id', $pair['course_id']);
                    });
                }
            })
            ->get()
            ->groupBy(fn (Enrollment $e) => $e->student_id . ':' . $e->course_id);

        foreach ($certificates as $certificate) {
            $key = $certificate->student_id . ':' . $certificate->course_id;
            $certificate->setRelation('enrollments', $enrollments->get($key, collect([])));
        }
    }
}
