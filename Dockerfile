FROM richarvey/php-apache-heroku:latest

# نقل ملفات المشروع للسيرفر
COPY . /var/www/html

# إعدادات الـ Environment
ENV COMPOSER_ALLOW_SUPERUSER=1
ENV APP_ENV=production

# تنفيذ أوامر التجهيز
RUN composer install --no-dev
RUN php artisan optimize