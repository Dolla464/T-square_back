# 📮 Postman Guide - Solutions API

## Read-Only API - جلب البيانات فقط

---

## 1️⃣ GET - جميع الحلول

**اختر:** GET
**اكتب هنا:**
```
http://localhost:8000/api/student/solutions
```

**Headers:**
```
Authorization: Bearer YOUR_TOKEN
Content-Type: application/json
```

---

## 2️⃣ GET - حل معين

**اختر:** GET
**اكتب هنا:**
```
http://localhost:8000/api/student/solutions/1
```

**Headers:**
```
Authorization: Bearer YOUR_TOKEN
Content-Type: application/json
```

---

## 3️⃣ GET - البحث عن حل

**اختر:** GET
**اكتب هنا:**
```
http://localhost:8000/api/student/solutions?search=laravel
```

**Headers:**
```
Authorization: Bearer YOUR_TOKEN
Content-Type: application/json
```

---

## 4️⃣ GET - فلترة حسب Tag

**اختر:** GET
**اكتب هنا:**
```
http://localhost:8000/api/student/solutions?tag_id=1
```

**Headers:**
```
Authorization: Bearer YOUR_TOKEN
Content-Type: application/json
```

---

## 🔑 طريقة الحصول على Token

### 1. Login أولاً (POST)

**اختر:** POST
**اكتب هنا:**
```
http://localhost:8000/api/login
```

**Body (اختر raw + JSON):**
```json
{
  "email": "user@example.com",
  "password": "password"
}
```

**Response ستحصل على Token:**
```json
{
  "token": "YOUR_TOKEN_HERE",
  "user": {...}
}
```

### 2. استخدم Token في جميع الطلبات الأخرى

في Header:
```
Authorization: Bearer YOUR_TOKEN_HERE
```

---

## ⚡ أمثلة سريعة

### مثال 1: بحث + Pagination
```
http://localhost:8000/api/student/solutions?search=php&per_page=10&page=1
```

### مثال 2: فلترة Tag + Pagination
```
http://localhost:8000/api/student/solutions?tag_id=2&per_page=15&page=1
```

### مثال 3: بحث + فلترة + Pagination
```
http://localhost:8000/api/student/solutions?search=api&tag_id=1&per_page=20&page=1
```

---

## ✅ Response Format

### Data Format لكل Solution:
```json
{
  "id": 1,
  "name": "عنوان الحل",
  "description": "وصف الحل",
  "tags": [
    {
      "tag_id": 1,
      "tag_name": "PHP"
    },
    {
      "tag_id": 2,
      "tag_name": "Laravel"
    }
  ]
}
```

### Complete Response Example (مثال كامل):

```json
{
  "status": "success",
  "message": "Solutions fetched successfully",
  "data": [
    {
      "id": 1,
      "name": "شرح Laravel API",
      "description": "شرح شامل لـ REST API في Laravel",
      "tags": [
        {
          "tag_id": 1,
          "tag_name": "Laravel"
        },
        {
          "tag_id": 2,
          "tag_name": "API"
        }
      ]
    },
    {
      "id": 2,
      "name": "شرح PHP OOP",
      "description": "شرح البرمجة كائنية التوجه في PHP",
      "tags": [
        {
          "tag_id": 3,
          "tag_name": "PHP"
        }
      ]
    }
  ],
  "pagination": {
    "total": 50,
    "count": 2,
    "per_page": 15,
    "current_page": 1,
    "total_pages": 4
  }
}
```---

## 🚀 نصيحة: استخدم Environment Variables

في Postman:
1. اذهب إلى **Environments**
2. أنشئ environment جديد
3. أضف variables:
   - `base_url` = `http://localhost:8000`
   - `token` = `YOUR_TOKEN_HERE`

ثم استخدم:
```
{{base_url}}/api/student/solutions
```

وفي Header:
```
Authorization: Bearer {{token}}
```
