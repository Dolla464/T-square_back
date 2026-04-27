# T-Square LMS - API Routes Documentation

## Users Management Routes

### Base URL
```
/api/users
```

### 1. Get All Users (Paginated)
- **Method:** `GET`
- **Route:** `/api/users`
- **Auth Required:** Yes (Optional)
- **Description:** جلب قائمة بجميع المستخدمين بـ pagination
- **Response:** 15 مستخدم لكل صفحة
- **Example:**
```bash
curl -X GET "http://localhost:8000/api/users" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

### 2. Create New User
- **Method:** `POST`
- **Route:** `/api/users`
- **Auth Required:** No
- **Description:** إنشاء مستخدم جديد
- **Request Body:**
```json
{
  "name": "أحمد علي",
  "email": "ahmed@example.com",
  "password": "password123",
  "role": "student" | "instructor" | "admin"
}
```
- **Validation Rules:**
  - `name`: مطلوب، string، 3-255 أحرف
  - `email`: مطلوب، بريد إلكتروني صحيح، فريد
  - `password`: مطلوب، 8 أحرف على الأقل
  - `role`: مطلوب، يجب أن يكون من القيم المعرّفة

### 3. Search Users
- **Method:** `GET`
- **Route:** `/api/users/search`
- **Auth Required:** No
- **Query Parameters:**
  - `q`: نص البحث (حد أدنى 2 حرف)
- **Description:** البحث عن مستخدمين بالاسم أو البريد الإلكتروني
- **Example:**
```bash
curl -X GET "http://localhost:8000/api/users/search?q=ahmed" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

### 4. Get Single User
- **Method:** `GET`
- **Route:** `/api/users/{user}`
- **Auth Required:** No
- **Parameters:**
  - `user`: معرف المستخدم
- **Description:** جلب بيانات مستخدم واحد
- **Example:**
```bash
curl -X GET "http://localhost:8000/api/users/1" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

### 5. Update User ⭐ **الأهم**
- **Method:** `PUT`
- **Route:** `/api/users/{user}`
- **Auth Required:** No
- **Parameters:**
  - `user`: معرف المستخدم
- **Description:** تحديث بيانات المستخدم (ما عدا الايميل)
- **Request Body (جميع الحقول اختيارية):**
```json
{
  "name": "الاسم الجديد",
  "password": "كلمة مرور جديدة",
  "role": "instructor"
}
```
- **Important Notes:**
  - ✅ يمكن تحديث: `name`, `password`, `role`
  - ❌ لا يمكن تحديث: `email`, `id`, `created_at`
  - إذا لم يتم إرسال أي بيانات، يرجع "لم يتم إرسال بيانات للتحديث"
  - بعد التحديث، يتم إعادة تحميل البيانات من Database

- **Example Request:**
```bash
curl -X PUT "http://localhost:8000/api/users/67" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "اسم جديد",
    "role": "admin"
  }'
```

- **Success Response:**
```json
{
  "status": "success",
  "message": "تم تحديث المستخدم بنجاح",
  "data": {
    "id": 67,
    "name": "اسم جديد",
    "email": "elgamily@gmail.ww",
    "role": "admin",
    "email_verified_at": null,
    "last_login_at": null,
    "created_at": "2026-04-27T02:10:08.000000Z",
    "updated_at": "2026-04-27T02:15:30.000000Z"
  }
}
```

### 6. Delete User (Soft Delete)
- **Method:** `DELETE`
- **Route:** `/api/users/{user}`
- **Auth Required:** No
- **Parameters:**
  - `user`: معرف المستخدم
- **Description:** حذف مستخدم (حذف ناعم - يمكن استرجاعه)
- **Example:**
```bash
curl -X DELETE "http://localhost:8000/api/users/67" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

### 7. Restore Soft-Deleted User
- **Method:** `POST`
- **Route:** `/api/users/{id}/restore`
- **Auth Required:** No
- **Parameters:**
  - `id`: معرف المستخدم المحذوف
- **Description:** استعادة مستخدم تم حذفه بشكل ناعم
- **Example:**
```bash
curl -X POST "http://localhost:8000/api/users/67/restore" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

### 8. Force Delete User (Permanent)
- **Method:** `DELETE`
- **Route:** `/api/users/{id}/force-delete`
- **Auth Required:** No
- **Parameters:**
  - `id`: معرف المستخدم
- **Description:** حذف دائم للمستخدم (لا يمكن استرجاعه)
- **Example:**
```bash
curl -X DELETE "http://localhost:8000/api/users/67/force-delete" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

---

## Common Issues & Solutions

### ⚠️ المشكلة: "لم يتم تغيير أي بيانات"
**السبب:** البيانات المرسلة نفس البيانات الموجودة في Database
**الحل:** 
1. تأكد من إرسال قيم مختلفة عن الموجودة
2. لا تترك جميع الحقول فارغة

### ⚠️ المشكلة: "البريد الإلكتروني موجود بالفعل"
**السبب:** محاولة إنشاء مستخدم ببريد إلكتروني مستخدم
**الحل:** استخدم بريد إلكتروني جديد لم يتم استخدامه من قبل

### ⚠️ المشكلة: "الدور غير صحيح"
**السبب:** إرسال قيمة دور غير معرّفة
**الحل:** استخدم فقط: `student`, `instructor`, `admin`

---

## Response Structure

### Success Response
```json
{
  "status": "success",
  "message": "رسالة النجاح",
  "data": { /* البيانات */ }
}
```

### Error Response
```json
{
  "status": "error",
  "message": "رسالة الخطأ",
  "errors": { /* تفاصيل الخطأ */ }
}
```

---

## Route Order (Important! ⚠️)
```
GET    /api/users                    # Get all users
POST   /api/users                    # Create user
GET    /api/users/search             # Search users ← BEFORE dynamic routes
POST   /api/users/{id}/restore       # Restore user ← BEFORE {user} parameter
DELETE /api/users/{id}/force-delete  # Force delete ← BEFORE {user} parameter
GET    /api/users/{user}             # Get single user
PUT    /api/users/{user}             # Update user
DELETE /api/users/{user}             # Delete user
```

**Note:** المسارات الثابتة يجب أن تكون قبل المسارات ذات المعاملات الديناميكية!
