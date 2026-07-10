# أوامر Artisan الخاصة بمشروع T-Square LMS

هذا الملف يوثّق **جميع أوامر Artisan المخصصة** في المشروع (المعرّفة في `app/Console/Commands/` و `routes/console.php`)، مع شرح فائدة كل أمر وطريقة استخدامه.

> **ملاحظة:** أوامر Laravel الافتراضية (`migrate`، `db:seed`، `cache:clear`، …) و أوامر الحزم الخارجية (`permission:*`، `media-library:*`، …) **ليست** مدرجة هنا لأنها ليست خاصة بهذا المشروع.

---

## ملخص سريع

| الأمر | الفئة | مجدول تلقائياً؟ |
|-------|-------|-----------------|
| `attendance:activate` | الحضور | نعم — كل 15 دقيقة |
| `attendance:complete` | الحضور | نعم — كل 15 دقيقة |
| `attendance:generate-weekly` | الحضور | نعم — يومياً عند منتصف الليل |
| `attendance:fix-group-sessions` | الحضور | لا — يدوي |
| `chunks:cleanup` | الرفع والتخزين | نعم — يومياً الساعة 02:00 |
| `previews:cleanup-orphans` | الرفع والتخزين | نعم — يومياً الساعة 02:30 |
| `exams:backfill-group-activations` | الامتحانات | لا — يدوي |
| `db:truncate-all` | قاعدة البيانات | لا — يدوي (خطير) |
| `inspire` | تجريبي | لا |

---

## 1. أوامر الحضور والغياب (`attendance:*`)

### `attendance:activate`

**الملف:** `app/Console/Commands/ActivateAttendanceSessions.php`

**الفائدة:** تفعيل جلسات الحضور ذات الحالة `upcoming` التي اقترب موعدها. يبحث عن الجلسات المجدولة **اليوم** والتي يبدأ وقتها خلال نافذة من 5 دقائق ماضية إلى 30 دقيقة قادمة.

**ماذا يفعل عند التفعيل:**
- يغيّر حالة الجلسة إلى `active`
- يُنشئ رمز QR فريد للجلسة (`sess_...`)
- يُرسل إشعار `SessionActivated` للمدرّس والطلاب المسجّلين في المجموعة

**الاستخدام:**
```bash
php artisan attendance:activate
```

**الجدولة:** كل 15 دقيقة — مع `withoutOverlapping()` — والمخرجات تُسجَّل في `storage/logs/attendance-activate.log`

---

### `attendance:complete`

**الملف:** `app/Console/Commands/CompleteAttendanceSessions.php`

**الفائدة:** إنهاء جلسات الحضور النشطة (`active`) بعد مرور **30 دقيقة** من وقت انتهائها المجدول، وتسجيل الطلاب غير الحاضرين كـ `absent`.

**ماذا يفعل:**
- يحدّد الجلسات النشطة اليوم التي انتهى وقتها الفعلي قبل (الآن − 30 دقيقة)
- يُنشئ سجلات حضور `absent` للطلاب المسجّلين الذين لم يُسجَّل حضورهم (`present` أو `late`)
- يغيّر حالة الجلسة إلى `completed`
- يعرض معلومات تشخيصية مفصّلة أثناء التنفيذ

**الاستخدام:**
```bash
php artisan attendance:complete
```

**الجدولة:** كل 15 دقيقة — مع `withoutOverlapping()` — والمخرجات في `storage/logs/attendance-complete.log`

---

### `attendance:generate-weekly`

**الملف:** `app/Console/Commands/GenerateWeeklySessions.php`

**الفائدة:** إنشاء جلسات حضور للأسبوع القادم (7 أيام من اليوم) لجميع المجموعات النشطة (`status = active`) التي لم تنتهِ بعد.

**ماذا يفعل:**
- يمرّ على جداول كل مجموعة (`schedules`) ويطابق أيام الأسبوع
- يُنشئ جلسة `upcoming` فقط إذا لم تكن موجودة مسبقاً لنفس (المجموعة + الجدول + التاريخ)
- يحترم تواريخ بداية ونهاية المجموعة

**الاستخدام:**
```bash
php artisan attendance:generate-weekly
```

**الجدولة:** يومياً عند الساعة `00:00` — المخرجات في `storage/logs/attendance-generate-weekly.log`

---

### `attendance:fix-group-sessions`

**الملف:** `app/Console/Commands/FixGroupSessionsCommand.php`

**الفائدة:** تصحيح `end_date` لمجموعة تعلّم بناءً على مدة الدورة (`duration_weeks`)، وحذف جلسات الحضور `upcoming` التي تقع خارج النطاق الزمني الصحيح.

**الخيارات:**

| الخيار / الوسيط | الوصف |
|-----------------|-------|
| `{group}` | معرّف مجموعة تعلّم محددة |
| `--all` | معالجة جميع المجموعات |
| `--dry-run` | معاينة التغييرات دون تطبيقها |

**الاستخدام:**
```bash
# مجموعة واحدة
php artisan attendance:fix-group-sessions 12

# جميع المجموعات (معاينة)
php artisan attendance:fix-group-sessions --all --dry-run

# تطبيق فعلي على الكل
php artisan attendance:fix-group-sessions --all
```

**ملاحظة:** يجب تمرير `{group}` **أو** `--all` — لا يمكن استخدامهما معاً.

---

## 2. أوامر الرفع والتخزين

### `chunks:cleanup`

**الملف:** `app/Console/Commands/CleanupChunksCommand.php`

**الفائدة:** حذف جلسات الرفع المقطّع (chunked upload) المنتهية أو غير المكتملة، بناءً على `expires_at` و `status` في ملف `meta.json` لكل جلسة.

**الاستخدام:**
```bash
php artisan chunks:cleanup
```

**الجدولة:** يومياً الساعة `02:00`

---

### `previews:cleanup-orphans`

**الملف:** `app/Console/Commands/CleanupOrphanPreviewsCommand.php`

**الفائدة:** حذف ملفات فيديو المعاينة المرفوعة (`storage/app/public/courses/previews/`) التي **لا يوجد لها سجل** في جدول `course_previews`، بشرط أن يكون عمر الملف أكثر من **24 ساعة** (لتجنب حذف ملفات قيد الرفع).

**الاستخدام:**
```bash
php artisan previews:cleanup-orphans
```

**الجدولة:** يومياً الساعة `02:30`

---

## 3. أوامر الامتحانات

### `exams:backfill-group-activations`

**الملف:** `app/Console/Commands/BackfillGroupExamActivations.php`

**الفائدة:** تفعيل جميع الامتحانات النشطة عالمياً (`is_active = true`) لكل مجموعة تعلّم في نفس الدورة، عبر إنشاء سجلات `GroupExamActivation` الناقصة.

**مفيد عند:** ترحيل بيانات قديمة، أو بعد إضافة ميزة تفعيل الامتحانات على مستوى المجموعة.

**الخيارات:**

| الخيار | الوصف |
|--------|-------|
| `--dry-run` | عرض عدد السجلات التي ستُنشأ دون كتابتها |

**الاستخدام:**
```bash
# معاينة
php artisan exams:backfill-group-activations --dry-run

# تنفيذ
php artisan exams:backfill-group-activations
```

---

## 4. أوامر قاعدة البيانات

### `db:truncate-all`

**الملف:** `app/Console/Commands/TruncateAllTables.php`

**الفائدة:** **مسح جميع بيانات** قاعدة البيانات (باستثناء جدول `migrations`) ثم إعادة زرع البيانات الأساسية.

**⚠️ تحذير:** أمر خطير — يحذف كل البيانات. للاستخدام في بيئة التطوير فقط.

**البيانات التي تُعاد بعد المسح:**
- الأدوار (`RoleSeeder`)
- حسابات النظام (`AdminUserSeeder`)
- موظف الاستقبال (`ReceptionistSeeder`)
- الإعدادات (`SettingSeeder`)

**الحسابات الافتراضية بعد التنفيذ:**

| الدور | البريد | كلمة المرور |
|-------|--------|-------------|
| Admin | admin@tsquare.com | Admin@12345 |
| Instructor | instructor@tsquare.com | Instructor@12345 |
| Student | student@tsquare.com | Student@12345 |
| Receptionist | receptionist@tsquare.com | Receptionist@12345 |

**الخيارات:**

| الخيار | الوصف |
|--------|-------|
| `--force` | تخطي رسالة التأكيد التفاعلية |

**الاستخدام:**
```bash
php artisan db:truncate-all
php artisan db:truncate-all --force
```

---

## 5. أوامر أخرى

### `inspire`

**الملف:** `routes/console.php`

**الفائدة:** أمر تجريبي من Laravel يعرض اقتباساً ملهمًا. ليس له علاقة بوظائف المشروع.

**الاستخدام:**
```bash
php artisan inspire
```

---

## الجدولة التلقائية (Scheduler)

تُعرَّف المهام المجدولة في `routes/console.php`. لتشغيلها في الإنتاج يجب إضافة Cron:

```bash
* * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
```

| الأمر | التوقيت | ملاحظات |
|-------|---------|---------|
| `chunks:cleanup` | يومياً 02:00 | — |
| `previews:cleanup-orphans` | يومياً 02:30 | — |
| `attendance:activate` | كل 15 دقيقة | `withoutOverlapping()` + log |
| `attendance:complete` | كل 15 دقيقة | `withoutOverlapping()` + log |
| `attendance:generate-weekly` | يومياً 00:00 | `withoutOverlapping()` + log |

**عرض الجدولة:**
```bash
php artisan schedule:list
```

**تشغيل مهمة مجدولة يدوياً للاختبار:**
```bash
php artisan schedule:test
```

---

## هيكل الملفات

```
app/Console/Commands/
├── ActivateAttendanceSessions.php      → attendance:activate
├── CompleteAttendanceSessions.php      → attendance:complete
├── GenerateWeeklySessions.php          → attendance:generate-weekly
├── FixGroupSessionsCommand.php         → attendance:fix-group-sessions
├── CleanupChunksCommand.php            → chunks:cleanup
├── CleanupOrphanPreviewsCommand.php    → previews:cleanup-orphans
├── BackfillGroupExamActivations.php    → exams:backfill-group-activations
└── TruncateAllTables.php               → db:truncate-all

routes/console.php                      → inspire + تعريف الجدولة
```

---

## أوامر مفيدة للتحقق

```bash
# عرض جميع الأوامر المتاحة
php artisan list

# عرض تفاصيل أمر محدد
php artisan help attendance:activate

# عرض المهام المجدولة
php artisan schedule:list
```

---

*آخر تحديث: يوليو 2026*
