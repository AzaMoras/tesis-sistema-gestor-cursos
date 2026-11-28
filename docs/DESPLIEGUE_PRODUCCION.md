# Despliegue de Aula Verde en producción

## Alcance e integración

Esta guía parte de una versión revisada que reúne 07B, 08A y 08B. La rama de 08B conserva la base PostgreSQL que ya tenía el repositorio hasta integrar 08A. Antes de preparar datos o arrancar servicios, comprueba que el Compose integrado contiene MariaDB y que los archivos privados terminan en `-mariadb.env`.

| Archivo o conjunto | Reconciliación |
| --- | --- |
| `compose.prod.yaml` | Conservar de 08A la imagen, usuario, arranque, comprobación de salud y volumen `mariadb`; los dos `env_file` de MariaDB; las dependencias y la red `database` interna. Añadir de 08B las etiquetas de versión, `AULA_PRODUCTION`, `AULA_DOMAIN`, los montajes de Nginx, la sonda local y `logging: *production-logging` en los tres servicios. |
| `.env.production.example` | Conservar de 08A el driver, host, puerto, socket, juego de caracteres y colación de MariaDB. Conservar de 08B las opciones de cookie, verificación de correo y `MAIL_MAILER=array`. |
| `Dockerfile`, `scripts/docker-init.php`, `config/database.php`, `compose.yaml`, `compose.test.yaml`, scripts y pruebas de BD, `docs/DOCKER.md` | Integrar los cambios de 08A. Mantener el Dockerfile que copia el repositorio y ejecuta `docker/entrypoint.sh`, que ahora llama a la validación de 08B. Revisar las instrucciones anteriores de producción en `docs/DOCKER.md` y remitir a esta guía para HTTPS y operación. |
| `bootstrap/app.php`, `app/Exceptions/`, `resources/views/errors/` y pruebas de error | Integrar 07B. Conservar el manejador HTML/JSON y la ruta de salud `/up`. La configuración FastCGI de 08B entrega las respuestas de Laravel, incluidos sus errores. |

Si existe una instalación PostgreSQL con datos, ensaya primero el procedimiento de [transferencia de 08A](TRANSFERENCIA_POSTGRESQL_MARIADB.md) en un entorno aislado. Conserva la clave original y los volúmenes existentes durante la transferencia. La inicialización de una instalación nueva y la importación de una instalación existente son procedimientos distintos.

## Arquitectura y servidor

El navegador llega directamente a Nginx, que termina HTTPS y sirve los archivos estáticos de `public`. Nginx entrega las rutas dinámicas a PHP 8.4-FPM por `app:9000` en la red `web`. Laravel se conecta a `db:3306` por la red interna `database`. Solo Nginx publica puertos, 80 y 443. El volumen `storage` conserva archivos de Laravel y el volumen `mariadb` conserva la base de datos.

| Recurso | Mínimo orientativo para carga baja | Recomendado para comenzar |
| --- | --- | --- |
| CPU, RAM | 2 vCPU y 4 GiB | 4 vCPU y 8 GiB si también se construyen imágenes en el VPS |
| Disco SSD | 30 GiB libres | 80 GiB o más según archivos, BD, imágenes y retención |
| Plataforma | Linux de 64 bits mantenido y Docker Engine con Compose v2 o posterior | VPS dedicado, almacenamiento de respaldo cifrado fuera del host y monitoreo externo |
| Red | Dominio público, DNS, TCP 80/443 y salida a registros, repositorios y CA | SSH con llave, restringido a IP administrativa o VPN, y sincronización horaria |

Valida estos tamaños con la carga y el volumen de archivos previstos. Usa una cuenta operadora autorizada para Docker, que concede control equivalente a root. En el firewall del VPS y del proveedor expón únicamente HTTP/HTTPS y el acceso administrativo elegido. Comprueba la exposición desde otra red, ya que las reglas de publicación de Docker interactúan con el firewall local. Mantén restringidos el socket y la API de Docker.

## Preparación de una instalación nueva

1. Coloca la versión integrada en una ruta estable y controlada por administradores, por ejemplo `/srv/aula-verde`. Instala Docker/Compose, Certbot con los plugins standalone y webroot, y herramientas GNU para archivos, archivado y verificación. Configura DNS A y, si hay conectividad IPv6 de extremo a extremo, AAAA.
2. Desde la raíz del proyecto, crea la configuración privada de 08A una sola vez. Si el host carece de PHP 8.4, el comando alternativo usa un contenedor temporal con una cuenta sin privilegios y escribe como el operador.

```sh
umask 077
php scripts/docker-init.php --production
# Alternativa al comando anterior
docker run --rm --user "$(id -u):$(id -g)" -v "$PWD:/workspace" -w /workspace \
  php:8.4-cli-bookworm php scripts/docker-init.php --production
```

3. Mantén `.docker/production` en modo 0700 y los dos archivos `*-mariadb.env` en 0600. Edita `app-mariadb.env` con un editor privado. Define `APP_ENV=production`, `APP_DEBUG=false` y `APP_URL=https://<dominio>` sin barra final. Conserva la `APP_KEY` de 32 bytes generada por el inicializador y las credenciales de BD coordinadas por 08A. Configura `SESSION_SECURE_COOKIE=true`, `SESSION_HTTP_ONLY=true`, `SESSION_SAME_SITE=lax`, `SESSION_DRIVER=file` y deja `SESSION_DOMAIN` sin definir para usar una cookie del host. Usa `LOG_CHANNEL=stderr`, `LOG_LEVEL=warning`, `MAIL_MAILER=array` y `EMAIL_VERIFICATION_ENABLED=false` mientras falte SMTP. Si migras mediante `--from-postgres`, agrega explícitamente estos ajustes al archivo generado.
4. Guarda una copia cifrada de la clave y los archivos privados, con acceso auditado. Las variables del contenedor son accesibles para operadores de Docker, así que restringe ese acceso. Usa `compose config --quiet` para revisar el modelo sin volcar variables resueltas. Cambiar contraseñas en un archivo no rota las cuentas de un volumen MariaDB inicializado. Coordina la rotación en MariaDB y en ambos archivos y comprueba las conexiones tras recrear el servicio afectado. Conserva `APP_KEY` entre actualizaciones y restauraciones. Cualquier rotación de esa clave requiere un plan para descifrar los datos previos y gestionar sesiones y enlaces firmados.
5. Elige un dominio ASCII en minúsculas y una etiqueta única por versión. Crea los archivos auxiliares y define el comando Compose para esta sesión de shell. Sustituye los valores de ejemplo antes de ejecutarlo.

```sh
AULA_DOMAIN=aula.example.org
AULA_RELEASE=2026-11-27-r1
printf 'AULA_DOMAIN=%s\nAULA_RELEASE=%s\n' "$AULA_DOMAIN" "$AULA_RELEASE" \
  > .docker/production/release.env
sed "s/aula[.]invalid/$AULA_DOMAIN/g" nginx/production.conf > .docker/production/nginx.conf
chmod 0600 .docker/production/release.env
chmod 0644 .docker/production/nginx.conf
install -d -m 0755 .docker/production/acme
dc() { docker compose --env-file .docker/production/release.env -f compose.prod.yaml "$@"; }
dc config --quiet
dc build app
dc build nginx
dc run --rm --no-deps app true
```

El Dockerfile realiza `composer install` desde `composer.lock`, `npm ci` desde `package-lock.json` y `npm run build`. La etapa de producción prepara el autoloader optimizado con `--no-dev`; app y Nginx reciben los assets de la misma versión. Registra las etiquetas e IDs de ambas imágenes y conserva al menos el par anterior.

PHP-FPM corre como UID/GID 33 y necesita escritura en `storage` y `bootstrap/cache`. El código se sirve desde la imagen y los archivos privados de Laravel permanecen en `storage/app/private`; Nginx carece de acceso a ese volumen. Si una restauración cambia la propiedad, recupérala para UID/GID 33 en los directorios de escritura correspondientes. Usa 0750 para directorios privados y 0640 para sus archivos, con la propiedad correcta. Nginx corre como UID/GID 101 y recibe los montajes de configuración, certificados y ACME en solo lectura. Los modos de certificado indicados abajo asumen Docker Linux convencional sin remapeo de IDs. Si hay user namespaces, rootless o SELinux, verifica los UID/GID efectivos y las etiquetas del montaje en el entorno objetivo antes de exponerlo.

## HTTPS, CSRF y arranque

Emite el primer certificado con Certbot standalone mientras el puerto 80 está libre. Luego instala el certificado para Nginx. Los comandos siguientes que requieren root se ejecutan desde una sesión administrativa en la ruta estable del proyecto, con el dominio y la función `dc` ya definidos.

```sh
certbot certonly --standalone --cert-name "$AULA_DOMAIN" -d "$AULA_DOMAIN"
install -o root -g root -m 0750 scripts/install-production-certificate.sh \
  /usr/local/sbin/aula-install-certificate
RENEWED_LINEAGE="/etc/letsencrypt/live/$AULA_DOMAIN" \
  /usr/local/sbin/aula-install-certificate "$PWD" initial

dc up -d --no-build --wait db app
dc exec -T app php artisan migrate --force
dc exec -T app php artisan migrate:status
dc exec -T app sh -ec 'umask 0027; php artisan optimize'
dc run --rm --no-deps nginx nginx -t
dc up -d --no-build --no-deps --wait nginx
```

Comprueba el estado de cada paso antes de avanzar. Los comandos institucionales existentes se reservan al desarrollo y el seeder general crea una cuenta de prueba. Para una base nueva, el equipo debe aprobar cómo aprovisionar la primera institución y cuenta administrativa antes de habilitar el uso institucional.

El instalador conserva cada par certificado/clave en un directorio `root:101` con modo 0750, archivos 0640 y un enlace `certs/current` actualizado de forma atómica. Mantén la ruta y los scripts de operación bajo control administrativo. Para renovar sin detener Nginx, cambia la autenticación de Certbot a webroot y comprueba su renovación simulada.

```sh
certbot reconfigure --cert-name "$AULA_DOMAIN" --webroot \
  --webroot-path "$PWD/.docker/production/acme"
certbot renew --cert-name "$AULA_DOMAIN" --dry-run
```

Instala un gancho root con modo 0750 en `/etc/letsencrypt/renewal-hooks/deploy/aula-verde`. Ajusta el dominio y la ruta literales. Usa la tarea periódica de Certbot del servidor y confirma su próxima ejecución. El gancho se ejecuta después de una renovación satisfactoria, comprueba `nginx -t` y solicita la recarga. Si esos comandos fallan, repone el enlace anterior. Comprueba externamente la vigencia servida y alerta si el gancho falla. [Guía de Certbot](https://eff-certbot.readthedocs.io/en/stable/using.html)

```sh
#!/bin/sh
set -eu
[ "${RENEWED_LINEAGE:-}" = /etc/letsencrypt/live/aula.example.org ] || exit 0
exec /usr/local/sbin/aula-install-certificate /srv/aula-verde reload
```

Nginx limita el host a un dominio explícito, redirige HTTP a HTTPS excepto el desafío ACME y admite TLS 1.2/1.3. Aplica HSTS de un día inicialmente, `nosniff`, `Referrer-Policy` y protección contra enmarcado. La CSP se limita a `base-uri`, `object-src` y `frame-ancestors` para preservar los recursos actuales. Laravel recibe HTTPS y host canónico por FastCGI, con la IP que ve Nginx. Esta topología no requiere proxies HTTP de confianza. Si se incorpora un balanceador o CDN, limita el ingreso al proxy autorizado, verifica la IP de origen y configura únicamente direcciones y encabezados explícitamente autorizados. Mantén el grupo web y la protección CSRF de Laravel, incluidos los tokens Blade y la validación de origen de Laravel 13. La cookie de sesión debe ser HttpOnly; la cookie auxiliar XSRF se expone a JavaScript por diseño. [Proxies en Laravel](https://laravel.com/docs/13.x/requests#configuring-trusted-proxies) · [CSRF en Laravel](https://laravel.com/docs/13.x/csrf)

## Comprobaciones

Antes de exponer tráfico de usuarios:

- [ ] Versión integrada, imágenes del mismo build verificadas, DNS y firewall revisados, volúmenes correctos y espacio libre suficiente.
- [ ] `dc config --quiet`, validación de variables y `nginx -t` correctos. Revisar en el modelo integrado que únicamente `nginx` tiene `ports` y `database.internal` es `true`.
- [ ] `APP_URL`, `AULA_DOMAIN`, `server_name` y el certificado corresponden al mismo dominio. Llaves y configuración privada fuera del historial Git y del contexto Docker.
- [ ] Migraciones probadas en MariaDB aislada, respaldo restaurado de prueba, recuperación documentada y responsables de guardia identificados.

Después del arranque o de una actualización, comprueba desde otra red:

```sh
curl -fsSI "http://$AULA_DOMAIN/up"       # 308 y Location canónica
curl -fsS "https://$AULA_DOMAIN/up" >/dev/null
curl -fsSI "https://$AULA_DOMAIN/login"   # TLS, cabeceras y cookies
curl -s -o /dev/null -w '%{http_code}\n' -H 'Host: incorrecto.invalid' "http://$AULA_DOMAIN/"
dc ps
dc exec -T app php artisan migrate:status
```

- [ ] Host incorrecto devuelve 421 en HTTP. Verificar rechazo de SNI desconocido y bloqueo de archivos ocultos, PHP directo y rutas privadas.
- [ ] Puertos 3306, 8081 y 9000 inaccesibles desde Internet. La sonda interna de Nginx usa `127.0.0.1:8081/up`; `/up` sobre HTTPS comprueba el arranque de Laravel y `migrate:status` comprueba la conexión y el estado de migraciones.
- [ ] Recurso versionado de Vite responde, el login funciona con una cuenta de prueba autorizada y la cookie de sesión tiene Secure, HttpOnly y SameSite=Lax. Un POST de prueba sin token y con origen ajeno o sin encabezado de origen moderno devuelve el error CSRF esperado. Confirma también un formulario legítimo desde el navegador.
- [ ] Se sirven las páginas de error de 07B y JSON de error sin trazas ni credenciales. Probar acceso autorizado a una evidencia y denegación para otra cuenta.
- [ ] Renovación ACME de prueba correcta, alertas habilitadas y fecha de caducidad visible desde fuera.

## Persistencia, respaldo y monitoreo

Respalda juntos la base y los archivos de `storage/app`. Para obtener un punto coherente, detén Nginx durante la captura y suspende cualquier otro escritor de la aplicación. `--single-transaction` requiere tablas InnoDB y ausencia de DDL concurrente. Este ejemplo usa la cuenta de la aplicación y crea un directorio que solo debe catalogarse como respaldo cuando hayan finalizado todos los comandos.

```sh
umask 077
install -d -m 0700 backups
backup=$(mktemp -d backups/produccion.XXXXXXXX)
dc exec -T db sh -ec 'export MYSQL_PWD="$MARIADB_PASSWORD"
  exec mariadb-dump --protocol=tcp --host=127.0.0.1 --user="$MARIADB_USER" \
    --single-transaction --quick --no-tablespaces --skip-add-drop-table \
    --default-character-set=utf8mb4 "$MARIADB_DATABASE"' > "$backup/database.sql"
dc exec -T app tar -C /var/www/html/storage -czf - app > "$backup/storage.tar.gz"
test -s "$backup/database.sql"
tar -tzf "$backup/storage.tar.gz" >/dev/null
(cd "$backup" && sha256sum database.sql storage.tar.gz > SHA256SUMS)
```

Comprueba cada código de salida. Registra la versión, hora y punto de recuperación. Cifra y transfiere la copia fuera del VPS. Conserva una copia separada de los secretos, los ajustes de operación y el material de recuperación ACME, con acceso restringido. Define frecuencia y retención con el equipo según la pérdida y el tiempo de indisponibilidad tolerados. Como punto de partida, haz un respaldo diario y antes de cada cambio de versión, con simulacros periódicos.

Para restaurar, usa una copia aislada del proyecto con otro nombre Compose, volúmenes nuevos, credenciales propias y sin arrancar Nginx público. Conserva la `APP_KEY` del respaldo. Comprueba las sumas dentro de la carpeta del respaldo, inspecciona que el archivo contenga rutas relativas bajo `app/` y arranca solo la BD. En la shell aislada define `dr` con el archivo de producción, su archivo privado de versión y `-p aula-verde-restauracion`. `backup` debe ser una ruta accesible con los archivos ya descifrados.

```sh
dr() {
  docker compose -p aula-verde-restauracion --env-file .docker/production/release.env \
    -f compose.prod.yaml "$@"
}
(cd "$backup" && sha256sum -c SHA256SUMS)
dr up -d --wait db
dr exec -T db sh -ec 'export MYSQL_PWD="$MARIADB_PASSWORD"
  exec mariadb --protocol=tcp --host=127.0.0.1 --user="$MARIADB_USER" \
    --default-character-set=utf8mb4 "$MARIADB_DATABASE"' < "$backup/database.sql"
dr up -d --no-build --wait app
dr exec -T app tar -C /var/www/html/storage -xzf - < "$backup/storage.tar.gz"
dr exec -T app php artisan migrate:status
```

Valida recuentos, relaciones, acceso a archivos y descifrado con la versión del respaldo antes de promover. Conserva la instancia original para análisis y elige un punto de corte con los responsables de datos. Mantén los volúmenes durante actualizaciones y recuperaciones. Los caches compilados y los logs de contenedor quedan fuera de este respaldo; las sesiones se crean de nuevo tras una restauración.

Consulta `dc logs --tail=100 nginx app db` y `dc ps`. Compose rota registros con el controlador local a 10 MiB por archivo y cinco archivos por servicio. El registro de acceso omite la consulta y el agente de usuario. Los registros de error pueden incluir rutas o datos sensibles, por lo que requieren acceso restringido y redacción antes de salir del entorno. Monitorea errores 5xx, latencia, espacio e inodos, memoria, reinicios, salud de MariaDB, caducidad TLS y antigüedad del último respaldo verificado. Usa una comprobación externa sobre `/up` y una prueba operativa autenticada por separado.

## Actualización y reversión

1. Ensaya el release y las migraciones con una restauración aislada de los datos. Conserva el par de imágenes previo, su configuración de Nginx y Compose, archivos privados respaldados y las referencias de versión. Registra si el esquema actualizado es compatible con el código anterior. Trata cambios de versión de MariaDB como una intervención separada.
2. Construye las dos imágenes nuevas con una etiqueta única. Cambiar `AULA_RELEASE` en `release.env` selecciona las imágenes para el siguiente arranque, mientras los contenedores previos siguen sirviendo hasta que se detengan o recreen. Comprueba `dc config --quiet` y el control de variables en un contenedor puntual.
3. En la ventana de mantenimiento, ejecuta `dc stop nginx`, suspende otros escritores y toma el respaldo coherente de DB y archivos. Conserva el mismo nombre de proyecto y los mismos volúmenes. Ejecuta `dc up -d --no-build --no-deps --wait app`, `dc exec -T app php artisan migrate --force`, `migrate:status` y `optimize` con los comandos del primer arranque. Tras validar, renderiza la configuración versionada de Nginx, comprueba `nginx -t` y ejecuta `dc up -d --no-build --no-deps --force-recreate --wait nginx`. Recrear Nginx vuelve a montar el archivo de configuración.
4. Si hay un fallo, detén el tráfico y conserva registros y estado. Si el esquema y los datos siguen siendo compatibles, selecciona la etiqueta anterior y recupera su configuración compatible. Recrea primero app, ejecuta `optimize` dentro de ese contenedor y comprueba la base. Valida y recrea Nginx. Repite las pruebas posteriores.
5. Si una migración parcial o incompatible impide volver al código previo, usa el respaldo verificado anterior al cambio para recuperar base y archivos en volúmenes separados. Comprueba el resultado con el par anterior. Para promover en el mismo VPS, declara los dos volúmenes recuperados como externos en la configuración del proyecto de producción, indicando en `volumes.storage.name` y `volumes.mariadb.name` sus nombres completos confirmados con Docker. Antes de recrear los servicios, alinea los archivos privados con las credenciales de la BD restaurada y recupera su clave de aplicación. Verifica los montajes efectivos, repite las pruebas posteriores y conserva intactos los volúmenes anteriores. Acuerda con los responsables de datos cómo conciliar las escrituras posteriores al punto de recuperación.

## Validación local sin Docker Engine

Desde PowerShell se puede ejecutar `php -l scripts/check-production-env.php`, `php vendor/bin/phpunit --filter ProductionEnvironmentTest` y `docker compose -f compose.prod.yaml config --no-env-resolution --quiet`. El último comando comprueba la estructura y evita cargar los archivos privados; sobre la versión integrada comprueba también el Compose de 08A. La aceptación operativa incluye construir y arrancar los contenedores, `nginx -t`, conexión y migraciones reales en MariaDB de prueba, restauración, DNS, red pública y emisión/renovación de certificados.
