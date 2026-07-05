<?php

use App\Models\Enrollment;
use App\Models\Order;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Backfill completed orders for enrollments that have no order_id (legacy free enrollments).
     */
    public function up(): void
    {
        // Accumulate counts per course during the loop — no Course writes here.
        $courseStudentIncrements = [];

        Enrollment::query()
            ->whereNull('order_id')
            ->with(['student.user'])
            ->chunkById(100, function ($enrollments) use (&$courseStudentIncrements) {
                foreach ($enrollments as $enrollment) {
                    DB::transaction(function () use ($enrollment, &$courseStudentIncrements) {
                        $student = $enrollment->student;

                        if (! $student) {
                            return;
                        }

                        Order::withoutEvents(function () use ($enrollment, $student) {
                            $order = Order::query()->create([
                                'student_id'    => $student->id,
                                'total_amount'  => 0,
                                'status'        => 'completed',
                                'billing_name'  => $student->full_name ?? $student->user?->name ?? 'N/A',
                                'billing_email' => $student->user?->email ?? 'N/A',
                                'billing_phone' => $student->phone ?? 'N/A',
                                'created_at'    => $enrollment->created_at,
                                'updated_at'    => $enrollment->created_at,
                            ]);

                            $enrollment->update(['order_id' => $order->id]);
                        });

                        $courseStudentIncrements[$enrollment->course_id] =
                            ($courseStudentIncrements[$enrollment->course_id] ?? 0) + 1;
                    });
                }
            });

        // After all chunks: one bulk UPDATE for total_students (no N+1 per enrollment).
        $this->incrementCourseStudentCounts($courseStudentIncrements);
    }

    /**
     * @param  array<int, int>  $courseStudentIncrements  course_id => count
     */
    private function incrementCourseStudentCounts(array $courseStudentIncrements): void
    {
        if ($courseStudentIncrements === []) {
            return;
        }

        $cases = [];
        $ids = [];

        foreach ($courseStudentIncrements as $courseId => $count) {
            $cases[] = 'WHEN '.((int) $courseId).' THEN '.((int) $count);
            $ids[] = (int) $courseId;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        DB::update(
            'UPDATE courses SET total_students = total_students + CASE id '
            .implode(' ', $cases)
            .' END WHERE id IN ('.$placeholders.')',
            $ids
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Irreversible: cannot safely distinguish backfilled orders from organic free orders.
    }
};
