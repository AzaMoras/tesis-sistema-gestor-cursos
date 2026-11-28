$ErrorActionPreference = 'Stop'
$project = 'aula-verde-testing-' + [guid]::NewGuid().ToString('N')
$root = Split-Path -Parent $PSScriptRoot
$composeArgs = @('--project-name', $project, '-f', (Join-Path $root 'compose.yaml'),
    '-f', (Join-Path $root 'compose.test.yaml'))

Write-Output "Proyecto aislado: $project"
try {
    docker compose @composeArgs up --wait --wait-timeout 180 db
    if ($LASTEXITCODE -ne 0) { throw 'MariaDB aislada no alcanzó el estado saludable.' }

    docker compose @composeArgs run --build --rm --no-deps -T app php scripts/check-mariadb.php --migrate-isolated aula_test_compatibilidad
    if ($LASTEXITCODE -ne 0) { throw 'Falló la comprobación de compatibilidad con MariaDB.' }
} finally {
    docker compose @composeArgs stop db
    if ($LASTEXITCODE -ne 0) { throw "No se pudo detener MariaDB del proyecto $project." }
}
Write-Output "Volumen conservado: $($project)_mariadb"
