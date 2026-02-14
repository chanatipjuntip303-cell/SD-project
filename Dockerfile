FROM php:8.2-apache
# ลง extension mysqli เพื่อใช้เชื่อมต่อฐานข้อมูล
RUN docker-php-ext-install mysqli && docker-php-ext-enable mysqli
# เปิดใช้งานโหมด rewrite ของ apache (เผื่อไว้ทำ URL สวยๆ)
RUN a2enmod rewrite