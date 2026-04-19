FROM php:8.2-apache

RUN docker-php-ext-install pdo pdo_mysql

RUN sed -i 's/^LoadModule mpm_prefork_module/# LoadModule mpm_prefork_module/' /etc/apache2/apache2.conf

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public

RUN sed -ri -e "s!/var/www/html!${APACHE_DOCUMENT_ROOT}!g" \
    /etc/apache2/sites-available/*.conf \
    /etc/apache2/apache2.conf \
    /etc/apache2/conf-available/*.conf \
    && printf '%s\n' \
        'Alias /api /var/www/html/api' \
        '<Directory /var/www/html/api>' \
        '    AllowOverride All' \
        '    Require all granted' \
        '</Directory>' \
    > /etc/apache2/conf-available/zenzone-api.conf \
    && a2enconf zenzone-api

WORKDIR /var/www/html
COPY . /var/www/html

EXPOSE 80
