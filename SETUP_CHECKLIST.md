# T-Square LMS — قائمة الإعداد على السيرفر

> نفّذ الخطوات بالترتيب. كل خطوة منتهية ضعها ✅

---

## المرحلة 1 — رفع التعديلات على GitHub

```bash
# على جهازك المحلي
git add .
git commit -m "chore: add CI/CD workflow and update env config"
git push origin develop
```

ثم افتح GitHub → Repo → **Compare & pull request** → اختر base: `main` ← compare: `develop` → اضغط **Create pull request**.

انتظر حتى تنجح GitHub Actions (CI) ← اضغط **Merge pull request**.

---

## المرحلة 2 — إضافة GitHub Secrets (مرة واحدة فقط)

**المكان:** GitHub Repo → Settings → Secrets and variables → Actions → **New repository secret**

### Secret 1: `SERVER_HOST`
```
api.tsquarecenter.com
```

### Secret 2: `SERVER_USER`
```
root
```
> أو اسم الـ user الخاص بالسيرفر لو مش root

### Secret 3: `SERVER_SSH_KEY`

على السيرفر من aaPanel Terminal أو SSH، شغّل:
```bash
cat ~/.ssh/id_rsa
```
انسخ كل المحتوى من السطر `-----BEGIN OPENSSH PRIVATE KEY-----` لـ `-----END OPENSSH PRIVATE KEY-----` وضعه قيمة الـ Secret.

> **لو الملف مش موجود:** شغّل `ssh-keygen -t rsa -b 4096` ← اضغط Enter على كل الأسئلة ← بعدين شغّل الأمر أعلاه.

بعد ما تعمل key جديد، أضف الـ public key لـ `authorized_keys`:
```bash
cat ~/.ssh/id_rsa.pub >> ~/.ssh/authorized_keys
chmod 600 ~/.ssh/authorized_keys
```

---

## المرحلة 3 — الإعداد الأولي على السيرفر

ادخل على السيرفر من aaPanel → **Terminal** أو أي SSH client.

### 3.1 — كلون المشروع (لو مش موجود)
```bash
cd /www/wwwroot
git clone git@github.com:YOUR_USERNAME/YOUR_REPO.git api.tsquarecenter.com
cd api.tsquarecenter.com
```

### 3.2 — إنشاء ملف `.env`
```bash
cp .env.example .env
```

افتح الملف وعدّل القيم دي بالبيانات الحقيقية:
```bash
nano .env
```

| المتغير | اللي محتاج تحدده |
|---|---|
| `APP_KEY` | اتركه فاضي، هيتولد تلقائي في الخطوة الجاية |
| `DB_DATABASE` | اسم قاعدة البيانات على السيرفر |
| `DB_USERNAME` | يوزر قاعدة البيانات |
| `DB_PASSWORD` | باسوورد قاعدة البيانات |
| `REVERB_APP_ID` | أي رقم عشوائي (مثلاً: `123456`) |
| `REVERB_APP_KEY` | أي نص عشوائي (مثلاً: `tsquare-reverb-key`) |
| `REVERB_APP_SECRET` | أي نص عشوائي طويل |

> **ملاحظة:** `SESSION_DOMAIN`, `SANCTUM_STATEFUL_DOMAINS`, `APP_URL`, `FRONTEND_URL` كلهم محدّدين بالصح في `.env.example` بالفعل.

### 3.3 — تشغيل أوامر الإعداد
```bash
# توليد APP_KEY
php artisan key:generate

# تثبيت الـ packages (production فقط بدون dev tools)
composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader

# تثبيت Node packages وبناء الـ assets
npm ci --omit=dev
npm run build

# تشغيل الـ migrations
php artisan migrate --force

# ربط storage
php artisan storage:link

# تحديث الـ cache
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# ضبط الصلاحيات
chmod -R 775 storage bootstrap/cache
chown -R www:www storage bootstrap/cache
```

### 3.4 — تشغيل PM2
```bash
# أول تشغيل فقط
pm2 start ecosystem.config.cjs --env production
pm2 save
pm2 startup
```

> بعد `pm2 startup` هيطبع أمر، انسخه وشغّله عشان PM2 يشتغل تلقائياً بعد restart السيرفر.

---

## المرحلة 4 — إعداد Nginx لـ WebSocket (Reverb)

عشان الـ WebSocket يشتغل على `wss://` (مشفّر)، محتاج Nginx يعمل proxy للـ Reverb.

في aaPanel افتح **Website → اسم الموقع → Config** وأضف الـ block ده داخل `server {}`:

```nginx
# WebSocket proxy for Laravel Reverb
location /app/ {
    proxy_pass http://127.0.0.1:8080;
    proxy_http_version 1.1;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "Upgrade";
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
    proxy_read_timeout 60s;
}
```

بعدين restart Nginx من aaPanel.

---

## المرحلة 5 — التحقق النهائي

```bash
# التحقق إن PM2 شغّال
pm2 status

# التحقق إن التطبيق شغّال
php artisan about

# مراقبة الـ logs
tail -f storage/logs/laravel.log
```

افتح `https://api.tsquarecenter.com` في المتصفح وتأكد إن الـ API بيرد.

---

## بعد كده — الـ Deployment التلقائي

من هنا وبعدين، أي تعديل بتعمله:

```bash
# على جهازك المحلي
git add .
git commit -m "وصف التعديل"
git push origin develop
```

ثم على GitHub: افتح PR من `develop` → `main` ← انتظر CI ← **Merge** ← الـ deployment هيحصل تلقائياً على السيرفر.
