/**
 * PM2 Ecosystem Configuration — T-Square LMS Backend
 *
 * الاستخدام:
 *   pm2 start ecosystem.config.js --env production
 *   pm2 save
 *   pm2 startup
 */
module.exports = {
  apps: [
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
  ],
};
