<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\Student;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class OrderSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $students = Student::all();

        if ($students->isEmpty()) {
            $this->command->info('لازم تشغل StudentSeeder الأول يا عادل!');
            return;
        }

        // إنشاء 50 طلب شراء موزعين على الـ 6 شهور اللي فاتت
        Order::factory(50)->make()->each(function ($order) use ($students) {
            $student = $students->random();

            // ربط الطلب بطالب عشوائي وتعبئة بيانات الفاتورة من بياناته
            $order->student_id = $student->id;
            $order->billing_name = $student->full_name;
            $order->billing_email = $student->user->email; // بنجيب الإيميل من جدول اليوزرز
            $order->billing_phone = $student->phone ?? '01000000000';

            $order->save();
        });

        $this->command->info('تم إنشاء 50 طلب شراء بنجاح!');
    }
}
