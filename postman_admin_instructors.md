# Postman Endpoints: Admin Instructors Management

Base URL: `http://localhost:8000/api/admin/instructors` (تأكد من تغيير البورت إذا كان مشروعك يستخدم بورت مختلف).

يجب تمرير هيدر المصادقة للآدمن مع جميع الطلبات (إذا كان هناك Middleware للمصادقة):
`Authorization: Bearer YOUR_ADMIN_TOKEN`
`Accept: application/json`

---

## 1. Get All Instructors (Paginated)
- **Method:** `GET`
- **URL:** `/api/admin/instructors`
- **Query Params (Optional):**
  - `per_page`: 10 (عدد المدربين في الصفحة الواحدة)

---

## 2. Get Single Instructor
- **Method:** `GET`
- **URL:** `/api/admin/instructors/{instructor_id}`
- **Example:** `/api/admin/instructors/1`

---

## 3. Update Instructor
- **Method:** `POST` (تم تعديل المسار ليكون POST مباشر لسهولة رفع الصور)
- **URL:** `/api/admin/instructors/{instructor_id}`
- **Example:** `/api/admin/instructors/1`
- **Body:** `form-data`

**Keys:**
- `full_name`: `أحمد محمد`
- `field`: `برمجة الويب`
- `bio`: `نبذة تعريفية عن المدرب هنا...`
- `gender`: `male` (أو `female`)
- `insta_url`: `https://instagram.com/ahmed`
- `linkedin_url`: `https://linkedin.com/in/ahmed`
- `facebook_url`: `https://facebook.com/ahmed`
- `status`: `active` (أو `inactive`)
- `avatar`: `[File]` (اختياري - قم برفع صورة من هنا إذا أردت تغييرها)

*(ملاحظة: جميع الحقول أعلاه أصبحت اختيارية، أي أنك تستطيع إرسال الحقل الذي تريد تحديثه فقط وسوف يتم تجاهل الباقي والاحتفاظ بقيمته القديمة. ولكن تذكر أننا منعنا حقل `phone`، فلا تقم بإرساله).*

---

## 4. Delete Instructor
- **Method:** `DELETE`
- **URL:** `/api/admin/instructors/{instructor_id}`
- **Example:** `/api/admin/instructors/1`
