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

## Student API Endpoints (Postman)

Base URL (local):

`http://127.0.0.1:8000/api`

### 1) Get all students

- **Method:** `GET`
- **URL:** `/students`
- **Description:** يرجع قائمة الطلاب مع بيانات `user` و `learningGroup`.
- **Full URL Example:** `http://127.0.0.1:8000/api/students`

### 2) Create new student

- **Method:** `POST`
- **URL:** `/students`
- **Description:** ينشئ طالب جديد.
- **Full URL Example:** `http://127.0.0.1:8000/api/students`
- **Body (JSON) Example:**

```json
{
  "user_id": 5,
  "full_name": "Ahmed Ali",
  "phone": "01012345678",
  "enrollment_number": "TSQ-ABCD-2026",
  "group_id": 1,
  "avatar": "students/ahmed.png",
  "gender": "male",
  "status": "active"
}
```

### 3) Get single student

- **Method:** `GET`
- **URL:** `/students/{student_id}`
- **Description:** يرجع بيانات طالب واحد حسب `id`.
- **Full URL Example:** `http://127.0.0.1:8000/api/students/1`

### 4) Update student

- **Method:** `POST`
- **URL:** `/students/{student_id}`
- **Description:** يحدث بيانات الطالب.
- **Important:** حقل `phone` **غير مسموح** تعديله في التحديث.
- **Full URL Example:** `http://127.0.0.1:8000/api/students/1`
- **Body (JSON) Example:**

```json
{
  "full_name": "Ahmed Ali Updated",
  "group_id": 2,
  "status": "inactive"
}
```

### 5) Delete student

- **Method:** `DELETE`
- **URL:** `/students/{student_id}`
- **Description:** يحذف الطالب.
- **Full URL Example:** `http://127.0.0.1:8000/api/students/1`

📄 License This project is private and belongs to the T-Square development team.