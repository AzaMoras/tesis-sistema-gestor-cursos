# Migraciones del Sistema de Gestión de Cursos e Inscripciones (Laravel 13 + MariaDB)

## Contenido

- `database/migrations/`: 32 migraciones: **31 tablas nuevas** y **1 alteración de la tabla `users`**.
- `docs/DECISIONES_PENDIENTES.md`: limitaciones y validaciones indispensables para la lógica de negocio.
- `docs/RELACIONES.md`: relaciones FK y consideraciones de consistencia.

## Base de diseño

Versión **propuesta**, basada en el modelo de 32 tablas acordado, con sedes como infraestructura, permisos por rol e institución, solicitudes de registro, cursos 100 % en línea y pagos. No aplica aún la lógica de negocio.

### Compatibilidad Laravel

**No sobrescribir** las migraciones originales de Laravel para `users`, `password_reset_tokens`, `sessions`, `cache` o `jobs`.
Este paquete solamente **altera `users`** agregando `estado_cuenta`, `ultimo_acceso_at` y `deleted_at` (no vuelve a crear la tabla).

**Si la tabla `users` ya tiene alguno de esos campos, adapta la migración ANTES de ejecutarla.**

## Integración segura en el repositorio

1. `git status` y `Get-ChildItem database\migrations` para examinar el estado actual. No copies archivos que dupliquen tablas ya migradas.
2. Crea una rama: `git switch -c feature/migraciones-base-datos`.
3. Extrae el ZIP y copia solamente la carpeta `database/migrations` dentro de tu proyecto Laravel (no reemplaces la carpeta completa ni `.env`).
4. Revisa los cambios: `git diff --stat; git status; php artisan migrate:status`.
5. Configura una BD **de desarrollo vacía** con cuenta dedicada y credenciales en `.env`.
6. `php artisan config:clear` y `php artisan migrate --pretend` para inspeccionar SQL.
7. Cuando todo sea correcto: `php artisan migrate` y luego `php artisan migrate:status`.
8. `git add database/migrations` ; `git commit -m "feat(db): agregar migraciones del sistema de cursos"` ; `git push -u origin feature/migraciones-base-datos`.
9. Comparte la rama / abre un PR para revisar en equipo antes de integrar a `main`.

**IMPORTANTE:** Nunca ejecutes `migrate:fresh`, `migrate:reset`, `db:wipe` ni `docker compose down -v` en una BD con datos importantes.

## Verificaciones hechas

- Sintaxis de los 32 archivos comprobada con `php -l` (PHP 8.4).
- Referencias FK verificadas estáticamente respecto del orden de creación (suponiendo tabla `users` estándar de Laravel).
- **NO** se probaron estas migraciones ejecutándolas contra el MariaDB o un proyecto Laravel del usuario. Realizar prueba en su computadora antes del merge.

## SQL inicial de MariaDB (ejecutar como administrador SOLO para preparar la BD)

```sql
CREATE DATABASE IF NOT EXISTS tesis_gestion_cursos
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'tesis_app'@'localhost'
  IDENTIFIED BY 'REEMPLAZAR_CON_CLAVE_PRIVADA_Y_SEGURA';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, DROP, INDEX, REFERENCES
  ON tesis_gestion_cursos.* TO 'tesis_app'@'localhost';
```

Para el `.env` con TCP `DB_HOST=127.0.0.1`, el usuario MariaDB puede requerir también `tesis_app@127.0.0.1` según la configuración del servidor. Verifica cómo se autentica tu instalación antes de agregar usuarios/privilegios adicionales. La cuenta usada por la aplicación en **producción** debe tener privilegios de DML mínimos; usa otra cuenta restringida para las migraciones.

Archivo `.env` de ejemplo (NO copiar contraseña real al repositorio):

```ini
DB_CONNECTION=mariadb
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=tesis_gestion_cursos
DB_USERNAME=tesis_app
DB_PASSWORD="TU_CLAVE_PRIVADA"
```
