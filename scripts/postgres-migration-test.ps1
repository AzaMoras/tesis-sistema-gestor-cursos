$ErrorActionPreference = 'Stop'
$testDb = 'aula_test_' + [guid]::NewGuid().ToString('N')
$composeFile = Join-Path (Split-Path -Parent $PSScriptRoot) 'compose.yaml'
docker compose -f $composeFile exec -T db createdb -U aula_admin -O aula_app $testDb
if ($LASTEXITCODE -ne 0) { throw 'No se pudo crear la base aislada.' }
Write-Output "Base aislada creada: $testDb"
docker compose -f $composeFile exec -T -e "DB_DATABASE=$testDb" -e DB_URL= -e APP_ENV=testing app php scripts/check-postgres.php --migrate-isolated $testDb
if ($LASTEXITCODE -ne 0) { throw 'Fallo la prueba de migraciones aisladas.' }
Write-Output "Base conservada para inspeccion: $testDb."
