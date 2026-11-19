# Primer administrador institucional: solo desarrollo local

Implementación: 19/11/2026, copia local en `feature/gestion-oferta`.
No es un procedimiento de despliegue ni una herramienta web de alta de personal.

## Estado y alcance

El operador informó: un usuario, ninguna institución, ningún rol, ninguna
asignación en usuario_rol y ausencia de cursos.gestionar. Esos conteos son datos
aportados por el operador; en esta tarea no se consultó ni modificó MariaDB.

Se inspeccionaron las migraciones reales de instituciones, roles, permisos,
rol_permiso, usuario_rol, users y bitacora. Sus FK y restricciones únicas permiten
la operación sin nuevas tablas ni migraciones. La migración de vigencia no es una
dependencia y no se aplica sobre MariaDB.

Se añaden exclusivamente:

- `app/Console/Commands/InicializarInstitucionLocal.php`.
- `app/Services/InicializacionInstitucionalService.php`.
- `tests/Feature/InicializarInstitucionLocalTest.php`.
- Este documento.

Se conservan los cambios anteriores de la Fase 02A.1, incluidos sus archivos sin
seguimiento. No se modifica el comando `aula-verde:demo-oferta`, que continúa
restringido a su SQLite demostrativa. Tampoco se modifican `.env`, LaTeX,
credenciales, rutas, modelos, migraciones ni configuración existentes.

## Requisitos y comprobaciones antes de escribir

El comando es `aula-verde:inicializar-institucion` y usa la conexión predeterminada
de la aplicación, la misma que utilizará la gestión web. No selecciona otra
conexión por su cuenta.

1. Entorno `local`, configuración sin caché y ejecución interactiva con salida
   visible. Rechaza producción, staging, `--no-interaction`, `--quiet` y `--silent`.
2. `--base` debe contener el nombre exacto esperado. Muestra el nombre configurado
   antes de cualquier escritura y exige coincidencia.
3. Driver mysql/mariadb, host TCP local (`127.0.0.1`, `localhost` o `::1`) y
   coincidencia literal con `--host` y `--puerto`. No admite sockets, prefijos ni
   conexiones separadas de lectura/escritura. Los destinos de DB_URL se comprueban
   mediante la configuración resuelta; nunca se imprime la URL o credenciales.
4. Consultas de lectura verifican PDO mysql, `DATABASE()`, `VERSION()` con MariaDB
   y `@@port`. Comprueba que las siete tablas implicadas existen y usan InnoDB.
   Una configuración con túnel o puerto remapeado no se valida como servidor
   directo si su puerto efectivo es diferente.
5. Exige archivo de respaldo privado, legible, no vacío, de extensión `.sql`,
   `.sql.gz` o `.dump`, y checksum SHA-256 coincidente con `--sha256`.
6. El ID de usuario se proporciona con `--usuario` o se pregunta explícitamente.
   No se toma el primer usuario aunque solo exista uno. Muestra ID, nombre,
   correo y estado. Rechaza cuentas inexistentes, eliminadas o no activas.
7. Explica todas las escrituras y el alcance global del permiso del rol.
8. Solicita dos confirmaciones, ambas negativas por defecto: que se comprobó la
   restauración del respaldo en otro entorno y que se autoriza otorgar los
   privilegios a la cuenta mostrada.

Comprobar que un archivo existe y su hash coincide NO demuestra que se pueda
restaurar ni que corresponda a la base. Esa comprobación y la autorización de la
identidad son responsabilidad del operador y se piden expresamente. El comando
no ejecuta herramientas de respaldo ni restauración.

La excepción de testing exige `--autorizar-pruebas`, entorno `testing` y SQLite
`:memory:`. No permite inicializar una MariaDB bajo ese flag ni usar SQLite local.
Los tests no registran conexiones externas.

## Efecto exacto de cada escritura

Todas se ejecutan en una transacción. Los registros existentes se reutilizan sin
sobrescribir sus atributos, siempre que la institución corresponda a la identidad
de demostración esperada y esté activa. Los índices únicos protegen de duplicados.

| Tabla | Efecto cuando falta |
|---|---|
| instituciones | Crea `Institución DEMOSTRATIVA Aula Verde (desarrollo local)`, slug `aula-verde-desarrollo-demo`, estado `activa`, sin sede física |
| roles | Crea clave `administrador`, nombre `Administrador` |
| permisos | Crea clave `cursos.gestionar` |
| rol_permiso | Asocia el permiso al rol administrador |
| usuario_rol | Asigna ese rol a la cuenta elegida dentro de la institución demostrativa; asignado_por queda nulo porque el operador de consola no es una cuenta web autenticada |
| bitacora | Registra los cambios, usuario destino, origen Artisan y checksum del respaldo; no almacena contenido del respaldo ni credenciales |

**rol_permiso es global:** añadir cursos.gestionar al rol administrador también
habilita ese permiso para otras asignaciones ya existentes de ese rol. El comando
informa cuántas asignaciones tiene antes de pedir autorización. No crea pertenencia
a otras instituciones ni elimina los controles institucionales de las rutas.

No crea cuentas ni perfiles, no activa cuentas, no cambia contraseña, correo,
email_verified_at o remember_token. Revalida la cuenta y su identidad dentro de la
transacción después de las confirmaciones. No crea categorías ni cursos.

Repetir con la misma cuenta conserva una institución, un rol, un permiso y cada
asociación; si no hay cambios, tampoco agrega otra entrada de auditoría. No es un
comando de reinstalación: si el slug está ocupado por una institución distinta,
inactiva o eliminada, se detiene sin adoptarla ni reactivarla.

## Ejecución manual

Antes de ejecutar, **dispón de un respaldo verificable y comprueba su restauración
en una base separada**. Guarda el respaldo fuera del repositorio y no lo compartas
con el chat. No continúes sin esa comprobación.

Abre una terminal PowerShell nueva en el proyecto, con tu configuración habitual
de desarrollo local. No reutilices la terminal que apuntaba a SQLite para la demo
ni fuerces un entorno local sobre una configuración de producción.

Puedes identificar la cuenta con esta consulta de solo lectura desde tu gestor
de MariaDB, sin consultar contraseñas ni tokens:

```sql
SELECT id, name, email, estado_cuenta
FROM users
WHERE deleted_at IS NULL;
```

Después ejecuta:

```powershell
git branch --show-current
git status --short

$baseLocal = Read-Host 'Nombre exacto de la base MariaDB LOCAL prevista'
$idAdministrador = Read-Host 'ID de la cuenta existente y activa que autorizas'
$respaldoLocal = Read-Host 'Ruta absoluta del respaldo cuya restauración comprobaste'
$checksumRespaldo = (Get-FileHash -LiteralPath $respaldoLocal -Algorithm SHA256).Hash

php artisan aula-verde:inicializar-institucion --base="$baseLocal" --host=127.0.0.1 --puerto=3306 --usuario="$idAdministrador" --respaldo="$respaldoLocal" --sha256="$checksumRespaldo"
```

Si tu host configurado es `localhost` o el puerto es otro, adapta esas opciones
para que coincidan exactamente. No inventes el nombre de la base ni el ID.
Si una comprobación falla, revisa la causa; no ejecutes migraciones para intentar
sortearla. El comando devuelve código 0 al completar y 1 si rechaza o se cancela.

Antes de responder afirmativamente verifica la base efectiva, identidad de la
cuenta e institución que muestra. Al terminar informa el ID de institución.

Para comprobar el acceso:

1. Abre la aplicación local habitual o ejecuta `php artisan serve` desde esa
   misma terminal, conservando la conexión MariaDB de desarrollo.
2. Inicia sesión en `/login` con la cuenta seleccionada y su contraseña existente.
3. Abre `/gestion` y selecciona la institución DEMOSTRATIVA.
4. Comprueba `/gestion/{ID_INSTITUCION}/cursos` y las secciones de categorías,
   ediciones y grupos. Los listados inicialmente vacíos son correctos.

No se ejecuta `migrate`, `migrate:fresh`, `db:wipe`, seeders ni la migración de
vigencia como parte de este procedimiento. No hay commits ni operaciones Git
automáticas.

## Verificación de esta entrega

Antes de las pruebas se comprobaron phpunit.xml, las barreras de tests/TestCase.php
y ausencia de bootstrap/cache/config.php. Resultado de aislamiento:

```text
OK (1 test, 5 assertions)
```

Las pruebas nuevas usan únicamente SQLite :memory:, una cuenta creada por factory
y un archivo ficticio temporal para probar las verificaciones del checksum. Las
respuestas a las confirmaciones son simuladas exclusivamente dentro de PHPUnit.
No se usan respaldos ni cuentas de MariaDB. RefreshDatabase prepara el esquema
solo en memoria; no se invocaron migraciones sobre desarrollo.

```text
php vendor/phpunit/phpunit/phpunit --do-not-cache-result --colors=never --filter InicializarInstitucionLocalTest
OK (14 tests, 128 assertions)
```

Suite completa final:

```text
php vendor/phpunit/phpunit/phpunit --do-not-cache-result --colors=never
OK (234 tests, 1511 assertions)
```

Pint en los tres archivos PHP: `{"tool":"pint","result":"passed"}`.
`php -l`: `No syntax errors detected` en cada uno.
`git diff --check`: sin salida, código 0. El comando aparece en Artisan y se
comprobó su ayuda sin ejecutarlo sobre la conexión de desarrollo.

La primera ejecución detectó una comparación incorrecta del fixture: el modelo
recién creado aún no tenía hidratadas las columnas nulas y el orden de atributos
de la BD. Se corrigió recargando el fixture antes de comparar la conservación
íntegra de sus atributos; no se cambió la lógica productiva por ese fallo.

Se verifican asignación y acceso HTTP real a la gestión, conservación de cuenta,
repetición, elección explícita, estados inactivos/bajas, respaldo/hash inválidos,
cancelaciones, entorno, no-interaction/quiet/silent, colisión de institución,
reutilización de roles/permisos, cambio de identidad antes de escribir y rollback
si falla el INSERT de auditoría.

Las comprobaciones del servidor MariaDB y motores InnoDB se ejecutarán cuando el
operador invoque manualmente el comando. No fueron certificadas contra el servidor
local durante esta tarea, ni se afirma concurrencia real validada en ese motor.
