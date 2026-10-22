FROM alpine:3.24
RUN apk add --no-cache \
        nginx \
        php84 \
        php84-fpm \
        php84-pdo \
        php84-pdo_mysql \
        php84-pdo_sqlite \
        php84-opcache \
        php84-session \
        php84-mbstring \
        php84-curl \
		php84-openssl \
		php84-intl \
        tzdata \
        ca-certificates \
        wget \
    && php-fpm84 -v \
    && mkdir -p \
        /run/nginx \
        /var/log/nginx \
        /var/log/syncmarks \
        /var/lib/syncmarks \
        /var/www/html/backups \
    && chown -R nginx:www-data \
        /var/log/syncmarks \
        /var/lib/syncmarks \
        /var/www/html/backups

COPY . /var/www/html/
COPY nginx.conf /etc/nginx/nginx.conf
COPY www.conf /etc/php84/php-fpm.d/www.conf
COPY zz-security.ini /etc/php84/conf.d/zz-security.ini

EXPOSE 80

HEALTHCHECK CMD wget -q -O /dev/null http://127.0.0.1/health.txt || exit 1

CMD ["sh", "-c", "php-fpm84 -D && exec nginx -g 'daemon off;'"]

LABEL org.opencontainers.image.source="https://github.com/Offerel/SyncMarks-Webapp"
LABEL org.opencontainers.image.description="SyncMarks bookmark synchronization server"