# 🎓 T-Square LMS - Backend API

T-Square هو منصة لإدارة التعلم (Learning Management System) مبنية باستخدام **Laravel 13**. تم تصميم النظام ليكون مرناً وقابلاً للتوسع باستخدام الـ **Service Pattern**، مع دعم كامل للـ Multi-language والـ Roles.

## 🚀 التقنيات المستخدمة (Tech Stack)

* **Framework:** Laravel 13
* **Database:** MySQL
* **Architecture:** Service Pattern (Separation of Concerns)
* **Authentication:** Laravel Sanctum (Planned/In-progress)
* **Package Managers:** Composer
* **Tools:** Laravel Herd (Development Environment)

## 🛠️ المميزات الحالية (Features)

- [x] **Architecture:** إعداد الـ Service Pattern لفصل الـ Logic عن الـ Controllers.
- [x] **Database Design:** تصميم كامل لجدول الكورسات، الأقسام، والمدرسين.
- [x] **Multi-language:** دعم الترجمة (Localization) للأقسام والكورسات.
- [x] **Course Management:** نظام عرض الكورسات مع دعم الـ Pagination والـ Filters.
- [x] **Relationships:** ربط معقد بين (Courses, Categories, Instructors, Users).

## 📥 طريقة التثبيت (Installation)

1. **تحميل المشروع:**
   ```bash
git clone https://github.com/Dolla464/T-square_back.git


composer install

cp .env.example .env

php artisan key:generate

php artisan migrate --seed

php artisan storage:link

📄 License This project is private and belongs to the T-Square development team.