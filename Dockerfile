# Use an official PHP image with Apache
FROM php:8.1-apache

# Copy your code into the web directory
COPY . /var/www/html/

# Expose port 80
EXPOSE 80
