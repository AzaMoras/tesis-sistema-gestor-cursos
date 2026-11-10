# Aula Verde — Implantación incremental antes de login/logout

**Fecha:** 2026-11-10. Objetivo: incorporar decisiones aprobadas sin romper lógica ya existente ni retrasar innecesariamente el acceso autenticado.

## Antes de tocar código

1. Abrir el proyecto **Laravel**, no la carpeta de LaTeX. En terminal VS Code: `git status --short`, `git branch --show-current`, `git fetch origin`.
2. Si `main` está limpio: `git switch main` y `git pull --ff-only origin main`, luego `git switch -c feature/politicas-academicas-v2`. Si está sucio, identificar y proteger cambios antes de hacer nada.
3. Ejecutar auditoría de migraciones/modelos/rutas/pruebas. Leer `AGENTS.md`, `docs/`, `phpunit.xml`, `tests/TestCase.php`. No ejecutar `migrate:fresh`, `db:wipe` ni comandos destructivos sobre MariaDB local.
4. Guardar copia de seguridad/restauración comprobable de MariaDB, con credenciales fuera de capturas y Git.
5. No agregar las carpetas del ZIP encima de archivos existentes sin revisar; los nuevos `.md` pueden ir a `docs/` y los promps son materiales auxiliares.

## PR 1 — Documentación y políticas (sin alterar base de datos)

- Integrar los contenidos de `DECISIONES_PRODUCTO_V2.md`, `REGLAS_NEGOCIO_V2.md`, `VISION_MISION_Y_ALCANCE_V2.md` a la documentación central, manteniendo enlaces a políticas anteriores que sustituyen.
- Crear matriz de trazabilidad: requisito → modelo/servicio → test → estado.
- Revisar divergencias de estado del desarrollo. No anunciar que módulos planeados ya existen.
- Commit sugerido: `docs: actualizar vision y reglas academicas de Aula Verde`.

## PR 2 — Esquema y servicios base (antes de interfaz)

- Inspeccionar modelos y migraciones existentes. Diseñar migraciones **aditivas** para vigencia, prórrogas, ciclos de evaluación, reforzamiento, versión de contenidos y configuración de exámenes, solo donde realmente falten datos.
- Adaptar contratos de servicios y configuración de reglas operativas con pruebas de casos límite: fechas de acceso, tres intentos por ciclo, repetición de actividades, historial persistente, instructor asignado, prerrequisitos.
- No inventar que ya existe login o autorregistro. No romper pruebas existentes.
- Commit sugerido: `feat(domain): preparar vigencias evaluaciones y recuperacion`.

## PR 3 — Login/logout (tarea originalmente prevista hoy)

- Completar `GET /login`, `POST /login` y `POST /logout` usando auth/CSRF/sesiones nativas y roles reales, con throttling y pruebas Feature. La existencia de las nuevas reglas no obliga a terminar primero sus interfaces.
- Mantener las cuentas existentes y sus roles. Redirecciones por autorización real y selección institucional cuando corresponda.
- Commit sugerido: `feat(auth): implementar inicio y cierre de sesion`.

## PR 4 — Autorregistro público

- `GET /register`, `POST /register`, verificación de correo, protección contra abuso, perfil de participante y asignación segura de contexto; validar dependencias con infraestructura real de correo.
- Conservar mecanismos internos de administración de roles e instructores.
- Commit sugerido: `feat(auth): habilitar registro publico de participantes`.

## PR 5+ — Flujos funcionales

1. Catálogo real y prerrequisitos por curso.
2. Inscripción y pagos con vigencia, sin cargos implícitos.
3. Contenidos, progreso y modalidades de mentoría.
4. Exámenes formativos/finales y recuperación.
5. Constancias y versiones de cursos.
6. Avisos de vencimiento y prórrogas.
7. IA y comprobación externa profesional solo como fase posterior opcional.

## Comprobaciones seguras en cada PR

```powershell
git status --short
git diff --check
php artisan migrate:status
php artisan route:list
php artisan test
```

- `php artisan test` solo tras comprobar `DB_CONNECTION=sqlite` y base temporal/aislada en `phpunit.xml` y `tests/TestCase.php`. Evitar comandos destructivos en MariaDB de desarrollo.
- En un clon o BD **de pruebas** exclusivamente, usar `php artisan migrate --pretend` para inspeccionar sentencias antes de aplicar migraciones. `--pretend` no reemplaza revisar integridad de datos.
- Probar migraciones contra una copia/restauración de datos y revisar claves foráneas e índices en MariaDB de pruebas.
- Revisar manualmente esquema, tests y diffs; `git add` solo archivos específicos del PR; PR a `main` y luego `git pull --ff-only origin main`.

## Cómo integrar documentos de este paquete

Estos archivos son **nuevos** y no sobrescriben por sí solos `AGENTS.md`, `docs/CONTEXTO_PROYECTO.md`, `docs/REGLAS_NEGOCIO.md` ni los capítulos `.tex`. Codex debe fusionar ideas en documentos existentes y documentar qué sustituye. La segunda carpeta de LaTeX puede estar en un repositorio separado: los commits de código y Memoria deben hacerse por separado.

## Definición de terminado (DoD)

- Regla implementada y documentada con ID.
- Migración reversible y segura con datos previos.
- Tests positivos/negativos relevantes y ninguna regresión conocida.
- UI solo si forma parte del PR de esa entrega, sin mostrar prototipos como funcionalidad real.
- Manual mínimo de operación actualizado y commit coherente.
