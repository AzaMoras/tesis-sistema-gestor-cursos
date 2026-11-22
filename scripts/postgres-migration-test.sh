#!/bin/sh
# Crea una BD temporal con nombre único; nunca borra ni migra la BD de aplicación.
set -eu
test_db="aula_test_$(date +%s)_$$"
compose_file="$(dirname "$0")/../compose.yaml"
docker compose -f "$compose_file" exec -T db createdb -U aula_admin -O aula_app "$test_db"
printf 'Base aislada creada: %s\n' "$test_db"
docker compose -f "$compose_file" exec -T -e DB_DATABASE="$test_db" -e DB_URL= -e APP_ENV=testing app php scripts/check-postgres.php --migrate-isolated "$test_db"
printf 'Base conservada para inspección: %s. No se ha eliminado ningún dato.\n' "$test_db"
