<?php

namespace App\Services\Admin;

use App\Http\Resources\Admin\Certificate\CertificateFieldList;
use App\Models\Certificate;
use App\Models\Enrollment;
use App\Services\User\CertificateService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminCertificateService
{
    public function __construct(private readonly CertificateService $certificateService) {}

    // ─── Listing ─────────────────────────────────────────────────────────────

    /**
     * Return a paginated list of certificates with optional filters and global status counts.
     *
     * Supported filter keys:
     * search   – fuzzy-matches student full_name OR course title
     * group_id – narrows to certificates whose enrollment has this group_id
     * status   – exact match against the CertificateStatus enum value
     * * @return array{paginator: \Illuminate\Contracts\Pagination\LengthAwarePaginator, stats: array}
     */
    public function index(array $filters = [], int $perPage = 10): array
    {
        // 1. Calculate the total counts for certificates based on the three statuses using a single quick query
        $statsData = \Illuminate\Support\Facades\DB::table('certificates')
            ->selectRaw("
                COUNT(CASE WHEN status = 'issued' THEN 1 END) as issued_count,
                COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_count,
                COUNT(CASE WHEN status = 'revoked' THEN 1 END) as revoked_count
            ")
            ->first();

        // 2. Build the query to fetch the regular data and apply the filters
        $fields = CertificateFieldList::fieldsForList();

        $query = Certificate::query()
            ->select($this->selectColumns($fields))
            ->with($this->relationsForFields($fields))
            ->latest();

        $this->applySearch($query, $filters['search'] ?? null);
        $this->applyGroupId($query, $filters['group_id'] ?? null);
        $this->applyStatus($query, $filters['status'] ?? null);

        $paginator = $query->paginate($perPage);
        $this->hydrateEnrollments($paginator->getCollection(), $fields);

        // 3. Return the combined array of data and counts
        return [
            'paginator' => $paginator,
            'stats' => [
                'issued'  => (int) ($statsData->issued_count ?? 0),
                'pending' => (int) ($statsData->pending_count ?? 0),
                'revoked' => (int) ($statsData->revoked_count ?? 0),
            ]
        ];
    }

    // ─── Detail ──────────────────────────────────────────────────────────────

    /**
     * Return a fully-loaded single certificate (Route Model Binding path).
     */
    public function show(Certificate $certificate): Certificate
    {
        $fields = CertificateFieldList::fieldsForDetail();

        // Re-query to enforce select columns and eager loads for consistency.
        $certificate = Certificate::query()
            ->select($this->selectColumns($fields))
            ->with($this->relationsForFields($fields))
            ->findOrFail($certificate->id);

        $this->hydrateEnrollments(collect([$certificate]), $fields);

        return $certificate;
    }

    // ─── Download ────────────────────────────────────────────────────────────

    /**
     * Return a streamed download response for the certificate PDF/file.
     *
     * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
     */
    public function download(Certificate $certificate): StreamedResponse
    {
        $path = $certificate->certificate_url;

        abort_unless(
            $path && Storage::disk('public')->exists($path),
            Response::HTTP_NOT_FOUND,
            'Certificate file not found on storage.'
        );

        $fileName = 'certificate-' . $certificate->certificate_num . '.' . pathinfo($path, PATHINFO_EXTENSION);

        return Storage::disk('public')->download($path, $fileName);
    }

    // ─── Update ──────────────────────────────────────────────────────────────

    public function update(int $id, array $data): Certificate
    {
        return DB::transaction(function () use ($id, $data) {
            $certificate = Certificate::query()->lockForUpdate()->findOrFail($id);

            $studentId = $certificate->student_id;
            $courseId  = $certificate->course_id;

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

            if (array_key_exists('status', $data)) {
                $certificate->update(['status' => $data['status']]);
            }

            $reissue = (bool) ($data['reissue'] ?? false);
            if (! $reissue) {
                return $this->show($certificate);
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

            if (! empty($certificate->certificate_url)) {
                Storage::disk('public')->delete($certificate->certificate_url);
            }

            Certificate::query()->whereKey($certificate->id)->delete();

            $newCertificate = $this->certificateService->issueCertificate($enrollment);

            return $this->show($newCertificate);
        });
    }

    // ─── Destroy ─────────────────────────────────────────────────────────────

    public function destroy(int $id): void
    {
        Certificate::query()->findOrFail($id);
        Certificate::query()->whereKey($id)->delete();
    }

    // ─── Query helpers ────────────────────────────────────────────────────────

    /**
     * Global text search: student full_name OR course title.
     */
    private function applySearch(Builder $query, ?string $search): void
    {
        $search = trim((string) $search);

        if ($search === '') {
            return;
        }

        $query->where(function (Builder $q) use ($search): void {
            $q->whereHas('student', fn(Builder $s) => $s->where('full_name', 'like', "%{$search}%"))
                ->orWhereHas('course',   fn(Builder $c) => $c->where('title',     'like', "%{$search}%"));
        });
    }

    /**
     * Filter by the exact LearningGroup ID linked to the certificate's enrollment.
     *
     * Uses a correlated EXISTS subquery so that both student_id and course_id
     * are honoured without running into SQLite whereColumn edge-cases.
     */
    private function applyGroupId(Builder $query, mixed $groupId): void
    {
        if ($groupId === null || $groupId === '') {
            return;
        }

        $query->whereExists(function (\Illuminate\Database\Query\Builder $sub) use ($groupId): void {
            $sub->from('enrollments')
                ->whereColumn('enrollments.student_id', 'certificates.student_id')
                ->whereColumn('enrollments.course_id',  'certificates.course_id')
                ->where('enrollments.group_id', (int) $groupId);
        });
    }

    /**
     * Strict status filter — accepts enum value string (issued/pending/revoked).
     */
    private function applyStatus(Builder $query, mixed $status): void
    {
        if ($status === null || $status === '') {
            return;
        }

        $value = $status instanceof \BackedEnum ? $status->value : (string) $status;
        $query->where('status', $value);
    }

    // ─── Column / relation resolution ────────────────────────────────────────

    /** @return array<int, string> */
    private function selectColumns(array $fields): array
    {
        $columns = ['id', 'student_id', 'course_id'];

        foreach ($fields as $field) {
            if (str_contains($field, '.')) {
                continue;
            }
            $columns[] = $field;
        }

        return array_values(array_unique($columns));
    }

    /** @return array<string, \Closure> */
    private function relationsForFields(array $fields): array
    {
        $with = [];

        foreach ($fields as $field) {
            if (str_starts_with($field, 'student.user.')) {
                $with['student.user'] = fn($q) => $q->select(['id', 'email']);
            } elseif (str_starts_with($field, 'student.')) {
                $with['student'] ??= fn($q) => $q->select(['id', 'user_id', 'full_name']);
            }

            if (str_starts_with($field, 'course.')) {
                $with['course'] ??= fn($q) => $q->select(['id', 'title']);
            }
        }

        return $with;
    }

    /**
     * Hydrate enrollments (with learningGroup) for a batch of certificates
     * using a single query — avoids N+1 and the SQLite whereColumn edge-case.
     *
     * @param  Collection<int, Certificate>  $certificates
     * @param  array<int, string>             $fields
     */
    private function hydrateEnrollments(Collection $certificates, array $fields): void
    {
        if ($certificates->isEmpty()) {
            return;
        }

        $needsEnrollments = collect($fields)
            ->contains(fn(string $f) => str_starts_with($f, 'enrollments.'));

        if (! $needsEnrollments) {
            return;
        }

        $needsGroup = collect($fields)
            ->contains(fn(string $f) => str_starts_with($f, 'enrollments.learningGroup'));

        $pairs = $certificates
            ->map(fn(Certificate $c) => ['student_id' => $c->student_id, 'course_id' => $c->course_id])
            ->unique(fn(array $p) => $p['student_id'] . ':' . $p['course_id'])
            ->values();

        $enrollmentQuery = Enrollment::query()
            ->select(['id', 'student_id', 'course_id', 'group_id', 'is_completed'])
            ->where(function ($q) use ($pairs): void {
                foreach ($pairs as $pair) {
                    $q->orWhere(function ($qq) use ($pair): void {
                        $qq->where('student_id', $pair['student_id'])
                            ->where('course_id',  $pair['course_id']);
                    });
                }
            });

        if ($needsGroup) {
            $enrollmentQuery->with(['learningGroup:id,group_name']);
        }

        $enrollments = $enrollmentQuery->get()
            ->groupBy(fn(Enrollment $e) => $e->student_id . ':' . $e->course_id);

        foreach ($certificates as $certificate) {
            $key = $certificate->student_id . ':' . $certificate->course_id;
            $certificate->setRelation('enrollments', $enrollments->get($key, collect([])));
        }
    }
}
