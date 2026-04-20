FROM php:8.2-apache

RUN docker-php-ext-install pdo pdo_mysql

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public

RUN sed -ri -e "s!/var/www/html!${APACHE_DOCUMENT_ROOT}!g" \
    /etc/apache2/sites-available/*.conf \
    /etc/apache2/apache2.conf \
    /etc/apache2/conf-available/*.conf \
    && rm -f /etc/apache2/mods-enabled/mpm_event.load /etc/apache2/mods-enabled/mpm_event.conf \
    && rm -f /etc/apache2/mods-enabled/mpm_worker.load /etc/apache2/mods-enabled/mpm_worker.conf \
    && rm -f /etc/apache2/mods-enabled/mpm_prefork.load /etc/apache2/mods-enabled/mpm_prefork.conf \
    && ln -s ../mods-available/mpm_prefork.load /etc/apache2/mods-enabled/mpm_prefork.load \
    && ln -s ../mods-available/mpm_prefork.conf /etc/apache2/mods-enabled/mpm_prefork.conf \
    && printf '%s\n' \
        'Alias /api /var/www/html/api' \
        '<Directory /var/www/html/api>' \
        '    AllowOverride All' \
        '    Require all granted' \
        '</Directory>' \
    > /etc/apache2/conf-available/zenzone-api.conf \
    && a2enconf zenzone-api \
    && apache2ctl -M | grep -E 'mpm_.*_module'

WORKDIR /var/www/html
COPY . /var/www/html

COPY docker/apache-start.sh /usr/local/bin/apache-start
RUN chmod +x /usr/local/bin/apache-start

CMD ["/usr/local/bin/apache-start"]

EXPOSE 80
