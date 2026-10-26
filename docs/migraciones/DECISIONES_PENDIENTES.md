# Decisiones y validaciones pendientes

Las migraciones crean estructuras, NO completan funciones de negocio ni autorización. Revisar con el equipo:

1. **Exámenes:** `actividades.configuracion_json` y `entregas.respuestas_json` guardan preguntas y respuestas de forma simplificada. No implementan banco de preguntas normalizado, corrección automática ni protección de respuestas correctas. Si quieren exámenes avanzados, añadir tablas específicas.
2. **Roles multiinstitución:** roles/permisos globales y asignación scoped con `usuario_rol.institucion_id`. Definir si existirá un superadministrador global (actualmente no está representado por una asignación sin institución). Asegurar acceso solo a datos propios con policies y filtros de tenant.
3. **Referencias interinstitucionales:** claves foráneas garantizan existencia, pero NO que `cursos.categoria_id` sea de la misma institución ni que instructores y grupos compartan tenant. Validar en servicios/policies o reforzar con FKs compuestas.
4. **Cupos y pagos:** transacciones con bloqueo de filas para evitar sobrecupo y doble confirmación. Concurrencia e idempotencia de notificaciones del proveedor de pagos.
5. **Inscripción gratuita:** precio 0 no requiere pago; el estado no debe quedar `pendiente_pago` por defecto sin lógica de negocio.
6. **Lista de espera:** `posicion` única por grupo; evitar colisiones al reordenar. Sin historial separado de múltiples ofertas vencidas.
7. **Sesiones y asistencia:** verificar que `sesiones.grupo_id` coincida con `inscripciones.grupo_id` antes de guardar una asistencia.
8. **Actividades:** si `modulo_id` y `grupo_id` no son nulos, verificar que correspondan a `curso_id`; no guardar claves de respuestas correctas accesibles desde frontend. Elegir política de ediciones/versionado de contenidos una vez haya resultados.
9. **Evaluaciones:** comprobación `0 <= calificacion <= puntaje_maximo` en aplicación; evaluar restricción SQL adicional. `ponderacion` por curso/grupo debe ser coherente.
10. **Documentos:** almacenamiento privado, tamaños y MIME validados, control de acceso, retención de datos y auditoría.
11. **Soft deletes:** `deleted_at` conserva el registro. `inactivos` almacena historial manual de bajas/restauraciones y no actúa como cascada ni como FK polimórfica real.
12. **Pagos y reembolsos:** monto reembolsado agregado sin detalle de cada devolución. Si se requiere trazabilidad financiera rigurosa, recuperar tabla `reembolsos` y órdenes de pago.
13. **Sedes:** datos de infraestructura informativos, NO espacios físicos donde impartir clases.
14. **Usuarios nuevos:** `users.password` viene NOT NULL desde el proyecto base; generar hash aleatorio no utilizable y enviar enlace con token de activación/establecimiento de contraseña. No enviar contraseñas en correo.
15. **Nombres de tablas ya existentes:** NO copiar sobre una BD que ya tenga tablas equivalentes. Adaptar migraciones a cambios incrementales.
