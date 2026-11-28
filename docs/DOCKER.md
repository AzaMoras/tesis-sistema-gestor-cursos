# Docker de Aula Verde

La fase 08A sustituye PostgreSQL 17 por la imagen oficial `mariadb:11.8.9-noble`.
La versión de parche está fijada y la serie 11.8 LTS tiene mantenimiento comunitario
hasta junio de 2028. La construcción y el arranque quedan pendientes en una máquina
con Docker. [Mantenimiento de MariaDB](https://mariadb.org/about/#maintenance-policy)

## Arquitectura

| Servicio | Función | Red y almacenamiento |
|---|---|---|
| `nginx` | Nginx sin privilegios (UID 101). Sirve `public` y envía `index.php` a PHP-FPM | Red `web`. Local publica `127.0.0.1:8080`. Producción publica HTTP y HTTPS |
| `app` | PHP-FPM 8.4, Laravel 13, Blade, `pdo_mysql`, `pdo_sqlite`, intl, zip, bcmath, mbstring y OPcache. Node 22 compila Vite en otra etapa | Redes `web` y `database`, puerto 9000 interno y volumen `storage` |
| `db` | MariaDB con usuario de sistema `mysql` y healthcheck oficial de conexión e InnoDB | Red interna `database`, puerto 3306 interno y volumen nuevo `mariadb` en `/var/lib/mysql` |

`app` espera a MariaDB saludable. Nginx espera a PHP-FPM y verifica `/up`.
Los tres contenedores eliminan todas las capacidades y usan `no-new-privileges`.
Producción excluye dependencias Composer de desarrollo. El código y los assets
se incluyen en las imágenes. Reconstruye las imágenes al cambiarlos.

`compose.yaml` y `compose.prod.yaml` son configuraciones independientes. Sus
nombres de proyecto separan redes, almacenamiento y bases de datos.
`compose.test.yaml` se combina solo con el Compose local bajo un proyecto nuevo.

En un volumen vacío, la imagen oficial usa `MARIADB_DATABASE`, `MARIADB_USER`,
`MARIADB_PASSWORD` y `MARIADB_ROOT_PASSWORD`. `root` queda limitado al propio
servidor. Laravel recibe únicamente la cuenta `aula_app`, con permisos de
lectura, escritura y migración sobre su esquema. Las variables de inicialización
surten efecto al crear la base, por lo que la rotación de credenciales en una
base existente requiere modificar también la cuenta en MariaDB.
[Variables de inicialización de MariaDB](https://mariadb.com/docs/server/server-management/automated-mariadb-deployment-and-administration/docker-and-mariadb/mariadb-server-docker-official-image-environment-variables)

## Inicio local

En Windows usa Docker Desktop con motor Linux. En Linux usa Docker Engine
con el plugin Compose. Se requiere PHP CLI, Docker Compose v2 con `--wait`
y puerto 8080 libre. Verifica `docker version` y `docker compose version`.

Desde la raíz del repositorio, en una instalación nueva:

```powershell
php scripts/docker-init.php
docker compose config --quiet
docker compose up -d --build --wait
docker compose ps
```

Si ya existen archivos privados de MariaDB, empieza en `docker compose config --quiet`.
Si vienes del Compose de PostgreSQL, sigue primero la
[guía de transferencia](TRANSFERENCIA_POSTGRESQL_MARIADB.md).

El inicializador crea `.docker/local/app-mariadb.env` y `.docker/local/db-mariadb.env`
con una APP_KEY persistente y contraseñas independientes. Respalda estos
archivos de forma privada. Quedan fuera de Git y del contexto de construcción.
En Windows restringe con permisos NTFS el acceso a `.docker`. Usa
`config --quiet` para validar sin imprimir variables resueltas.
El archivo `.env` del host permanece separado.

Para validar los archivos Compose con Docker Desktop cerrado, usa el CLI
de Compose y evita resolver los archivos privados de los servicios:

```powershell
docker compose --env-file .env.docker.example -f compose.yaml config --no-env-resolution --quiet
docker compose --env-file .env.docker.example -f compose.prod.yaml config --no-env-resolution --quiet
docker compose --env-file .env.docker.example -f compose.yaml -f compose.test.yaml config --no-env-resolution --quiet
```

La validación estática comprueba la estructura de Compose. Los comandos de
inicio comprueban los archivos privados y el arranque con el motor activo.

### Prueba de compatibilidad de MariaDB

Desde la raíz, antes de migrar una instalación nueva:

```powershell
powershell -NoProfile -File scripts/mariadb-migration-test.ps1
```

```sh
sh scripts/mariadb-migration-test.sh
```

Cada ejecución usa un proyecto Compose único con su propia red y volumen.
Levanta una instancia MariaDB nueva y ejecuta un contenedor PHP temporal.
El comprobador exige entorno `testing`, configuración sin caché, base
configurada y efectiva esperada, servidor MariaDB, cuenta y permisos
acotados, y esquema vacío. Comprueba la colación de la conexión y del esquema,
UTC, modo estricto y claves foráneas activas. Ejecuta las migraciones, `migrate:status`, el
seeder y verificaciones de unicidad y clave foránea. Al terminar detiene
la instancia y conserva su volumen.

### Migraciones y verificación HTTP

En una base de aplicación nueva, aplica las migraciones después de la
prueba aislada. En una base con datos, verifica el respaldo y coordina la
ventana de cambio antes de migrar.

```powershell
docker compose exec -T app php artisan migrate --force
docker compose exec -T app php artisan migrate:status
docker compose exec -T app php scripts/check-mariadb.php
curl.exe --fail http://localhost:8080/up
curl.exe --fail http://localhost:8080/
curl.exe --fail http://localhost:8080/build/manifest.json
```

En Linux usa `curl`. Verifica también las rutas con hash del manifiesto.
Las migraciones y los seeders son explícitos. El seeder existente crea
un usuario de muestra y se ejecuta en la prueba de compatibilidad.

### Suite de pruebas con SQLite en memoria

```powershell
docker compose exec -T -e APP_ENV=testing -e DB_CONNECTION=sqlite -e 'DB_DATABASE=:memory:' -e 'DB_URL=' app php vendor/bin/phpunit --display-warnings --do-not-cache-result
```

Las variables se fijan antes de arrancar PHP. `tests/TestCase.php` exige
`testing`, SQLite en memoria, URL vacía y configuración sin caché.

Sin Docker, con PHP 8.4 y `pdo_sqlite`, ejecuta en PowerShell. La clave
de prueba vive solo en el entorno de este proceso:

```powershell
composer install --no-interaction --prefer-dist --no-scripts
$env:APP_ENV = 'testing'
$env:DB_CONNECTION = 'sqlite'
$env:DB_DATABASE = ':memory:'
$env:DB_URL = ''
$keyBytes = [byte[]]::new(32)
$rng = [Security.Cryptography.RandomNumberGenerator]::Create()
$rng.GetBytes($keyBytes)
$rng.Dispose()
$env:APP_KEY = 'base64:' + [Convert]::ToBase64String($keyBytes)
php vendor/bin/phpunit --display-warnings --do-not-cache-result
```

## Producción

En una instalación nueva, ejecuta `php scripts/docker-init.php --production`
y configura `APP_URL` con HTTPS en `.docker/production/app-mariadb.env`.
Coloca `fullchain.pem` y `privkey.pem` en `certs/` con permisos de lectura
para el UID 101. Configura el dominio y los puertos 80 y 443.

```sh
docker compose -f compose.prod.yaml config --quiet
docker compose -f compose.prod.yaml up -d --build --wait
docker compose -f compose.prod.yaml exec -T app php artisan migrate --force
docker compose -f compose.prod.yaml exec -T app php scripts/check-mariadb.php
docker compose -f compose.prod.yaml ps
```

Comprueba la redirección HTTP, HTTPS y la aplicación desde el dominio configurado.
Si existe una instalación anterior, sigue la guía de transferencia antes del arranque.

## Respaldo y restauración

Guarda el SQL, un respaldo consistente de `storage` y los archivos privados
en un destino restringido. Evita cambios de esquema durante el volcado.
Para producción, añade `-f compose.prod.yaml` después de `docker compose`.

Primero, crea un archivo privado dentro del contenedor:

```sh
docker compose exec db sh
umask 077
dump=$(mktemp /tmp/aula-verde.XXXXXXXX)
export MYSQL_PWD="$MARIADB_PASSWORD"
mariadb-dump --protocol=tcp --host=127.0.0.1 --user="$MARIADB_USER" --single-transaction --quick --no-tablespaces --default-character-set=utf8mb4 "$MARIADB_DATABASE" >"$dump" && printf '%s\n' "$dump"
exit
```

Copia la ruta impresa a un archivo nuevo. Desde PowerShell, sustituye
`RUTA_TEMPORAL` por la ruta del contenedor:

```powershell
New-Item -ItemType Directory -Force backups | Out-Null
$backup = 'backups/aula-verde-' + (Get-Date -Format yyyyMMdd-HHmmss) + '.sql'
docker compose cp 'db:RUTA_TEMPORAL' $backup
if ($LASTEXITCODE -ne 0) { throw 'Falló la copia del respaldo.' }
Get-FileHash $backup -Algorithm SHA256
```

Para ensayar la restauración, crea una instancia independiente y copia
el volcado. El SQL generado omite `--databases` y se importa en la base
indicada por `MARIADB_DATABASE` en el contenedor aislado.

```powershell
$restoreProject = 'aula-verde-restore-' + [guid]::NewGuid().ToString('N')
$composeArgs = @('-p', $restoreProject, '-f', 'compose.yaml', '-f', 'compose.test.yaml')
docker compose @composeArgs up --wait --wait-timeout 180 db
if ($LASTEXITCODE -ne 0) { throw 'Falló el inicio de la instancia aislada.' }
docker compose @composeArgs cp $backup 'db:/tmp/aula-restore.sql'
if ($LASTEXITCODE -ne 0) { throw 'Falló la copia del volcado.' }
docker compose @composeArgs exec -T db sh -ec 'export MYSQL_PWD=$MARIADB_PASSWORD; exec mariadb --protocol=tcp --host=127.0.0.1 --user=$MARIADB_USER --default-character-set=utf8mb4 $MARIADB_DATABASE </tmp/aula-restore.sql'
if ($LASTEXITCODE -ne 0) { throw 'Falló la restauración aislada.' }
docker compose @composeArgs run --build --rm --no-deps -T app php artisan migrate:status
docker compose @composeArgs stop db
```

Compara conteos y registros relevantes contra el origen. Los volúmenes
del origen y de restauración se conservan.

## Diagnóstico y persistencia

```powershell
docker compose ps
docker compose logs --tail=100 db app nginx
docker compose exec -T db id
docker compose exec -T app php --ri pdo_mysql
docker compose exec -T app php scripts/check-mariadb.php
docker compose up -d --wait --force-recreate
docker compose exec -T app php scripts/check-mariadb.php
```

- Base no saludable. Revisa logs, dueño del volumen, espacio y
  `docker compose exec -T db healthcheck.sh --connect --innodb_initialized`.
- Acceso denegado. Comprueba que ambos archivos privados correspondan al
  volumen inicializado antes de cambiar credenciales.
- Error 502. Revisa el servicio `app` y la conexión Nginx a `app:9000`.
- Error 500. Comprueba APP_KEY, permisos de `storage` y migraciones.
- Certificados. Verifica el dominio, cadena y permisos para el UID 101.
- Assets ausentes. Reconstruye las imágenes de `app` y `nginx` juntas.
- Pausa y reanuda con `docker compose stop` y `docker compose start`.
  Conserva los volúmenes. Evita `down -v`, `db:wipe` y `migrate:fresh`.

## Compatibilidad

Se revisaron 36 migraciones de Schema Builder, modelos, el seeder y consultas.
El SQL directo de negocio usa `LOWER` y `LIKE ... ESCAPE` con parámetros.
Laravel traduce transacciones, bloqueos `FOR UPDATE` y restricciones al
motor de destino. En Docker, `utf8mb4_nopad_bin` mantiene la sensibilidad a
mayúsculas, acentos y espacios finales. La conexión usa UTC y modo estricto.
Revisa las diferencias de ordenación, rango de fechas y enteros y
almacenamiento JSON al importar datos. El DDL de MariaDB puede confirmar
implícitamente una transacción. Restauración, TLS y concurrencia bajo carga
requieren validación en el entorno de destino.
