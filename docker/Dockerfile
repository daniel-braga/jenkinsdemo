FROM php:7.4.21-fpm-alpine

RUN apk --update add --no-cache libjpeg-turbo-dev libwebp-dev zlib-dev libxpm-dev libpng libpng-dev icu icu-dev autoconf \
    gcc musl-dev make \
    && docker-php-ext-install gd intl mysqli pdo_mysql \
    && pecl install mongodb \
    && docker-php-ext-enable mongodb \
    && docker-php-source delete \
    && apk del libjpeg-turbo-dev libwebp-dev zlib-dev libxpm-dev libpng-dev icu-dev autoconf gcc musl-dev make \
    && rm -rf /tmp/pear/download/*

RUN apk --update add --no-cache nginx supervisor curl bash \
    && rm /etc/nginx/http.d/default.conf \
    && mkdir -p /etc/supervisor.d \
    && mkdir -p /var/www/html

# Configure nginx
COPY config/nginx.conf /etc/nginx/nginx.conf
RUN chown nobody /var/www/html \
    && chown nobody /run \
    && chown nobody /var/lib/nginx \
    && chown nobody /var/log/nginx

# Configure PHP-FPM
COPY config/fpm-pool.conf /usr/local/etc/php-fpm.d/www.conf
COPY config/php.ini /usr/local/etc/php/conf.d/custom.ini

# Configure supervisord
COPY config/supervisord.conf /etc/supervisor.d/supervisord.conf

WORKDIR /var/www/html
EXPOSE 8080

USER nobody

# Copy the application files to nginx document root
COPY --chown=nobody blog/ /var/www/html/

# Let supervisord start nginx & php-fpm
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor.d/supervisord.conf"]

# Configure a healthcheck to validate that everything is up&running
HEALTHCHECK --timeout=10s CMD curl --silent --fail http://127.0.0.1:8080/fpm-ping