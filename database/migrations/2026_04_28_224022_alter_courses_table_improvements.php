<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            
            // 1. تعديل علاقة المدرب لمنع الحذف الكارثي (Cascade -> Restrict)
            $table->dropForeign(['instructor_id']); // نحذف العلاقة القديمة الأول
            $table->foreign('instructor_id')
                  ->references('id')->on('instructors')
                  ->onDelete('restrict'); // نضيفها بالمنطق الجديد

            // 2. إزالة حقل المراجعات المكرر
            if (Schema::hasColumn('courses', 'reviews_count')) {
                $table->dropColumn('reviews_count');
            }

            // 3. تعديل منطق الأسعار
            // السعر قبل الخصم أصبح يقبل Null
            $table->decimal('price_before', 10, 2)->nullable()->change(); 
            // السعر النهائي أصبح إجباري وله قيمة افتراضية
            $table->decimal('price', 10, 2)->nullable(false)->default(0)->change(); 

            // 4. إزالة الـ Index المكرر (Laravel بيسمي الفهرس باسم الجدول_الحقل_index)
            $table->dropIndex(['status']); 

            // 5. تحويل الـ Enums إلى Strings لمرونة أكبر مستقبلاً
            $table->string('attendance_type')->change();
            $table->string('level')->change();
            $table->string('status')->default('draft')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            
            // 1. إرجاع علاقة المدرب لـ Cascade
            $table->dropForeign(['instructor_id']);
            $table->foreign('instructor_id')
                  ->references('id')->on('instructors')
                  ->onDelete('cascade');

            // 2. إرجاع الحقل الممسوح
            $table->integer('reviews_count')->default(0);

            // 3. إرجاع منطق الأسعار القديم
            $table->decimal('price_before', 10, 2)->nullable(false)->change();
            $table->decimal('price', 10, 2)->nullable()->change();

            // 4. إرجاع الـ Index
            $table->index('status');

            // 5. إرجاع الـ Strings إلى Enums 
            // (ملاحظة: بعض قواعد البيانات قد تواجه مشاكل في إرجاع الـ String لـ Enum لو كان فيه بيانات خارج النطاق)
            $table->enum('attendance_type', ['Online', 'Offline', 'Hybrid'])->change();
            $table->enum('level', ['beginner', 'intermediate', 'advanced'])->change();
            $table->enum('status', ['draft', 'published'])->default('draft')->change();
        });
    }
};