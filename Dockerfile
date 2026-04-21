# استخدام نسخة PHP الرسمية مع Apache
FROM php:8.2-apache

# تثبيت الإضافات اللي لارافل بيحتاجها
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    git \
    curl

# تفعيل موديل Apache Rewrite عشان الراوتس تشتغل
RUN a2enmod rewrite

# تثبيت إضافات PHP
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd

# تثبيت Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# ضبط مسار المشروع
WORKDIR /var/www/html
COPY . .

# ضبط صلاحيات الفولدرات (مهم جداً للارفل)
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# تشغيل Composer install
RUN composer install --no-dev --optimize-autoloader

# تغيير الـ Document Root ليكون فولدر public
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# فتح بورت 80
EXPOSE 80

CMD ["apache2-foreground"]