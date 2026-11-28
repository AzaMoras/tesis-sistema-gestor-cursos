# Transferencia de PostgreSQL a MariaDB

La fase 08A inicia MariaDB con un volumen nuevo. Los volúmenes
`aula-verde-local_postgres` y `aula-verde-production_postgres` se conservan
para PostgreSQL 17. Conserva también los archivos privados y `storage`.

## Preparar una instalación existente

Respalda los archivos privados anteriores. El inicializador conserva APP_KEY
y los ajustes de aplicación del `app.env` anterior y genera credenciales nuevas
para MariaDB. Elige el entorno que corresponda:

```powershell
php scripts/docker-init.php --from-postgres
php scripts/docker-init.php --production --from-postgres
```

Al iniciar el Compose nuevo se puede reemplazar el contenedor `db` del proyecto
original. Si hay datos en servicio, acuerda la ventana de corte y respalda antes
de arrancar. Para volver a acceder al origen, usa la revisión anterior del
proyecto en un directorio separado con PostgreSQL 17, sus archivos privados y
los nombres de proyecto y volumen originales. Opera un stack a la vez por
nombre de proyecto.

## Transferir los datos si el equipo lo requiere

1. Pausa las escrituras para el respaldo final. Genera un volcado lógico con
   `pg_dump -Fc` usando PostgreSQL 17. Verifica una restauración de ensayo con
   `pg_restore` en PostgreSQL aislado y respalda `storage` y `APP_KEY`.
2. Aplica las migraciones Laravel en MariaDB aislada. Prepara una exportación
   tabular o un proceso ETL revisado. Un volcado nativo de PostgreSQL se
   restaura en PostgreSQL.
3. Importa en orden de dependencias con claves foráneas activas. Conserva IDs,
   referencias, hashes, datos cifrados e históricos. Valida zona horaria UTC,
   JSON, tipos sin signo, rangos de fechas, duplicados, colación y valores de
   auto-incremento. El destino mantiene su propio registro de migraciones.
4. Concilia conteos, relaciones y archivos privados. Comprueba autenticación,
   acceso por institución y consultas críticas con los datos restaurados.
5. Acuerda el corte y el procedimiento de reversión. Conserva el origen
   y define cómo reconciliar las escrituras posteriores a un corte.

La fase 08A deja la transferencia y la verificación de datos históricos
pendientes de una operación separada.
