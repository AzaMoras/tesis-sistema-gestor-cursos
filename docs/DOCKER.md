# Docker de Aula Verde

Estado al 17/11/2026. **Despliegue local comprobado** en Windows 11 con Docker
Desktop y WSL 2. **Despliegue en la nube sin realizar**. La configuración
de producción está disponible para su validación en un VPS.

## Arquitectura y aislamiento

- `nginx`: imagen nginx-unprivileged, UID/GID 101, puerto interno 8080.
  Sirve exclusivamente `public` y envía solo `index.php` a PHP-FPM.
- `app`: PHP 8.4 FPM, usuario www-data (33), Laravel 13, Composer,
  pdo_pgsql, pdo_sqlite (pruebas), intl, zip, bcmath, mbstring y OPcache.
  Vite/Tailwind se compilan con Node 22 en una etapa separada.
  Producción elimina dependencias Composer de desarrollo.
- `db`: PostgreSQL 17, proceso con usuario de sistema postgres.
  El administrador `aula_admin` se limita al contenedor de BD.
  Laravel recibe únicamente `aula_app`, sin superusuario, creación de BD,
  creación de roles ni replicación. Es dueño del esquema para migraciones.
  POSTGRES_USER inicializa un superusuario en la imagen oficial; por eso nunca
  se usa como usuario de Laravel. Referencia: [imagen oficial PostgreSQL](https://hub.docker.com/_/postgres).
- Red `database` interna, sin puertos publicados de PostgreSQL o FPM.
  Nginx y app comparten `web`; app puede salir a servicios externos.
- Volúmenes nombrados para PostgreSQL y storage. No se monta el código del host.
  Los cambios de código requieren reconstruir imágenes.
- Usuarios no privilegiados, capacidades eliminadas y no-new-privileges en los
  tres servicios; sin contenedores privilegiados. Root se usa temporalmente
  durante la construcción para instalar paquetes y establecer propietarios.
- Compose local y producción son archivos **independientes**, con nombres de
  proyecto y volúmenes separados. No combinarlos con múltiples `-f`.
- La configuración de MySQL y `.env` del host se conservan. No se leen en las
  imágenes ni se montan en los contenedores. Compose puede leer `.env` por
  convención, pero los archivos no interpolan sus variables de aplicación.
  Referencia de sintaxis: [servicios de Docker Compose](https://docs.docker.com/reference/compose-file/services/).

## Requisitos e inicialización local (PowerShell o Linux)

En Windows, Docker Desktop con motor Linux en WSL 2 y el comando
`docker compose`. En Linux, Docker Engine con el plugin Compose. Se requiere
acceso a registros para descargar imágenes y paquetes y el puerto 8080 libre.
PHP CLI permite inicializar los secretos de una copia nueva.

Desde la raíz del repositorio:

```powershell
php scripts/docker-init.php
docker compose config --quiet
docker compose up -d --build
docker compose ps
```

La inicialización **ya se ejecutó en esta copia local**. Una segunda ejecución
se rechaza intencionalmente para conservar APP_KEY y contraseñas.
En otra copia genera `.docker/local/app.env` y `db.env`, excluidos de Git
y del contexto de construcción. No copiar el `.env` de MySQL.
No publicar la salida completa de `docker compose config`: resuelve secretos.
`docker compose config --quiet` valida sin mostrarlos.

Si Linux no tiene PHP CLI, antes de levantar los servicios puede inicializarse
con una imagen temporal (proceso no root y sin acceso a la BD):

```sh
docker run --rm --user "$(id -u):$(id -g)" -v "$PWD:/work" -w /work php:8.4-cli php scripts/docker-init.php
```

Primero comprobar migraciones en una base **nueva y aislada**:

```powershell
powershell -File scripts/postgres-migration-test.ps1
```

Si la política de ejecución bloquea el archivo, ejecuta el script revisado en
un proceso nuevo. `Bypass` se aplica solo a ese proceso y respeta políticas de
grupo de mayor prioridad.

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\scripts\postgres-migration-test.ps1
```

En Linux:

```sh
sh scripts/postgres-migration-test.sh
```

Los scripts seleccionan el Compose local, crean una BD con nombre único y la
conservan para inspección. Antes de migrar, el comprobador valida el entorno
`testing`, el nombre de la conexión activa, el rol y la ausencia de caché de
configuración. Si cambias los scripts, reconstruye la imagen de `app` antes
de usarlos, pues el contenedor ejecuta el PHP incorporado en esa imagen.

Para aplicar migraciones en un volumen nuevo y revisar su estado:

```powershell
docker compose exec app php artisan migrate --force
docker compose exec app php artisan migrate:status
docker compose exec app php scripts/check-postgres.php
```

Si el volumen contiene datos anteriores, detenerse y solicitar aprobación
antes de migrar. No se ejecutan seeders ni migraciones automáticas.
No se importa ni migra la BD MySQL del host.

### PHPUnit con SQLite en memoria

Desde la raíz del repositorio, con `app` local iniciado:

```powershell
docker compose exec -T -e APP_ENV=testing -e DB_CONNECTION=sqlite -e 'DB_DATABASE=:memory:' -e 'DB_URL=' app php vendor/bin/phpunit --display-warnings --do-not-cache-result
```

El comando funciona también en un shell POSIX. El contenedor normalmente
inicia PHP con variables del `env_file` local que apuntan a PostgreSQL.
`force="true"` en `phpunit.xml` actualiza `getenv()` y `$_ENV`, pero no
`$_SERVER`. Laravel puede tomar primero los valores heredados de `$_SERVER`,
lo que explica el rechazo al usar `php artisan test --compact` dentro del
contenedor. Las opciones `-e` fijan el entorno del proceso desde antes de
arrancar PHP y vacían `DB_URL`, que podría redirigir la conexión. La protección
de `tests/TestCase.php` exige `testing`, SQLite en memoria, URL vacía y
configuración sin caché.

### Comprobaciones HTTP

Abrir `http://localhost:8080`. En PowerShell:

```powershell
curl.exe --fail http://localhost:8080/up
curl.exe --fail http://localhost:8080/ -o .docker/inicio.html
curl.exe --fail http://localhost:8080/build/manifest.json
curl.exe --fail http://localhost:8080/css/tesis-ui.css -o NUL
curl.exe --fail http://localhost:8080/js/tema-publico.js -o NUL
```

En Linux usar `curl` y `-o /dev/null`. Obtener del manifiesto las rutas
CSS/JS con hash y comprobar también HTTP 200 para ambas. Revisar en el navegador
estilos, imagen principal, tema claro/oscuro, consola y red sin errores.
La landing usa también CSS/JS propios; el layout carga Vite cuando hay manifiesto
o servidor de desarrollo, conservando el trabajo local sin assets compilados.

## Comprobación de usuarios y persistencia

```powershell
docker compose exec app id
docker compose exec nginx id
docker compose exec db id
docker compose top
docker compose exec app php scripts/check-postgres.php
docker compose exec db psql -U aula_admin -d aula_verde -c "SELECT system_identifier FROM pg_control_system();"
docker compose exec app php artisan migrate:status
docker compose restart
docker compose ps
docker compose exec db psql -U aula_admin -d aula_verde -c "SELECT system_identifier FROM pg_control_system();"
docker compose exec app php artisan migrate:status
docker compose exec app php scripts/check-postgres.php
```

Los UID deben ser distintos de 0; revisar también los procesos con `top`.
El identificador del clúster, migraciones y conteo informado por el comprobador
deben permanecer iguales. Para una verificación más fuerte, repetir tras
`docker compose down` y `docker compose up -d`, conservando volúmenes.
Esta prueba no borra ni crea datos de negocio.

```powershell
docker compose stop
docker compose start
docker compose restart
docker compose down
```

No usar `down -v`, `migrate:fresh`, `db:wipe` ni reset de migraciones.

## Variables y secretos

Plantillas versionadas: `.env.docker.example` y `.env.production.example`,
sin secretos. `scripts/docker-init.php` genera valores criptográficos sin
mostrarlos: APP_KEY base64 de 32 bytes y contraseñas independientes.
Se conservan en archivos privados y sobreviven reinicios/reconstrucciones.
Respaldar APP_KEY: cambiarla invalida cookies y puede impedir descifrar datos.

`app.env`: APP_ENV, APP_URL, APP_DEBUG=false, APP_KEY, conexión PostgreSQL,
sesiones/cache en archivos, cola síncrona y correo en log.
`db.env`: POSTGRES_USER, POSTGRES_DB, POSTGRES_PASSWORD y APP_DB_PASSWORD.
Mantener coincidentes DB_PASSWORD y APP_DB_PASSWORD durante la primera creación.
Editar variables de PostgreSQL tras inicializar el volumen **no cambia**
contraseñas de roles existentes. No regenerar archivos para intentar repararlo.
Los nombres de BD y roles están fijados por el script SQL de inicialización;
no cambiarlos aisladamente en un archivo.

En Linux se generan con permisos 0600 y directorios 0700; en Windows revisar ACL
del directorio privado. Nunca compartir esos archivos ni salidas de inspect/env.
No cachear configuración antes de ejecutar la suite: TestCase lo rechaza.
Si se usa `config:cache` en producción, regenerarlo tras cambios de entorno.

## Despliegue en VPS Linux

Requiere servidor accesible, Docker/Compose, dominio con DNS al servidor,
certificados válidos para ese dominio y reglas de firewall para 80/443 y SSH
restringido. No publicar 5432 ni 9000. No se inventaron IP/dominio.

1. Transferir la versión revisada del código, incluido package-lock.json, sin
   vendor, node_modules, .env de MySQL, .docker local ni archivos privados.
2. Generar secretos independientes con `php scripts/docker-init.php --production`
   (o el contenedor PHP temporal anterior añadiendo `--production`).
3. Editar `.docker/production/app.env`: APP_URL debe ser el origen HTTPS real.
   Configurar correo real cuando exista un flujo que lo necesite; el valor log
   actual no envía mensajes. Mantener APP_DEBUG=false y cookie segura.
4. Crear `certs/fullchain.pem` y `certs/privkey.pem` con certificados emitidos
   por una CA. Puede usarse ACME DNS-01 con el proveedor del dominio, sin abrir
   otro servidor HTTP; la emisión/renovación depende del proveedor y queda
   pendiente hasta contar con dominio y acceso. No subir certificados ni claves.
5. Dar lectura al grupo 101 del contenedor Nginx, sin abrir la clave al público:

```sh
sudo chgrp 101 certs certs/fullchain.pem certs/privkey.pem
sudo chmod 750 certs
sudo chmod 640 certs/fullchain.pem certs/privkey.pem
docker compose -f compose.prod.yaml config --quiet
docker compose -f compose.prod.yaml build
docker compose -f compose.prod.yaml up -d
docker compose -f compose.prod.yaml ps
docker compose -f compose.prod.yaml logs --tail=100
docker compose -f compose.prod.yaml exec app php artisan migrate:status
```

En una BD nueva, aplicar `docker compose -f compose.prod.yaml exec app php artisan migrate --force`.
En una existente, primero respaldo, revisión y aprobación del equipo.
Verificar `https://DOMINIO_REAL/up`, página, assets, redirección HTTP a HTTPS,
cadena TLS, usuarios, PostgreSQL y persistencia como en local.
El endpoint HTTP /up se reserva para healthcheck interno.
Tras renovar certificados ejecutar
`docker compose -f compose.prod.yaml exec nginx nginx -t` y
`docker compose -f compose.prod.yaml exec nginx nginx -s reload`.
Programar renovación con el cliente ACME elegido y comprobar su ejecución.

Actualización: respaldar primero; transferir código revisado; reconstruir
con `docker compose -f compose.prod.yaml build`; ejecutar en ventana de
mantenimiento las migraciones aprobadas usando la imagen nueva con
`docker compose -f compose.prod.yaml run --rm app php artisan migrate --force`;
recrear con `docker compose -f compose.prod.yaml up -d` y repetir comprobaciones.
Conservar imágenes anteriores y respaldo; volver a una imagen anterior no
revierte el esquema. No ejecutar rollback de BD sin revisión y aprobación.
No hay servicios separados de scheduler/worker: la configuración usa cola sync;
incorporarlos cuando los flujos del producto los requieran.

## Respaldo PostgreSQL (PowerShell y Linux)

Para producción anteponer `-f compose.prod.yaml` a todos los comandos.
Elegir un nombre nuevo por respaldo; estos comandos evitan redirección binaria
de PowerShell 5.1:

```powershell
New-Item -ItemType Directory -Force backups
docker compose exec -T db pg_dump -U aula_admin -d aula_verde -Fc -f /tmp/aula-verde.dump
docker compose exec -T db pg_restore --list /tmp/aula-verde.dump
docker compose cp db:/tmp/aula-verde.dump backups/aula-verde.dump
```

En Linux: `mkdir -p backups` y los mismos comandos Docker.
Comprobar códigos de salida antes de copiar. Usar nombres únicos para no
sobrescribir respaldos anteriores. Cifrar y conservar copias fuera del servidor,
con acceso restringido; respaldar además storage y archivos privados/APP_KEY.
El dump no incluye los roles globales: la inicialización recrea sus nombres.

Probar restauración únicamente en BD nueva, con nombre distinto:

```powershell
docker compose exec db createdb -U aula_admin -O aula_app aula_restore_verificacion
docker compose exec db pg_restore -U aula_admin --exit-on-error -d aula_restore_verificacion /tmp/aula-verde.dump
```

Revisar tablas/conteos en esa BD y comprobar la aplicación en un entorno aislado.
Nunca restaurar encima de una BD existente sin autorización. La restauración no
ha sido ejecutada aquí. El archivo /tmp se pierde al recrear el contenedor;
para un respaldo antiguo usar primero `docker compose cp` en sentido inverso.

## Diagnóstico

- Docker no reconocido: instalar/localizar Docker Desktop, iniciar motor Linux
  y reabrir terminal; comprobar `docker version` y `docker compose version`.
- Error de daemon/WSL2: iniciar Docker Desktop y resolver integración WSL2.
- env_file ausente: inicializar secretos una sola vez. No copiar .env de MySQL.
- 502: revisar salud/logs de app y que Nginx apunte a app:9000.
- 500: revisar logs de app; APP_KEY, permisos storage y migraciones.
- BD rechaza contraseña: comparar configuración privada y estado del volumen;
  no borrar volumen ni regenerar claves. Planificar cambio de contraseña.
- Error CRLF en .sh: los scripts tienen LF y regla explícita en .gitattributes.
- Assets ausentes: reconstruir ambos targets; no montar public del host encima.
- Puerto ocupado: liberar 8080 o cambiar solo el mapeo local y APP_URL.
- Falla TLS: comprobar dominio, cadena completa y lectura de clave por UID 101.
- Tests se niegan a iniciar con config cacheada: usar imagen runtime de local
  sin caché. Producción no contiene PHPUnit; probar en local/staging.

## Alcance funcional real y compatibilidad PostgreSQL

Se inspeccionaron estructura, dependencias, migraciones, modelos, controlador
InicioController, rutas, middleware de roles/permisos, servicios y pruebas.
Existen 36 migraciones con Schema Builder, relaciones Eloquent y servicios
transaccionales de inscripción, pago, solicitud, evaluación y vigencia.
No se detectó SQL MySQL específico en consultas ni migraciones que obligara
a editar código de negocio. PostgreSQL no impone unsigned como MySQL:
validaciones de límites siguen siendo responsabilidad de las reglas existentes.
La revisión estática y SQLite no prueban las migraciones PostgreSQL: para eso
están los scripts de BD aislada. No se modificaron migraciones existentes.

La landing real y prototipos Blade existen. Los cinco roles y middleware
existen; los tests cubren rechazos por rol, institución y propiedad.
Solo hay controlador de inicio y rutas de prototipos: login/logout,
autorregistro, verificación de correo, CRUD y flujos completos HTTP siguen
pendientes. La infraestructura no los implementa ni los presenta como resueltos.
Los servicios de negocio requieren integración/configuración pendiente descrita
en docs/AUDITORIA_FASE_00.md. Antes de publicar al público revisar las rutas
/_demo-tesis: son prototipos, no indicadores productivos.

## Resultados comprobados en Docker local

Validación reportada por el equipo en Windows 11 con Docker Desktop y WSL 2,
Docker 29.9.3 y Docker Compose v5.7.0.

| Verificación | Resultado observado |
|---|---|
| `docker compose config --quiet` | Correcto |
| `docker compose up -d --build` | Construcción finalizada y servicios `nginx`, `app` y `db` saludables |
| Landing | Visible en http://localhost:8080; Laravel 13.36.0, PHP 8.4.27 y PostgreSQL 17 |
| Migraciones | 36 en PostgreSQL aislado y 36 en la base principal de Docker |
| PHPUnit directo con variables explícitas | `OK (149 tests, 550 assertions)`, sin errores ni advertencias |
| Usuarios | `www-data` UID 33, `nginx` UID 101 y `postgres` UID 999 |
| `docker compose restart` | Los tres servicios recuperaron el estado saludable y las 36 migraciones siguieron registradas |

Incluye `package-lock.json` al compartir la infraestructura. La etapa de
assets usa ese archivo con `npm ci`.

En esta auditoría se repitieron `docker compose config --quiet`, la consulta
de salud de los tres servicios, la solicitud a la landing (HTTP 200) y PHPUnit
en el contenedor existente. La suite volvió a informar
`OK (149 tests, 550 assertions)`. La estructura
del Compose de producción se validó con
`docker compose -f compose.prod.yaml config --no-env-resolution --quiet`,
sin resolver los archivos privados de los servicios.

## Validaciones pendientes

- Ejecutar la versión revisada de los scripts de migración sobre otra base
  aislada. El resultado de 36 migraciones corresponde a la validación anterior.
- Comprobar restauración desde respaldo y persistencia tras recrear
  contenedores conservando los volúmenes.
- Validar la construcción e inicio del Compose de producción, HTTPS y la
  operación en un VPS. El despliegue en la nube no se ha realizado.
