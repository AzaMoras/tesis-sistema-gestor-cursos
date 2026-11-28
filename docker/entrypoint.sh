#!/bin/sh
set -eu
: "${APP_KEY:?Inicializa primero los archivos privados de Docker}"
if [ "${AULA_PRODUCTION:-0}" = 1 ]; then
    umask 0027
    php /var/www/html/scripts/check-production-env.php
fi
mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs storage/app/private storage/app/public
# No migraciones ni seeders automáticos sobre datos persistentes.
exec "$@"
