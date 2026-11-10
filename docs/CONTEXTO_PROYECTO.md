# Contexto y alcance del proyecto

## Propósito
Desarrollar un sistema web para gestionar cursos e inscripciones de instituciones de capacitación virtual, centralizando operaciones académicas y administrativas.

## Identidad y políticas vigentes (10/11/2026)
La misión es facilitar la administración de formación virtual con acceso claro, protección de datos y reglas configurables. La visión es consolidar una alternativa confiable y adaptable para organizaciones de capacitación de menor escala, sustentada en resultados verificables.

El público inicial son centros de capacitación, empresas y pequeñas organizaciones con formación en línea. La propuesta de valor integra oferta, inscripciones y seguimiento académico en una aplicación propia; la viabilidad económica y un piloto ilustrativo de 200–300 participantes son hipótesis, no ventajas ni capacidad demostradas. Texto completo: [visión, misión y alcance v2](VISION_MISION_Y_ALCANCE_V2.md).

Las [decisiones v2](DECISIONES_PRODUCTO_V2.md) y [reglas v2](REGLAS_NEGOCIO_V2.md) sustituyen restricciones anteriores de autorregistro. La [auditoría de Fase 00](AUDITORIA_FASE_00.md) distingue implementación de propuestas.

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
- Instructor: mentor virtual de grupos asignados, sesiones por enlaces externos, asistencia cuando aplique, evaluación y seguimiento.
- Participante: consultar cursos, inscribirse cuando se permita, entregar evidencias, presentar exámenes, revisar progreso y constancias.

## Módulos previstos
1. Portal público: inicio, institución, catálogo, horarios, requisitos, instructores, contacto, aviso de privacidad y captación de prospectos.
2. Autenticación: acceso, cierre de sesión, recuperación, autorregistro de participantes y verificación de correo; personal y privilegios administrados internamente.
3. Administración y seguridad: roles, permisos, usuarios y alcance por institución.
4. Cursos: categorías, cursos, ediciones, grupos, módulos y materiales.
5. Sesiones virtuales: horarios, instructor asignado, enlace de reunión y prevención de traslapes de instructores.
6. Inscripción: requisitos, cupos, lista de espera, ofertas temporales, cancelaciones y vencimiento configurable.
7. Pagos: registro, comprobantes, verificación y estados.
8. Aprendizaje: formativos ilimitados, finales por módulo con intentos por ciclo, reforzamiento, evidencias, versiones y calificaciones históricas.
9. Asistencia solo cuando corresponda y constancias internas sin reconocimiento oficial automático.
10. Reportes, KPI y exportación/importación validada.
11. Docker local, despliegue externo seguro y Memoria en LaTeX.

## Restricciones de implementación
- Validar que cualquier tabla o funcionalidad nueva sea compatible con las migraciones existentes.
- La lista anterior define alcance deseado, **no prueba** que esté implementado.
- Los contenidos y documentos públicos deben respetar privacidad y consentimiento.
