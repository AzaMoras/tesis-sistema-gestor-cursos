# Aula Verde — Decisiones de producto y políticas v2

**Fecha:** 2026-11-10  
**Estado:** Aprobadas por el equipo en conversación; requieren reflejarse en implementación y documentación, y validación académica con los asesores cuando corresponda.  
**Naturaleza:** Especificación, no constancia de funcionalidades implementadas.

## Propósito y público objetivo

Aula Verde es una aplicación web para gestionar **exclusivamente cursos virtuales**: oferta, participantes, inscripciones, pagos cuando procedan, módulos, evaluaciones, acompañamiento académico y constancias internas. Se plantea inicialmente para centros de capacitación, organizaciones y pequeñas instituciones de formación, sin limitarlo jurídicamente a un tamaño de matrícula. Un piloto para organizaciones de aproximadamente 200–300 participantes es una hipótesis de adopción, no una métrica de capacidad probada.

## Decisiones aprobadas

| ID | Decisión | Regla |
|---|---|---|
| DP-01 | Autorregistro | Cualquier visitante puede solicitar crear su propia cuenta mediante datos mínimos y correo verificable. No se exigen INE ni CURP como condición general. |
| DP-02 | Identidad | El sistema utiliza `user_id` interno y correo único normalizado para distinguir cuentas. No afirma identidad civil única ni garantiza impedir todas las multicuentas. |
| DP-03 | Prerrequisitos | Se evalúan **por curso**, no por el mero hecho de registrarse. Curso abierto sin prerrequisito; curso avanzado puede exigir unidades/cursos previamente acreditados o revisión humana de evidencia externa pertinente. |
| DP-04 | Modalidad | Cursos autogestionados o con instructor mentor, ambos 100 % en línea; no se gestionan aulas físicas. |
| DP-05 | Vigencia | Cada edición define si el acceso es indefinido o limitado. Para el modelo gratuito limitado, valor predeterminado propuesto: 30 días y, si la política lo permite, una prórroga gratuita de 14 días por inscripción. |
| DP-06 | Expiración | Expirar bloquea **nuevas** actividades/evaluaciones sujetas a acceso; no borra inscripciones históricas, progreso, calificaciones ni constancias emitidas. |
| DP-07 | Pago opcional | La continuación de acceso puede ofrecerse mediante compra explícita. No hay deuda, cargo recurrente ni conversión automática a pago por no concluir. |
| DP-08 | Práctica | Exámenes de control/formativos con intentos ilimitados y retroalimentación, sin perjuicio de controles de uso razonables. |
| DP-09 | Finales | Exámenes finales por módulo o bloque con 3 intentos **por ciclo de evaluación** por defecto y límite de tiempo configurable; política institucional prevalece sobre la del creador. |
| DP-10 | Recuperación | Al agotar intentos sin aprobar, el módulo entra a reforzamiento; para abrir nuevo ciclo debe completar de nuevo las actividades obligatorias definidas de esa unidad. Nunca se eliminan intentos pasados. |
| DP-11 | Acreditación | Requiere módulos obligatorios aprobados, criterios de evaluación configurados y asistencia solo si el curso acompañado la exige. No equivale a titulación o certificación gubernamental. |
| DP-12 | Instructores | Rol existente `instructor`: mentoría, sesiones virtuales en vivo mediante enlaces externos, materiales/videos, revisión de ejercicios y feedback, con permisos sobre cursos/grupos asignados. |
| DP-13 | Actualizaciones | Las nuevas versiones de cursos conservan historial y acreditaciones anteriores. Cambios sustantivos pueden requerir una actividad de actualización explícita, sin borrar automáticamente aprobaciones. |
| DP-14 | IA | Ideas de asistencia a preguntas/prácticas solo para etapa posterior, con supervisión docente y privacidad. La IA no decide por sí sola acreditaciones. |
| DP-15 | Seguridad | Las autorizaciones se verifican en servidor y por institución/recurso. El autorregistro **nunca** permite elegir roles de gestión o institución ajena sin validación. |

## Límites y aclaraciones

- No hay reconocimiento oficial automático de constancias ni integración de cédula profesional confirmada. Las validaciones externas se investigarán solo si un curso realmente lo exige y la fuente permite integraciones legítimas.
- No se promete impedir cuentas múltiples de una misma persona sin documentación invasiva. Se emplean correo único, verificación y límites de abuso.
- No se afirma que el sistema ya tenga operativas estas políticas: se debe implementar por etapas y demostrar con pruebas.
- Política de 30 + 14 días: propuesta predeterminada para **curso gratuito limitado**, no para cursos gratuitos permanentes ni para todos los cursos pagados.
- Un instructor no se convierte automáticamente en autor de cualquier curso: permisos institucionales y asignaciones son obligatorios.

## Conflictos con decisiones anteriores

- Sustituir la obligatoriedad de creación administrativa de cuentas de participante por autorregistro con correo verificado. Conservar administración interna para roles administrativos e instructores, solicitudes especiales y revisión de evidencias académicas externas.
- No solicitar documentos personales generales para entrar a la plataforma. Evitar eliminar históricos de solicitudes/documentos sin auditoría de datos; restringir su acceso y planificar su retención.
- Mantener los cinco roles: administrador, coordinador, recepción, instructor y participante.

## Autoridad de configuración

1. Política máxima/mínima o bloqueo institucional.
2. Configuración del curso/edición por creador autorizado, dentro de los límites institucionales.
3. Valor predeterminado del sistema si no existe configuración explícita.

Cualquier cambio de política que afecte inscripciones activas debe definir si aplica prospectivamente, preservar historia y dejar bitácora de quién lo autorizó.
