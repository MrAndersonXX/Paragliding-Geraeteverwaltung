#!/bin/sh
set -eu

mkdir -p /var/www/html/storage/data/uploads /var/www/html/storage/data/images /var/www/html/storage/app/backups
chown -R www-data:www-data /var/www/html/storage
chmod -R a+rwX /var/www/html/storage

touch /var/www/html/storage/data/.write-test
rm -f /var/www/html/storage/data/.write-test

exec "$@"
