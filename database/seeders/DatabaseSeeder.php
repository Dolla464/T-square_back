<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * DatabaseSeeder — T-Square LMS
 * ═══════════════════════════════════════════════════════════════════
 * ترتيب التشغيل مبني على تبعيات الـ Foreign Keys:
 *
 *  ① الأدوار والصلاحيات (Spatie)
 *  ② حسابات النظام الأساسية (Admin + Instructor + Student للاختبار)
 *  ③ بيانات ثابتة: الإعدادات، التصنيفات، التاجات
 *  ④ المحتوى: الكورسات، المجموعات، الحلول، الرسائل
 *  ⑤ المزيد من المستخدمين: مدربون عشوائيون، طلاب عشوائيون
 *  ⑥ الاشتراكات (تعتمد على: students, courses, orders, groups)
 *  ⑦ نظام الامتحانات (تعتمد على: exams, questions, choices)
 *  ⑧ محاولات الامتحانات والإجابات (تعتمد على: enrollments, exams)
 *  ⑨ الشهادات والتقييمات (تعتمد على: completed enrollments)
 * ═══════════════════════════════════════════════════════════════════
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('');
        $this->command->info('════════════════════════════════════════');
        $this->command->info('   T-Square LMS — Database Seeder');
        $this->command->info('════════════════════════════════════════');

        $this->call([

            // ════════════════════════════════════════
            // ① Roles — يجب أن يكون الأول دائماً
            //    Spatie يحتاج الـ roles قبل assignRole()
            // ════════════════════════════════════════
            RoleSeeder::class,

            // ════════════════════════════════════════
            // ② حسابات النظام الأساسية
            //    Admin + Student + Instructor للاختبار
            // ════════════════════════════════════════
            AdminUserSeeder::class,

            // ════════════════════════════════════════
            // ③ بيانات ثابتة لا تعتمد على غيرها
            // ════════════════════════════════════════
            SettingSeeder::class,
            CategorySeeder::class,
            TagSeeder::class,

            // ════════════════════════════════════════
            // ④ محتوى الموقع العام
            // ════════════════════════════════════════
            SolutionSeeder::class,
            SolutionTagSeeder::class,
            MessageSeeder::class,

            // ════════════════════════════════════════
            // ⑤ مستخدمون إضافيون (مدربون + طلاب)
            //    كل seeder ينشئ User + يُعيّن Role + ينشئ Profile
            // ════════════════════════════════════════
            InstructorSeeder::class,
            StudentSeeder::class,
            AdminSeeder::class,

            // ════════════════════════════════════════
            // ⑥ الكورسات والمجموعات
            //    تعتمد على: Categories, Instructors
            // ════════════════════════════════════════
            CourseSeeder::class,
            LearningGroupSeeder::class,

            // ════════════════════════════════════════
            // ⑦ الاشتراكات
            //    تعتمد على: Students, Courses, Groups
            //    وتُنشئ Orders داخلياً لكل اشتراك
            // ════════════════════════════════════════
            EnrollmentSeeder::class,

            // ════════════════════════════════════════
            // ⑧ نظام الامتحانات
            //    تسلسل إلزامي: Exam → Question → Choice
            // ════════════════════════════════════════
            ExamSeeder::class,
            QuestionSeeder::class,
            ChoiceSeeder::class,

            // ════════════════════════════════════════
            // ⑨ محاولات الامتحانات والإجابات
            //    تعتمد على: Enrollments, Exams, Questions, Choices
            // ════════════════════════════════════════
            ExamAttemptSeeder::class,
            AnswerSeeder::class,

            // ════════════════════════════════════════
            // ⑩ الشهادات والتقييمات
            //    تعتمد على: Enrollments المكتملة (is_completed = true)
            // ════════════════════════════════════════
            CertificateSeeder::class,
            CourseReviewSeeder::class,

            // ════════════════════════════════════════
            // ⑪ روابط Many-to-Many
            // ════════════════════════════════════════
            CourseTagSeeder::class,

        ]);

        $this->command->info('');
        $this->command->info('════════════════════════════════════════');
        $this->command->info('   ✓ اكتمل الـ Seeding بنجاح!');
        $this->command->info('════════════════════════════════════════');
        $this->command->info('');
        $this->command->info('بيانات الدخول الأساسية:');
        $this->command->table(
            ['الدور', 'الإيميل', 'كلمة المرور'],
            [
                ['Admin',      'admin@tsquare.com',      'Admin@12345'],
                ['Instructor', 'instructor@tsquare.com', 'Instructor@12345'],
                ['Student',    'student@tsquare.com',    'Student@12345'],
            ]
        );
    }
}
