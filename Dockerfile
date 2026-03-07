FROM php:8.1-apache
# Install PostgreSQL support
RUN apt-get update && apt-get install -y libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql pgsql
COPY . /var/www/html/
EXPOSE 80
