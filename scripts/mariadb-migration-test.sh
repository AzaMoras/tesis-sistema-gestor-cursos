#!/bin/sh
# Arranca únicamente una instancia nueva en un proyecto Compose exclusivo.
set -eu
root=$(CDPATH='' cd -- "$(dirname -- "$0")/.." && pwd)
project="aula-verde-testing-$(date +%s)-$$"

compose() {
    docker compose --project-name "$project" -f "$root/compose.yaml" -f "$root/compose.test.yaml" "$@"
}

cleanup() {
    status=$?
    trap - EXIT
    if ! compose stop db; then
        printf 'No se pudo detener MariaDB del proyecto %s.\n' "$project" >&2
        if [ "$status" -eq 0 ]; then
            status=1
        fi
    elif [ "$status" -eq 0 ]; then
        printf 'Volumen conservado: %s_mariadb\n' "$project"
    fi
    exit "$status"
}

trap cleanup EXIT
printf 'Proyecto aislado: %s\n' "$project"
compose up --wait --wait-timeout 180 db
compose run --build --rm --no-deps -T app php scripts/check-mariadb.php --migrate-isolated aula_test_compatibilidad
