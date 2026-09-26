# دليل أوامر Artisan — مشروع T-Square LMS

مرجع شامل لجميع أوامر `php artisan` المتاحة في هذا المشروع، مع شرح عربي واضح لفائدة كل أمر ومتى تستخدمه.

> **الإصدار:** Laravel 13.15  
> **آخر تحديث:** سبتمبر 2026  
> **المصدر:** `php artisan list` + أوامر مخصصة في `app/Console/Commands/` و `routes/console.php`

---

## كيف تستخدم هذا الدليل

```bash
# عرض كل الأوامر
php artisan list

# مساعدة أمر محدد (خيارات، وسائط، وصف)
php artisan help attendance:activate

# تشغيل أمر
php artisan migrate --force
```

| الرمز | المعنى |
|-------|--------|
| ⚙️ | مجدول تلقائياً عبر Scheduler |
| ⚠️ | أمر حساس — استخدمه بحذر |
| 🔧 | للتطوير أو الصيانة |
| 📦 | من حزمة خارجية (Package) |

---

## فهرس سريع — أوامر المشروع المخصصة

| الأمر | الفئة | مجدول؟ | الخطورة |
|-------|-------|--------|---------|
| `attendance:activate` | حضور | ⚙️ كل 15 د | آمن |
| `attendance:complete` | حضور | ⚙️ كل 15 د | آمن |
| `attendance:generate-weekly` | حضور | ⚙️ يومياً 00:00 | آمن |
| `attendance:repair-stale` | حضور | يدوي | آمن |
| `attendance:fix-group-sessions` | حضور | يدوي | متوسط |
| `learning-groups:complete-expired` | مجموعات | ⚙️ يومياً 01:00 | آمن |
| `exams:close-expired` | امتحانات | ⚙️ كل دقيقة | آمن |
| `exams:backfill-group-activations` | امتحانات | يدوي | متوسط |
| `chunks:cleanup` | رفع | ⚙️ يومياً 02:00 | آمن |
| `previews:cleanup-orphans` | رفع | ⚙️ يومياً 02:30 | آمن |
| `db:truncate-all` | قاعدة بيانات | يدوي | ⚠️ خطير |
| `demo:rotate-passwords` | حسابات تجريبية | يدوي | ⚠️ |
| `inspire` | تجريبي | — | آمن |

---

# القسم 1 — أوامر المشروع المخصصة (T-Square)

---

## 1.1 الحضور والغياب (`attendance:*`)

### `attendance:activate` ⚙️

**الملف:** `app/Console/Commands/ActivateAttendanceSessions.php`

**الفائدة:** تفعيل جلسات الحضور التي ما زالت `upcoming` واقترب موعدها.

**التفاصيل:**
- يبحث عن جلسات **اليوم** فقط.
- نافذة التفعيل: من **5 دقائق قبل الآن** إلى **30 دقيقة بعد الآن** (حسب وقت البداية الفعلي للجلسة).
- عند التفعيل:
  - تغيير الحالة إلى `active`.
  - إنشاء رمز QR فريد (`sess_...`).
  - إرسال إشعار `SessionActivated` للمدرّس والطلاب في المجموعة.

```bash
php artisan attendance:activate
```

**الجدولة:** كل 15 دقيقة — `storage/logs/attendance-activate.log`

---

### `attendance:complete` ⚙️

**الملف:** `app/Console/Commands/CompleteAttendanceSessions.php`

**الفائدة:** إنهاء الجلسات التي انتهى وقتها (مع فترة سماح 30 دقيقة بعد نهاية الجلسة الفعلية) وتسجيل الغائبين.

**التفاصيل:**
- يعمل على جلسات `active` أو `upcoming` التي **انتهى وقتها الفعلي + 30 دقيقة**.
- لا يقتصر على «اليوم فقط» — يصلح أيضاً الجلسات القديمة العالقة.
- ينشئ سجل `absent` لكل طالب مسجّل لم يُسجَّل حضوره (`present` / `late`).
- يغيّر حالة الجلسة إلى `completed`.

```bash
php artisan attendance:complete
```

**الجدولة:** كل 15 دقيقة — `storage/logs/attendance-complete.log`

---

### `attendance:repair-stale` 🔧

**الملف:** `app/Console/Commands/RepairStaleAttendanceSessions.php`

**الفائدة:** تشغيل **مرة واحدة** بعد النشر أو بعد إصلاح منطق الحضور، لإكمال الجلسات العالقة في `active` / `upcoming` من أيام سابقة.

**ملاحظة:** يستخدم **نفس منطق** `attendance:complete` — الفرق فقط في رسالة الإخراج والغرض (صيانة يدوية بعد deploy).

```bash
php artisan attendance:repair-stale
```

**متى تستخدمه:**
- بعد تحديث نظام الحضور.
- إذا ظهرت جلسات قديمة ما زالت `active` في لوحة الإدارة أو التقارير.

---

### `attendance:generate-weekly` ⚙️

**الملف:** `app/Console/Commands/GenerateWeeklySessions.php`

**الفائدة:** إنشاء جلسات حضور للأسبوع الحالي (7 أيام من اليوم) لكل المجموعات النشطة.

**التفاصيل:**
- يطابق أيام جدول كل مجموعة (`schedules`) مع أيام التقويم.
- ينشئ جلسة `upcoming` فقط إذا لم تكن موجودة (مجموعة + جدول + تاريخ).
- يحترم `start_date` و `end_date` للمجموعة.

```bash
php artisan attendance:generate-weekly
```

**الجدولة:** يومياً الساعة `00:00` — `storage/logs/attendance-generate-weekly.log`

---

### `attendance:fix-group-sessions` 🔧

**الملف:** `app/Console/Commands/FixGroupSessionsCommand.php`

**الفائدة:** تصحيح `end_date` لمجموعة تعلّم حسب مدة الدورة (`duration_weeks`)، وحذف جلسات `upcoming` خارج النطاق الزمني الصحيح.

| الوسيط / الخيار | الوصف |
|-----------------|-------|
| `{group}` | معرّف مجموعة واحدة |
| `--all` | معالجة كل المجموعات |
| `--dry-run` | معاينة بدون حفظ |

```bash
php artisan attendance:fix-group-sessions 12
php artisan attendance:fix-group-sessions --all --dry-run
php artisan attendance:fix-group-sessions --all
```

> يجب استخدام `{group}` **أو** `--all` — لا يُستخدمان معاً.

---

## 1.2 مجموعات التعلّم (`learning-groups:*`)

### `learning-groups:complete-expired` ⚙️

**الملف:** `app/Console/Commands/CompleteExpiredLearningGroups.php`

**الفائدة:** إغلاق المجموعات النشطة التي انتهى `end_date` الخاص بها (قبل اليوم) تلقائياً، وإكمال enrollments غير المكتملة، وإرسال إشعار `CourseReviewRequired` للطلاب الجدد (مع استثناء من لديهم review مسبقاً).

**قاعدة الأهلية:**

```text
status = active
AND end_date IS NOT NULL
AND end_date < today
```

- `end_date = today` → **لا** تُغلق في نفس اليوم.
- المجموعات `cancelled` أو `completed` أو `end_date = NULL` → تُتجاهل.

**لا يصدر شهادات تلقائياً** — يبقى مسار الشهادة عبر التقييم + `CertificateService` كما هو.

| الخيار | الوصف |
|--------|-------|
| `--dry-run` | عرض المجموعات المؤهلة بدون أي تعديل |
| `--date=YYYY-MM-DD` | تاريخ مرجعي للاختبار/التشغيل اليدوي (افتراضي: اليوم بتوقيت التطبيق) |

```bash
php artisan learning-groups:complete-expired
php artisan learning-groups:complete-expired --dry-run
php artisan learning-groups:complete-expired --date=2026-09-26
php artisan learning-groups:complete-expired --dry-run --date=2026-09-26
```

**الجدولة:** يومياً الساعة `01:00` — `storage/logs/learning-groups-complete-expired.log`

---

## 1.3 الامتحانات (`exams:*`)

### `exams:close-expired` ⚙️

**الملف:** `app/Console/Commands/CloseExpiredExamAttempts.php`

**الفائدة:** إغلاق محاولات الامتحان `ongoing` التي تجاوزت المدة المسموحة (انتهى الوقت) وإكمالها تلقائياً.

**التفاصيل:**
- يفحص المحاولات على دفعات (100 في كل مرة).
- يستدعي `ExamService::completeAttempt()` للمحاولات المنتهية.
- يضمن عدم بقاء امتحان «مفتوح» بعد انتهاء المؤقت.

```bash
php artisan exams:close-expired
```

**الجدولة:** **كل دقيقة** — `storage/logs/exams-close-expired.log`

---

### `exams:backfill-group-activations` 🔧

**الملف:** `app/Console/Commands/BackfillGroupExamActivations.php`

**الفائدة:** تفعيل كل الامتحانات النشطة عالمياً (`is_active = true`) لكل مجموعة في نفس الدورة، عبر إنشاء سجلات `GroupExamActivation` الناقصة.

**مفيد عند:** ترحيل بيانات قديمة، أو بعد إضافة ميزة تفعيل الامتحان على مستوى المجموعة.

| الخيار | الوصف |
|--------|-------|
| `--dry-run` | عرض ما سيُنشأ دون كتابة في DB |

```bash
php artisan exams:backfill-group-activations --dry-run
php artisan exams:backfill-group-activations
```

---

## 1.3 الرفع والتخزين

### `chunks:cleanup` ⚙️

**الملف:** `app/Console/Commands/CleanupChunksCommand.php`

**الفائدة:** حذف جلسات الرفع المقطّع (chunked upload) المنتهية أو غير المكتملة، حسب `expires_at` و `status` في `meta.json`.

```bash
php artisan chunks:cleanup
```

**الجدولة:** يومياً الساعة `02:00`

---

### `previews:cleanup-orphans` ⚙️

**الملف:** `app/Console/Commands/CleanupOrphanPreviewsCommand.php`

**الفائدة:** حذف ملفات فيديو المعاينة في `storage/app/public/courses/previews/` **بدون سجل** في `course_previews`، إذا كان عمر الملف **أكثر من 24 ساعة**.

```bash
php artisan previews:cleanup-orphans
```

**الجدولة:** يومياً الساعة `02:30`

---

## 1.4 قاعدة البيانات

### `db:truncate-all` ⚠️

**الملف:** `app/Console/Commands/TruncateAllTables.php`

**الفائدة:** **مسح كل بيانات** قاعدة البيانات (ما عدا `migrations`) ثم إعادة زرع البيانات الأساسية.

**⚠️ للتطوير فقط — لا تستخدمه على الإنتاج.**

**ما يُعاد بعد المسح:**
- الأدوار (`RoleSeeder`)
- حسابات النظام (`AdminUserSeeder`, `ReceptionistSeeder`)
- الإعدادات (`SettingSeeder`)

| الخيار | الوصف |
|--------|-------|
| `--force` | بدون رسالة تأكيد |

```bash
php artisan db:truncate-all
php artisan db:truncate-all --force
```

**حسابات افتراضية بعد التنفيذ:**

| الدور | البريد | كلمة المرور |
|-------|--------|-------------|
| Admin | admin@tsquare.com | Admin@12345 |
| Instructor | instructor@tsquare.com | Instructor@12345 |
| Student | student@tsquare.com | Student@12345 |
| Receptionist | receptionist@tsquare.com | Receptionist@12345 |

---

## 1.5 حسابات تجريبية

### `demo:rotate-passwords` ⚠️

**الملف:** `app/Console/Commands/RotateDemoAccountPasswords.php`

**الفائدة:** تحديث كلمات مرور الحسابات التجريبية الأربعة من متغيرات البيئة `SEED_*`.

**يتطلب في `.env`:**
- `SEED_ADMIN_PASSWORD`
- `SEED_INSTRUCTOR_PASSWORD`
- `SEED_STUDENT_PASSWORD`
- `SEED_RECEPTIONIST_PASSWORD`

| الخيار | الوصف |
|--------|-------|
| `--force` | بدون تأكيد |

```bash
php artisan demo:rotate-passwords
php artisan demo:rotate-passwords --force
```

---

## 1.6 أخرى

### `inspire`

**الملف:** `routes/console.php`

**الفائدة:** أمر تجريبي من Laravel — يعرض اقتباساً عشوائياً. لا علاقة له بوظائف LMS.

```bash
php artisan inspire
```

---

# القسم 2 — الجدولة التلقائية (Scheduler)

**الملف:** `routes/console.php`

في الإنتاج يجب إضافة Cron:

```bash
* * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
```

| الأمر | التوقيت | ملاحظات |
|-------|---------|---------|
| `attendance:generate-weekly` | يومياً `00:00` | `withoutOverlapping()` |
| `chunks:cleanup` | يومياً `02:00` | — |
| `previews:cleanup-orphans` | يومياً `02:30` | — |
| `attendance:activate` | كل 15 دقيقة | log: `attendance-activate.log` |
| `attendance:complete` | كل 15 دقيقة | log: `attendance-complete.log` |
| `exams:close-expired` | **كل دقيقة** | log: `exams-close-expired.log` |

### أوامر الجدولة

| الأمر | الفائدة |
|-------|---------|
| `schedule:list` | عرض كل المهام المجدولة وتوقيتها القادم |
| `schedule:run` | تشغيل المهام المستحقة **الآن** (يستخدمه Cron) |
| `schedule:work` | worker يعمل باستمرار ويشغّل الجدولة (بديل Cron في بعض البيئات) |
| `schedule:test` | تشغيل مهمة مجدولة واحدة للاختبار |
| `schedule:clear-cache` | حذف ملفات mutex للمهام المتداخلة |
| `schedule:pause` | إيقاف الجدولة مؤقتاً |
| `schedule:resume` | استئناف الجدولة |
| `schedule:interrupt` | مقاطعة تشغيل الجدولة الحالي |

---

# القسم 3 — أوامر Laravel الأساسية

---

## 3.1 التطبيق والسيرفر

| الأمر | الفائدة |
|-------|---------|
| `about` | معلومات التطبيق: إصدار Laravel، PHP، البيئة، Cache، Queue |
| `serve` | تشغيل سيرفر تطوير PHP (`http://127.0.0.1:8000`) |
| `down` | وضع **صيانة** — الموقع غير متاح للزوار |
| `up` | إنهاء وضع الصيانة |
| `env` | عرض البيئة الحالية (`local`, `production`, …) |
| `test` | تشغيل اختبارات Pest/PHPUnit |
| `tinker` | REPL تفاعلي — تنفيذ كود PHP داخل التطبيق |
| `pail` | متابعة السجلات (`storage/logs`) مباشرة في الطرفية |
| `reload` | إعادة تحميل خدمات Octane/RoadRunner إن وُجدت |
| `docs` | فتح توثيق Laravel |
| `list` | قائمة كل الأوامر |
| `help {command}` | مساعدة أمر محدد |
| `clear-compiled` | حذف ملف bootstrap الم compiled |
| `completion` | إنشاء script إكمال تلقائي للـ Shell |

---

## 3.2 قاعدة البيانات (`migrate`, `db:*`)

| الأمر | الفائدة | ⚠️ |
|-------|---------|-----|
| `migrate` | تنفيذ migrations الجديدة | — |
| `migrate:status` | حالة كل migration (تم / لم يُنفَّذ) | — |
| `migrate:rollback` | التراجع عن **آخر دفعة** migrations | ⚠️ |
| `migrate:reset` | التراجع عن **كل** migrations | ⚠️ |
| `migrate:refresh` | reset + migrate من جديد | ⚠️ |
| `migrate:fresh` | **حذف كل الجداول** ثم migrate | ⚠️ |
| `migrate:install` | إنشاء جدول `migrations` | — |
| `db:seed` | تشغيل Seeders (`--class=` لseeder محدد) | — |
| `db:wipe` | حذف كل الجداول والـ views | ⚠️ |
| `db:show` | معلومات قاعدة البيانات | — |
| `db:table {table}` | بنية جدول محدد | — |
| `db:monitor` | مراقبة عدد الاتصالات | — |
| `db` | فتح جلسة CLI لقاعدة البيانات | — |
| `schema:dump` | تصدير schema قاعدة البيانات لملف | — |

**أمثلة شائعة في المشروع:**

```bash
php artisan migrate --force          # على السيرفر (بدون تأكيد)
php artisan migrate:status
php artisan db:seed --class=RoleSeeder
```

---

## 3.3 الكاش والتحسين (`cache:*`, `optimize:*`)

| الأمر | الفائدة |
|-------|---------|
| `cache:clear` | مسح **كل** كاش التطبيق |
| `cache:forget {key}` | حذف مفتاح كاش واحد |
| `cache:prune-stale-tags` | تنظيف tags منتهية (Redis فقط) |
| `config:cache` | تجميع ملفات config في ملف واحد — **للإنتاج** |
| `config:clear` | حذف كاش الإعدادات |
| `config:show {key}` | عرض قيمة إعداد |
| `config:publish` | نشر ملفات config من الحزم |
| `route:cache` | تجميع المسارات — **للإنتاج** |
| `route:clear` | حذف كاش المسارات |
| `route:list` | عرض كل مسارات API/Web |
| `view:cache` | compile كل قوالب Blade |
| `view:clear` | مسح قوالب Blade الم compiled |
| `event:cache` | cache الأحداث والـ listeners |
| `event:clear` | مسح cache الأحداث |
| `event:list` | قائمة Events و Listeners |
| `optimize` | تجميع config + routes + events + views |
| `optimize:clear` | مسح **كل** ملفات التحسين |
| `package:discover` | إعادة بناء manifest الحزم |

**سير عمل النشر على السيرفر:**

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

**بعد تعديل `.env` أو config:**

```bash
php artisan config:clear
php artisan cache:clear
```

---

## 3.4 قائمة الانتظار (`queue:*`)

| الأمر | الفائدة |
|-------|---------|
| `queue:work` | worker يعالج Jobs باستمرار (إشعارات، رفع، …) |
| `queue:listen` | listener يعيد تحميل الكود بعد كل job (تطوير) |
| `queue:restart` | إعادة تشغيل workers بعد انتهاء job الحالي — **بعد deploy** |
| `queue:failed` | عرض Jobs الفاشلة |
| `queue:retry {id}` | إعادة محاولة job فاشل |
| `queue:retry-batch {id}` | إعادة محاولة batch فاشل |
| `queue:forget {id}` | حذف job فاشل من السجل |
| `queue:flush` | حذف **كل** Jobs الفاشلة |
| `queue:clear {connection}` | حذف كل jobs في queue |
| `queue:monitor` | مراقبة حجم queues |
| `queue:pause` | إيقاف queue |
| `queue:resume` | استئناف queue |
| `queue:prune-failed` | حذف failed jobs القديمة |
| `queue:prune-batches` | حذف batch records القديمة |

**في التطوير (من `composer.json`):**

```bash
php artisan queue:listen --tries=1
```

**بعد تحديث الكود على السيرفر:**

```bash
php artisan queue:restart
```

> الإشعارات (`StudentExamAttemptStatusNotification` وغيرها) تستخدم Queue — بدون `queue:work` لن تُرسل.

---

## 3.5 التخزين

| الأمر | الفائدة |
|-------|---------|
| `storage:link` | إنشاء symlink من `public/storage` إلى `storage/app/public` — **مطلوب للملفات والصور** |
| `storage:unlink` | حذف symlinks التخزين |

```bash
php artisan storage:link
```

---

## 3.6 المفاتيح والبيئة

| الأمر | الفائدة |
|-------|---------|
| `key:generate` | توليد `APP_KEY` في `.env` — **مرة واحدة عند الإعداد** |
| `env:encrypt` | تشفير ملف `.env` |
| `env:decrypt` | فك تشفير `.env` |

---

## 3.7 المصادقة

| الأمر | الفائدة |
|-------|---------|
| `auth:clear-resets` | حذف tokens إعادة تعيين كلمة المرور المنتهية |

---

## 3.8 البث (Broadcasting)

| الأمر | الفائدة |
|-------|---------|
| `channel:list` | قائمة قنوات البث الخاصة المسجّلة |

---

## 3.9 النماذج (Models)

| الأمر | الفائدة |
|-------|---------|
| `model:show {Model}` | معلومات Model: علاقات، observers، … |
| `model:prune` | حذف سجلات models قابلة للـ pruning حسب جدول زمني |

---

## 3.10 Vendor و Stubs

| الأمر | الفائدة |
|-------|---------|
| `vendor:publish` | نشر assets/config/views من الحزم (`--tag=`, `--provider=`) |
| `stub:publish` | نشر stubs للتخصيص |
| `lang:publish` | نشر ملفات الترجمة |

---

# القسم 4 — أوامر الحزم الخارجية (Packages)

---

## 4.1 Spatie Permission (`permission:*`) 📦

| الأمر | الفائدة |
|-------|---------|
| `permission:show` | جدول الأدوار والصلاحيات |
| `permission:create-role` | إنشاء دور |
| `permission:create-permission` | إنشاء صلاحية |
| `permission:assign-role` | إسناد دور لمستخدم |
| `permission:cache-reset` | إعادة تعيين كاش الصلاحيات — **بعد تعديل roles/permissions** |
| `permission:setup-teams` | إعداد ميزة Teams (migration) |

---

## 4.2 Spatie Media Library (`media-library:*`) 📦

| الأمر | الفائدة |
|-------|---------|
| `media-library:regenerate` | إعادة توليد conversions/صور مشتقة |
| `media-library:clean` | تنظيف conversions قديمة وملفات بدون model |
| `media-library:clear` | حذف كل عناصر collection |

---

## 4.3 Laravel Sanctum (`sanctum:*`) 📦

| الأمر | الفائدة |
|-------|---------|
| `sanctum:prune-expired` | حذف API tokens منتهية (--hours=) |

---

## 4.4 Laravel IDE Helper (`ide-helper:*`) 📦 🔧

| الأمر | الفائدة |
|-------|---------|
| `ide-helper:generate` | ملف helper للـ IDE (autocomplete) |
| `ide-helper:models` | PHPDoc للـ Models |
| `ide-helper:meta` | metadata لـ PhpStorm |
| `ide-helper:eloquent` | إضافة `@mixin` لـ Eloquent |

---

## 4.5 Pest (`pest:*`) 📦

| الأمر | الفائدة |
|-------|---------|
| `pest:test {name}` | إنشاء ملف اختبار Pest |
| `pest:dataset {name}` | إنشاء dataset لاختبارات |

**تشغيل اختبارات:**

```bash
php artisan test
php artisan test tests/Feature/Attendance/SessionLifecycleStatusTest.php
```

---

## 4.6 Scramble — توثيق API (`scramble:*`) 📦

| الأمر | الفائدة |
|-------|---------|
| `scramble:export` | تصدير OpenAPI JSON |
| `scramble:analyze` | تحليل مشاكل توليد التوثيق |

---

## 4.7 Laravel Breeze (`breeze:*`) 📦

| الأمر | الفائدة |
|-------|---------|
| `breeze:install` | تثبيت Breeze (Scaffolding مصادقة) |

---

## 4.8 Laravel Install (`install:*`)

| الأمر | الفائدة |
|-------|---------|
| `install:api` | إعداد API routes + Sanctum/Passport |
| `install:broadcasting` | إعداد Broadcasting |

---

# القسم 5 — أوامر توليد الكود (`make:*`)

أوامر لإنشاء ملفات جديدة — للمطورين فقط.

| الأمر | ينشئ |
|-------|------|
| `make:command` | Artisan Command |
| `make:controller` | Controller |
| `make:model` | Eloquent Model |
| `make:migration` | Migration |
| `make:seeder` | Seeder |
| `make:factory` | Factory |
| `make:request` | Form Request (validation) |
| `make:resource` | API Resource |
| `make:middleware` | Middleware |
| `make:policy` | Policy |
| `make:observer` | Observer |
| `make:event` | Event |
| `make:listener` | Listener |
| `make:job` | Queue Job |
| `make:notification` | Notification |
| `make:mail` | Mailable |
| `make:exception` | Exception |
| `make:rule` | Validation Rule |
| `make:cast` | Eloquent Cast |
| `make:enum` | Enum |
| `make:scope` | Query Scope |
| `make:trait` | Trait |
| `make:class` | Class عام |
| `make:interface` | Interface |
| `make:component` | Blade Component |
| `make:view` | Blade View |
| `make:channel` | Broadcast Channel |
| `make:provider` | Service Provider |
| `make:config` | Config file |
| `make:test` | Test class |
| `make:job-middleware` | Job Middleware |
| `make:cache-table` | Migration لجدول cache |
| `make:session-table` | Migration لجدول sessions |
| `make:queue-table` | Migration لجدول queue |
| `make:queue-failed-table` | Migration لجدول failed_jobs |
| `make:queue-batches-table` | Migration لجدول job_batches |
| `make:notifications-table` | Migration لجدول notifications |

**مثال:**

```bash
php artisan make:model ActivityLog -mfs
# Model + Migration + Factory + Seeder
```

---

# القسم 6 — سيناريوهات عملية

## إعداد مشروع جديد

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
php artisan serve
php artisan queue:work
```

## بعد كل Deploy على السيرفر

```bash
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
php artisan queue:restart
php artisan attendance:repair-stale   # مرة عند الحاجة
```

## مشكلة: الإشعارات لا تُرسل

```bash
php artisan queue:work          # أو queue listener في Supervisor
php artisan queue:failed        # فحص الأخطاء
php artisan queue:retry all     # إعادة المحاولات
```

## مشكلة: جلسات حضور عالقة active

```bash
php artisan attendance:repair-stale
php artisan schedule:list       # تأكد أن Cron يعمل
```

## مشكلة: امتحان لم يُغلق بعد انتهاء الوقت

```bash
php artisan exams:close-expired
php artisan schedule:list       # يجب أن يعمل كل دقيقة
```

## إعادة بيئة تطوير نظيفة

```bash
php artisan db:truncate-all --force
# أو
php artisan migrate:fresh --seed
```

---

# القسم 7 — هيكل ملفات الأوامر المخصصة

```
app/Console/Commands/
├── ActivateAttendanceSessions.php       → attendance:activate
├── CompleteAttendanceSessions.php       → attendance:complete
├── RepairStaleAttendanceSessions.php    → attendance:repair-stale
├── GenerateWeeklySessions.php           → attendance:generate-weekly
├── FixGroupSessionsCommand.php          → attendance:fix-group-sessions
├── CloseExpiredExamAttempts.php         → exams:close-expired
├── BackfillGroupExamActivations.php     → exams:backfill-group-activations
├── CleanupChunksCommand.php             → chunks:cleanup
├── CleanupOrphanPreviewsCommand.php     → previews:cleanup-orphans
├── TruncateAllTables.php                → db:truncate-all
└── RotateDemoAccountPasswords.php       → demo:rotate-passwords

routes/console.php                       → inspire + Schedule
```

---

*للتحديث: شغّل `php artisan list` وقارِن بالقائمة أعلاه.*
