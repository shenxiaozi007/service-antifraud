FROM nginx:1.27-alpine

WORKDIR /var/www/html

COPY docs/docker/backend/nginx/api.conf /etc/nginx/conf.d/default.conf
COPY www/service.antifraud.local.com/public /var/www/html/public
