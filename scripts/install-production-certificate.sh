#!/bin/sh
set -eu

if [ "$#" -ne 2 ] || { [ "$2" != initial ] && [ "$2" != reload ]; }; then
    echo "Uso: install-production-certificate.sh DIRECTORIO_DEL_PROYECTO initial|reload" >&2
    exit 1
fi

: "${RENEWED_LINEAGE:?Indica el directorio live del certificado emitido}"
cd "$1"
umask 0027
install -d -o 0 -g 101 -m 0750 certs

if [ "$2" = initial ] && [ -L certs/current ]; then
    echo "Ya existe un certificado activo. Usa reload." >&2
    exit 1
fi

previous=
if [ "$2" = reload ]; then
    previous=$(readlink certs/current)
fi

version=$(mktemp -d certs/version.XXXXXXXX)
install -o 0 -g 101 -m 0640 "$RENEWED_LINEAGE/fullchain.pem" "$version/fullchain.pem"
install -o 0 -g 101 -m 0640 "$RENEWED_LINEAGE/privkey.pem" "$version/privkey.pem"
chown 0:101 "$version"
chmod 0750 "$version"
ln -s "${version##*/}" "$version/current"
mv -Tf "$version/current" certs/current

if [ "$2" = reload ]; then
    if ! docker compose --env-file .docker/production/release.env -f compose.prod.yaml exec -T nginx nginx -t ||
        ! docker compose --env-file .docker/production/release.env -f compose.prod.yaml exec -T nginx nginx -s reload; then
        ln -s "$previous" "$version/previous"
        mv -Tf "$version/previous" certs/current
        echo "Falló la recarga. Se restauró el enlace al certificado anterior." >&2
        exit 1
    fi
fi
