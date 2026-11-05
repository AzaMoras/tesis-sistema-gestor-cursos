# Instrucciones del proyecto para Codex

## Proyecto
Sistema de Gestión de Cursos e Inscripciones en Línea, exclusivo para capacitación **100 % virtual**.
Tecnologías objetivo: PHP 8.4, Laravel 13, Composer, MariaDB, Blade, HTML, CSS y JavaScript.
Arquitectura: MVC de Laravel complementada con servicios, middleware y Policies.

## Fuentes de contexto
Antes de proponer cambios, consulta según corresponda:
- `docs/CONTEXTO_PROYECTO.md`: alcance y actores.
- `docs/REGLAS_NEGOCIO.md`: reglas verificables.
- `docs/DECISIONES_TECNICAS.md`: decisiones y límites de diseño.
- `docs/FLUJO_DESARROLLO.md`: procedimiento local, pruebas y Git.
- `docs/PLAN_NOVIEMBRE_2026.md`: secuencia de entregables.
- `docs/PLANTILLA_TAREA_CODEX.md`: formato para recibir tareas.

## Límites obligatorios
- Nunca implementar sedes docentes, aulas, asistencia presencial o inventario/préstamo de equipos físicos. Las sedes registradas representan infraestructura o metadatos administrativos, no escuelas físicas.
- Los cursos pueden ser autogestionados o acompañados por instructor; ambos son virtuales.
- Mantener una interfaz compartida, con contenido y acciones permitidos según roles y permisos, sin duplicar todas las vistas por rol.
- No crear cuentas privilegiadas mediante registro público. Una solicitud pública no equivale a una cuenta habilitada.
- Aplicar autorización en el servidor por rol, permiso, institución y propiedad del registro; no confiar solo en botones ocultos.
- No duplicar módulos, archivos o tablas que ya existan. Inspeccionar primero el repositorio real.
- No usar emojis en código, textos de interfaz, comentarios ni plantillas. Usar SVG cuando sean necesarios iconos.
- Interfaz en español, semántica, responsiva, con modo claro/oscuro y control visible fuera del menú de opciones.
- Colores: #BEEF9E, #A6C36F, #828C51, #335145, #1E352F. Tipografía principal: Manrope.
- No introducir dependencias, migraciones destructivas, cambios de arquitectura ni eliminación de datos sin justificación y aprobación.
- No mostrar, versionar ni copiar secretos, contraseñas, tokens ni el archivo `.env`.

## Forma de trabajo
1. Confirmar que se trabaja en la copia **local** correcta: `git rev-parse --show-toplevel` y `git branch --show-current`.
2. Revisar `git status --short` antes de modificar archivos. No sobrescribir cambios ajenos.
3. Leer archivos implicados y explicar brevemente el plan. Implementar solo el alcance solicitado.
4. Guardar cambios directamente en la carpeta local y verificar su existencia.
5. Añadir pruebas que verifiquen los resultados reales, incluidos errores esperados y autorizaciones.
6. Antes de ejecutar pruebas con datos, confirmar una base de **testing aislada**; nunca usar comandos destructivos sobre desarrollo o producción.
7. Ejecutar las verificaciones aplicables y reportar literalmente sus resultados; no declarar PASS si no fueron ejecutadas.
8. Mostrar archivos afectados, `git status --short`, riesgos y pendientes.
9. No realizar `git commit`, `git push`, `git merge`, `git reset --hard` ni cambiar de rama por cuenta propia. Estas operaciones las autoriza y ejecuta el equipo.
10. Actualizar la documentación de negocio o decisiones solo cuando la modificación haya sido aprobada.

## Criterios de calidad
- Validaciones de servidor, mensajes claros y estados/transiciones explícitos.
- Operaciones de cupo, inscripción, cancelación y oferta con transacciones y protección ante concurrencia cuando corresponda.
- Listados con búsqueda, filtros, paginación y estado vacío.
- Código coherente con Laravel; servicios para reglas de negocio, controladores para coordinar solicitudes.
- Evitar implementaciones simuladas, datos de muestra fijos en indicadores productivos y pruebas vacías.
