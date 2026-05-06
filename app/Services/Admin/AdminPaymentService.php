<?php

namespace App\Services\Admin;

use App\Http\Resources\Admin\Payment\PaymentFieldList;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Order;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class AdminPaymentService
{
    public function index(array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        $fields = PaymentFieldList::fieldsForList();

        $query = Order::query()
            ->select($this->selectColumns($fields))
            ->with($this->relationsForFields($fields))
            ->latest();

        if (! empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $query->whereHas('student', function (Builder $q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->paginate($perPage);
    }

    public function show(int $id): Order
    {
        $fields = PaymentFieldList::fieldsForDetail();

        return Order::query()
            ->select($this->selectColumns($fields))
            ->with($this->relationsForFields($fields))
            ->findOrFail($id);
    }

    public function update(int $id, array $data): Order
    {
        return DB::transaction(function () use ($id, $data) {
            $order = Order::query()->lockForUpdate()->findOrFail($id);

            $studentId = $data['student_id'] ?? $order->student_id;

            $order->fill([
                'student_id' => $studentId,
                'total_amount' => $data['total_amount'] ?? $order->total_amount,
                'status' => $data['status'] ?? $order->status,
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
                        throw (new ModelNotFoundException())->setModel(Course::class, [$courseId]);
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
        Order::query()->findOrFail($id)->delete();
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

