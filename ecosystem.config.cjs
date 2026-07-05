/**
 * PM2 Ecosystem Configuration — T-Square LMS Backend
 *
 * الاستخدام:
 *   pm2 start ecosystem.config.cjs --env production
 *   pm2 save
 *   pm2 startup
 *
 * العمليات:
 *   tsquare-queue     — queue worker (يعالج Jobs مثل الحضور والإشعارات)
 *   tsquare-scheduler — task scheduler (يشغّل Artisan schedule كل دقيقة)
 *   tsquare-reverb    — Laravel Reverb WebSocket server (البث الفوري للحضور)
 */
module.exports = {
  apps: [
    // ─────────────────────────────────────────────
    // Queue Worker
    // ─────────────────────────────────────────────
    {
      name: "tsquare-queue",
      script: "artisan",
      interpreter: "php",
      args: "queue:work --sleep=3 --tries=3 --max-time=3600 --timeout=60",
      instances: 1,
      autorestart: true,
      watch: false,
      max_memory_restart: "256M",
      log_date_format: "YYYY-MM-DD HH:mm:ss",
      error_file: "storage/logs/pm2-queue-error.log",
      out_file: "storage/logs/pm2-queue-out.log",
      env: {
        APP_ENV: "local",
      },
      env_production: {
        APP_ENV: "production",
      },
    },

    // ─────────────────────────────────────────────
    // Task Scheduler
    // ─────────────────────────────────────────────
    {
      name: "tsquare-scheduler",
      script: "artisan",
      interpreter: "php",
      args: "schedule:work",
      instances: 1,
      autorestart: true,
      watch: false,
      max_memory_restart: "128M",
      log_date_format: "YYYY-MM-DD HH:mm:ss",
      error_file: "storage/logs/pm2-scheduler-error.log",
      out_file: "storage/logs/pm2-scheduler-out.log",
      env: {
        APP_ENV: "local",
      },
      env_production: {
        APP_ENV: "production",
      },
    },

    // ─────────────────────────────────────────────
    // Laravel Reverb — WebSocket Server
    // يُستخدم للبث الفوري (مثل StudentScanned event)
    // تأكد من ضبط REVERB_* في ملف .env
    // ─────────────────────────────────────────────
    {
      name: "tsquare-reverb",
      script: "artisan",
      interpreter: "php",
      args: "reverb:start --host=0.0.0.0 --port=8080",
      instances: 1,
      autorestart: true,
      watch: false,
      max_memory_restart: "256M",
      log_date_format: "YYYY-MM-DD HH:mm:ss",
      error_file: "storage/logs/pm2-reverb-error.log",
      out_file: "storage/logs/pm2-reverb-out.log",
      env: {
        APP_ENV: "local",
      },
      env_production: {
        APP_ENV: "production",
        REVERB_PORT: "8080",
      },
    },
  ],
};
