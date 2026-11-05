# Contexto y alcance del proyecto

## Propósito
Desarrollar un sistema web para gestionar cursos e inscripciones de instituciones de capacitación virtual, centralizando operaciones académicas y administrativas.

## Modalidad definitiva
**Todos los cursos, grupos, sesiones y actividades docentes serán 100 % virtuales.**
- Curso autogestionado: contenidos y actividades accesibles según las fechas y condiciones configuradas.
- Curso acompañado: actividades y sesiones virtuales con instructor.
- No existen clases presenciales ni mixtas.
- Las sedes en el modelo, cuando correspondan, son metadatos de infraestructura o administración, no planteles ni aulas.
- No agregar entidades de salones, espacios físicos, préstamo o inventario de equipos por una especificación anterior que contradice esta decisión.

## Roles
- Administrador: usuarios, asignación de roles/permisos, instituciones y sedes administrativas.
- Coordinador: cursos, ediciones, grupos, instructores, horarios y recursos virtuales.
- Recepción: solicitudes de registro, validación de requisitos e inscripciones.
- Instructor: grupos asignados, asistencia, evaluación y seguimiento.
- Participante: consultar cursos, inscribirse cuando se permita, entregar evidencias, presentar exámenes, revisar progreso y constancias.

## Módulos previstos
1. Portal público: inicio, institución, catálogo, horarios, requisitos, instructores, contacto, aviso de privacidad y captación de prospectos.
2. Autenticación: acceso, cierre de sesión, recuperación de contraseña y cuentas administradas por personal autorizado.
3. Administración y seguridad: roles, permisos, usuarios y alcance por institución.
4. Cursos: categorías, cursos, ediciones, grupos, módulos y materiales.
5. Sesiones virtuales: horarios, instructor asignado, enlace de reunión y prevención de traslapes de instructores.
6. Inscripción: requisitos, cupos, lista de espera, ofertas temporales, cancelaciones y vencimiento configurable.
7. Pagos: registro, comprobantes, verificación y estados.
8. Aprendizaje: actividades, exámenes por módulo/final, evidencias y calificaciones.
9. Asistencia y constancias internas.
10. Reportes, KPI y exportación/importación validada.
11. Docker local, despliegue externo seguro y Memoria en LaTeX.

## Restricciones de implementación
- Validar que cualquier tabla o funcionalidad nueva sea compatible con las migraciones existentes.
- La lista anterior define alcance deseado, **no prueba** que esté implementado.
- Los contenidos y documentos públicos deben respetar privacidad y consentimiento.
