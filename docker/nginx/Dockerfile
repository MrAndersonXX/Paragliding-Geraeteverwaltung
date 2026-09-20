FROM nginx:1.27-alpine

# Bakes the static/PHP-routing view of the application into the image so
# Synology Container Manager needs no bind mount for /var/www/html/public.
# Actual PHP execution still happens in the app container via fastcgi_pass.
COPY public /var/www/html/public
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
RUN find /var/www/html/public -type d -exec chmod 755 {} + \
    && find /var/www/html/public -type f -exec chmod 644 {} +
