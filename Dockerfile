FROM php:8.2-apache

RUN docker-php-ext-install pdo pdo_mysql

RUN rm -f /etc/apache2/mods-enabled/mpm_event.load /etc/apache2/mods-enabled/mpm_worker.load

RUN ls -la /etc/apache2/mods-enabled/mpm*.load

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

RUN echo '#!/bin/bash' > /usr/local/bin/check-mpm.sh && \
    echo 'echo "=== MPM modules at startup ===" && apache2ctl -M | grep mpm && echo "=== End ===" && exec apache2-foreground' >> /usr/local/bin/check-mpm.sh && \
    chmod +x /usr/local/bin/check-mpm.sh

CMD ["/bin/bash", "-c", "echo '=== Checking mods-enabled ===' && ls -la /etc/apache2/mods-enabled/mpm*.load && echo '=== Trying apache2ctl -M ===' && apache2ctl -M 2>&1 | head -20 || true && echo '=== Starting apache2-foreground ===' && exec apache2-foreground"]

EXPOSE 80
