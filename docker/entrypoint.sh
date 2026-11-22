#!/bin/sh
set -eu
: "${APP_KEY:?Inicializa primero los archivos privados de Docker}"
mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs storage/app/private storage/app/public
# No migraciones ni seeders automáticos sobre datos persistentes.
exec "$@"
