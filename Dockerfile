FROM alpine:3.22
RUN apk add --no-cache nginx php84 php84-fpm php84-pdo php84-pdo_mysql php84-pdo_sqlite php84-json php84-opcache php84-session php84-mbstring php84-curl php84-dom php84-xml php84-phar php84-fileinfo php84-intl tzdata ca-certificates wget
RUN mkdir -p /run/nginx /var/log/nginx /var/log/syncmarks /var/lib/syncmarks /var/www/html
RUN chown nobody:nobody /var/log/syncmarks /var/www/html /var/lib/syncmarks

COPY . /var/www/html/
COPY nginx.conf /etc/nginx/nginx.conf
COPY www.conf /etc/php84/php-fpm.d/www.conf

EXPOSE 80

CMD php-fpm84 -D && nginx -g "daemon off;"
HEALTHCHECK CMD wget -q -O /dev/null http://127.0.0.1/health.txt || exit 1

LABEL org.opencontainers.image.source="https://github.com/Offerel/SyncMarks-Webapp"
LABEL org.opencontainers.image.description="SyncMarks bookmark synchronization server"