<?php

namespace App\Services\Admin;

use App\Http\Resources\Admin\Payment\PaymentFieldList;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Order;
use App\Models\Student;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdminPaymentService
{
    public function index(array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        $fields = PaymentFieldList::fieldsForList();

        $query = Order::query()
            ->select($this->selectColumns($fields))
            ->with($this->relationsForFields($fields))
            ->latest();

        $this->applyFilters($query, $filters);

        return $query->paginate($perPage);
    }

    /**
     * @return Collection<int, Order>
     */
    public function getOrdersForExport(array $filters = []): Collection
    {
        $fields = PaymentFieldList::fieldsForDetail();

        $query = Order::query()
            ->select($this->selectColumns($fields))
            ->with($this->relationsForFields($fields))
            ->latest();

        $this->applyFilters($query, $filters);

        return $query->get();
    }

    public function show(int $id): Order
    {
        $fields = PaymentFieldList::fieldsForDetail();

        return Order::query()
            ->select($this->selectColumns($fields))
            ->with($this->relationsForFields($fields))
            ->findOrFail($id);
    }

    public function store(array $data): Order
    {
        return DB::transaction(function () use ($data) {
            $student = Student::with('user')->findOrFail($data['student_id']);
            $course = Course::findOrFail($data['course_id']);
            $attendanceType = strtolower(trim((string) $data['attendance_type']));

            $this->assertAttendanceTypeSupported($course, $attendanceType);

            $order = Order::create([
                'student_id' => $student->id,
                'total_amount' => $course->price,
                'status' => 'completed',
                'billing_name' => $data['billing_name'] ?? $student->full_name,
                'billing_email' => $data['billing_email'] ?? optional($student->user)->email,
                'billing_phone' => $data['billing_phone'] ?? $student->phone,
                'notes' => $data['notes'] ?? null,
            ]);

            Enrollment::create([
                'student_id' => $student->id,
                'course_id' => $course->id,
                'order_id' => $order->id,
                'price_paid' => $course->price,
                'attendance_type' => $attendanceType,
                'is_completed' => false,
            ]);

            return $this->show($order->id);
        });
    }

    public function update(int $id, array $data): Order
    {
        return DB::transaction(function () use ($id, $data) {
            $order = Order::query()->lockForUpdate()->findOrFail($id);

            $wasCompleted = $order->status === 'completed';
            $requestedStatus = $data['status'] ?? $order->status;
            $enteringCompleted = ! $wasCompleted && $requestedStatus === 'completed';

            if ($enteringCompleted) {
                $this->completeOrderEnrollment($order, $data);
            } elseif (
                $wasCompleted
                && $requestedStatus === 'completed'
                && array_key_exists('attendance_type', $data)
                && $data['attendance_type'] !== null
            ) {
                $enrollment = $this->resolveEnrollmentForUpdate($order);

                if ($enrollment !== null) {
                    $course = Course::query()->findOrFail($enrollment->course_id);
                    $attendanceType = strtolower(trim((string) $data['attendance_type']));

                    $this->assertAttendanceTypeSupported($course, $attendanceType);
                    $this->applyAttendanceTypeToEnrollment($enrollment, $attendanceType);
                }
            }

            $studentId = $data['student_id'] ?? $order->student_id;

            $order->fill([
                'student_id' => $studentId,
                'total_amount' => $data['total_amount'] ?? $order->total_amount,
                'status' => $requestedStatus,
                'billing_name' => $data['billing_name'] ?? $order->billing_name,
                'billing_email' => $data['billing_email'] ?? $order->billing_email,
                'billing_phone' => $data['billing_phone'] ?? $order->billing_phone,
                'notes' => array_key_exists('notes', $data) ? $data['notes'] : $order->notes,
            ]);
            $order->save();

            if (! empty($data['course_id']) || ! empty($data['student_id'])) {
                $courseId = $data['course_id'] ?? null;

                if ($courseId) {

                    $course = Course::query()->find($courseId);
                    if (! $course) {
                        throw (new ModelNotFoundException)->setModel(Course::class, [$courseId]);
                    }
                }

                $enrollment = $order->enrollments()->orderBy('id')->first();

                if ($enrollment) {
                    $enrollment->update([
                        'student_id' => $studentId,
                        'course_id' => $courseId ?? $enrollment->course_id,
                        'price_paid' => $data['price_paid'] ?? $enrollment->price_paid,
                    ]);
                } elseif ($courseId) {
                    Enrollment::query()->create([
                        'student_id' => $studentId,
                        'course_id' => $courseId,
                        'order_id' => $order->id,
                        'price_paid' => $data['price_paid'] ?? $order->total_amount,
                        'is_completed' => false,
                        'completed_at' => null,
                    ]);
                }
            }

            return $this->show($order->id);
        });
    }

    public function destroy(int $id): void
    {
        DB::transaction(function () use ($id) {
            $order = Order::query()
                ->with('enrollments')
                ->lockForUpdate()
                ->findOrFail($id);

            if ($order->status === 'completed') {
                foreach ($order->enrollments as $enrollment) {
                    Course::query()
                        ->whereKey($enrollment->course_id)
                        ->decrement('total_students');

                    if ($enrollment->price_paid > 0) {
                        Course::query()
                            ->whereKey($enrollment->course_id)
                            ->decrement('total_revenue', $enrollment->price_paid);
                    }
                }
            }

            $order->delete();
        });
    }

    private function completeOrderEnrollment(Order $order, array $data): void
    {
        $enrollment = $this->resolveEnrollmentForUpdate($order);

        if ($enrollment === null) {
            throw ValidationException::withMessages([
                'status' => ['Cannot complete an order without an associated enrollment.'],
            ]);
        }

        if (! empty($data['course_id']) && (int) $data['course_id'] !== (int) $enrollment->course_id) {
            throw ValidationException::withMessages([
                'course_id' => ['Cannot change the course while completing an order.'],
            ]);
        }

        $course = Course::query()->findOrFail($enrollment->course_id);
        $attendanceType = strtolower(trim((string) $data['attendance_type']));

        $this->assertAttendanceTypeSupported($course, $attendanceType);
        $this->applyAttendanceTypeToEnrollment($enrollment, $attendanceType);
    }

    private function resolveEnrollmentForUpdate(Order $order): ?Enrollment
    {
        return Enrollment::query()
            ->where('order_id', $order->id)
            ->orderBy('id')
            ->lockForUpdate()
            ->first();
    }

    private function assertAttendanceTypeSupported(Course $course, string $attendanceType): void
    {
        if (! $course->supportsAttendanceType($attendanceType)) {
            throw ValidationException::withMessages([
                'attendance_type' => ['The selected attendance type is not supported by this course.'],
            ]);
        }
    }

    private function applyAttendanceTypeToEnrollment(Enrollment $enrollment, string $attendanceType): void
    {
        $currentType = $enrollment->attendance_type;

        if ($currentType === null) {
            $enrollment->update(['attendance_type' => $attendanceType]);

            return;
        }

        if (strtolower((string) $currentType) === $attendanceType) {
            return;
        }

        throw ValidationException::withMessages([
            'attendance_type' => ['The enrollment attendance type cannot be changed.'],
        ]);
    }

    private function applyFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $query->whereHas('student', function (Builder $q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        if (! empty($filters['attendance_type'])) {
            if ($filters['attendance_type'] === 'unassigned') {
                $query->whereHas('enrollments', function (Builder $q): void {
                    $q->whereNull('attendance_type');
                });
            } else {
                $query->whereHas('enrollments', function (Builder $q) use ($filters): void {
                    $q->where('attendance_type', $filters['attendance_type']);
                });
            }
        }
    }

    /**
     * @return array<int, string>
     */
    private function selectColumns(array $fields): array
    {
        $columns = ['id', 'student_id'];

        foreach ($fields as $field) {
            if (str_contains($field, '.')) {
                continue;
            }
            $columns[] = $field;
        }

        return array_values(array_unique($columns));
    }

    /**
     * @return array<int, string>
     */
    private function relationsForFields(array $fields): array
    {
        $with = [];

        foreach ($fields as $field) {
            if (str_starts_with($field, 'student.')) {
                $with[] = 'student';
            }
            if (str_starts_with($field, 'student.user.')) {
                $with[] = 'student.user';
            }
            if (str_starts_with($field, 'enrollments.')) {
                $with[] = 'enrollments.course';
            }
        }

        return array_values(array_unique($with));
    }
}
