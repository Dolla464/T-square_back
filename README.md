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

## 📋 توثيق الـ API عبر Postman

### تحديث بيانات البروفيل (Update Profile)

يمكنك تحديث بيانات المستخدم والبروفيل الخاص به (مثل الطالب أو المدرس) عبر هذا الـ Endpoint.

- **الرابط (URL):** `{{base_url}}/api/profile`
- **الطريقة (Method):** `PATCH`
- **العناوين (Headers):**
    - `Accept`: `application/json`
    - `Authorization`: `Bearer {your_token}`
- **جسم الطلب (Body - JSON):**

```json
{
    "name": "الاسم الجديد",
    "full_name": "الاسم الكامل الجديد",
    "email": "newemail@example.com",
    "phone": "01000000000",
    "gender": "male",
    "avatar": "https://example.com/avatar.png",
    "password": "new_password_123",
    "password_confirmation": "new_password_123"
}
```

> **ملاحظة:** جميع الحقول اختيارية (Optional)، يمكنك إرسال الحقول التي تريد تحديثها فقط.

📄 License This project is private and belongs to the T-Square development team.