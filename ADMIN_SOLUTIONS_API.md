# Admin Solutions Management API Documentation

## نظرة عامة

هذا الـ API يوفر إمكانيات إدارة كاملة (CRUD) للحلول (Solutions) من خلال لوحة التحكم للمسؤولين. يتيح للمسؤولين إضافة وتعديل وحذف والاطلاع على جميع الحلول في النظام.

---

## معلومات الـ API

- **Base URL**: `http://localhost:8000/api`
- **Prefix**: `/admin`
- **Authentication**: Bearer Token (Sanctum)
- **Content-Type**: `application/json`

---

## Endpoints

### 1. الحصول على قائمة جميع الحلول
```
GET /api/admin/solutions
```

#### الوصف
جلب جميع الحلول مع دعم الـ pagination (15 حل في الصفحة الواحدة).

#### Headers
```
Authorization: Bearer {token}
Accept: application/json
```

#### Response (200 OK)
```json
{
  "success": true,
  "data": {
    "data": [
      {
        "id": 1,
        "name": "اسم الحل",
        "description": "وصف الحل",
        "tags": [
          {
            "tag_id": 1,
            "tag_name": "الوسم"
          }
        ]
      }
    ],
    "links": {
      "first": "http://localhost:8000/api/admin/solutions?page=1",
      "last": "http://localhost:8000/api/admin/solutions?page=1",
      "prev": null,
      "next": null
    },
    "meta": {
      "current_page": 1,
      "from": 1,
      "last_page": 1,
      "per_page": 15,
      "to": 1,
      "total": 1
    }
  },
  "message": "Solutions retrieved successfully"
}
```

---

### 2. إنشاء حل جديد
```
POST /api/admin/solutions
```

#### الوصف
إنشاء حل جديد مع إمكانية إضافة الوسوم.

#### Headers
```
Authorization: Bearer {token}
Content-Type: application/json
Accept: application/json
```

#### Request Body
```json
{
  "title": "اسم الحل",
  "description": "وصف تفصيلي للحل",
  "tag_ids": [1, 2, 3]
}
```

#### متطلبات التحقق
| الحقل | النوع | المتطلبات |
|------|-------|----------|
| title | string | مطلوب، أقصى طول 255 حرف |
| description | string | مطلوب |
| tag_ids | array | اختياري، جميع الـ IDs يجب أن تكون موجودة في جدول tags |

#### Response (201 Created)
```json
{
  "success": true,
  "data": {
    "id": 1,
    "name": "اسم الحل",
    "description": "وصف الحل",
    "tags": [
      {
        "tag_id": 1,
        "tag_name": "الوسم الأول"
      },
      {
        "tag_id": 2,
        "tag_name": "الوسم الثاني"
      }
    ]
  },
  "message": "Solution created successfully"
}
```

#### الأخطاء
- **401 Unauthorized**: التوكن غير صحيح أو مفقود
- **403 Forbidden**: المستخدم ليس مسؤول
- **422 Unprocessable Entity**: بيانات غير صحيحة

---

### 3. الحصول على حل معين
```
GET /api/admin/solutions/{id}
```

#### الوصف
جلب بيانات حل معين شامل جميع الوسوم.

#### Parameters
- **id** (integer, required): معرّف الحل

#### Headers
```
Authorization: Bearer {token}
Accept: application/json
```

#### Response (200 OK)
```json
{
  "success": true,
  "data": {
    "id": 1,
    "name": "اسم الحل",
    "description": "وصف الحل",
    "tags": [
      {
        "tag_id": 1,
        "tag_name": "الوسم"
      }
    ]
  },
  "message": "Solution retrieved successfully"
}
```

#### الأخطاء
- **404 Not Found**: الحل غير موجود
- **401 Unauthorized**: التوكن غير صحيح

---

### 4. تحديث حل موجود
```
PUT /api/admin/solutions/{id}
```

#### الوصف
تحديث بيانات حل موجود (يمكن تحديث بعض الحقول فقط).

#### Parameters
- **id** (integer, required): معرّف الحل

#### Headers
```
Authorization: Bearer {token}
Content-Type: application/json
Accept: application/json
```

#### Request Body (اختياري)
```json
{
  "title": "عنوان جديد",
  "description": "وصف جديد",
  "tag_ids": [1, 2]
}
```

#### متطلبات التحقق
| الحقل | النوع | المتطلبات |
|------|-------|----------|
| title | string | اختياري، أقصى طول 255 حرف |
| description | string | اختياري |
| tag_ids | array | اختياري، جميع الـ IDs يجب أن تكون موجودة |

#### Response (200 OK)
```json
{
  "success": true,
  "data": {
    "id": 1,
    "name": "العنوان الجديد",
    "description": "الوصف الجديد",
    "tags": [
      {
        "tag_id": 1,
        "tag_name": "الوسم"
      }
    ]
  },
  "message": "Solution updated successfully"
}
```

#### الأخطاء
- **404 Not Found**: الحل غير موجود
- **422 Unprocessable Entity**: بيانات غير صحيحة

---

### 5. حذف حل
```
DELETE /api/admin/solutions/{id}
```

#### الوصف
حذف حل من النظام بشكل نهائي.

#### Parameters
- **id** (integer, required): معرّف الحل

#### Headers
```
Authorization: Bearer {token}
Accept: application/json
```

#### Response (200 OK)
```json
{
  "success": true,
  "data": null,
  "message": "Solution deleted successfully"
}
```

#### الأخطاء
- **404 Not Found**: الحل غير موجود
- **401 Unauthorized**: التوكن غير صحيح

---

## Postman Collection

### تحميل الـ Collection
يمكنك استيراد هذا الـ URL في Postman:

```
انسخ البيانات التالية واستخدم Postman > Import > Raw text
```

### متغيرات البيئة (Environment)
قم بإعداد المتغيرات التالية في Postman:

| المتغير | القيمة | الملاحظات |
|---------|--------|----------|
| `{{base_url}}` | `http://localhost:8000/api` | الـ URL الأساسي |
| `{{token}}` | `your_admin_token_here` | توكن الـ Admin |

---

## أمثلة الاستخدام

### مثال 1: إنشاء حل بسيط

**Request:**
```bash
curl -X POST http://localhost:8000/api/admin/solutions \
  -H "Authorization: Bearer your_token" \
  -H "Content-Type: application/json" \
  -d '{
    "title": "حل مشكلة الـ Authentication",
    "description": "شرح تفصيلي عن كيفية استخدام JWT في Laravel",
    "tag_ids": [1, 3]
  }'
```

### مثال 2: تحديث حل

**Request:**
```bash
curl -X PUT http://localhost:8000/api/admin/solutions/1 \
  -H "Authorization: Bearer your_token" \
  -H "Content-Type: application/json" \
  -d '{
    "title": "حل محدث",
    "tag_ids": [1, 2, 3, 4]
  }'
```

### مثال 3: حذف حل

**Request:**
```bash
curl -X DELETE http://localhost:8000/api/admin/solutions/1 \
  -H "Authorization: Bearer your_token"
```

---

## معلومات المسؤول والصلاحيات

### المتطلبات
- المستخدم يجب أن يكون **مسؤول (admin)**
- يجب تسجيل الدخول وامتلاك **Bearer Token** صحيح
- جميع العمليات تتطلب مصادقة Sanctum

### الأدوار والصلاحيات
```php
// في الـ Request Class
public function authorize(): bool
{
    return $this->user()->hasRole('admin');
}
```

---

## معالجة الأخطاء

### الأخطاء الشائعة

| الكود | الرسالة | السبب |
|------|--------|-------|
| 401 | Unauthorized | التوكن مفقود أو غير صحيح |
| 403 | Forbidden | المستخدم ليس مسؤول |
| 404 | Not Found | الحل المطلوب غير موجود |
| 422 | Unprocessable Entity | بيانات غير صحيحة أو غير مكتملة |

### مثال على رسالة خطأ
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "title": ["The title field is required."],
    "description": ["The description field is required."],
    "tag_ids": ["The tag 999 does not exist."]
  }
}
```

---

## هيكل الملفات

```
app/
├── Http/
│   ├── Controllers/Api/Admin/
│   │   └── AdminSolutionController.php      # الـ Controller الرئيسي
│   ├── Requests/Admin/
│   │   ├── StoreSolutionRequest.php         # التحقق من البيانات (Create)
│   │   └── UpdateSolutionRequest.php        # التحقق من البيانات (Update)
│   └── Resources/
│       └── SolutionResource.php             # تنسيق الـ Response
├── Services/
│   └── AdminSolutionService.php             # Business Logic
├── Models/
│   └── Solution.php                         # نموذج الحل
└── Traits/
    └── ApiResponseTrait.php                 # Helper Methods
```

---

## التحسينات والملاحظات

✅ **المميزات المطبقة:**
- CRUD operations كامل
- Service Layer للـ Business Logic
- Form Requests للـ Validation
- Resource Classes للـ Response Formatting
- Database Transactions للسلامة
- دعم الوسوم (Tags)
- Pagination مدعوم
- Error Handling شامل

---

## الدعم والمساعدة

للمزيد من المعلومات عن الـ API أو الإبلاغ عن أي مشاكل، يرجى التواصل مع فريق التطوير.
