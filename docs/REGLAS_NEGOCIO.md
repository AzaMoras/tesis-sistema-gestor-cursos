# Reglas de negocio — capacitación virtual

Las [reglas v2 aprobadas el 10/11/2026](REGLAS_NEGOCIO_V2.md) son la fuente funcional vigente y prevalecen ante contradicciones. La [matriz de Fase 00](AUDITORIA_FASE_00.md) identifica cobertura real y pendientes.

1. Un visitante puede crear una cuenta de participante con datos mínimos y correo verificado antes de funciones privadas; nunca elige privilegios ni institución ajena desde el navegador.
2. El personal autorizado administra cuentas de gestión, instructores y solicitudes especiales. El registro general no exige INE ni CURP; las evidencias de prerrequisitos se revisan por curso y se almacenan privadamente.
3. El acceso se decide del lado del servidor según permisos, institución y propiedad de datos. Ocultar controles visuales no sustituye autorización.
4. Una persona no puede mantener dos inscripciones al mismo grupo. Validar en servicios y aplicar restricciones de base de datos cuando proceda.
5. El cupo efectivo de un grupo virtual es el cupo máximo definido para ese grupo. No se calcula a partir de salones físicos.
6. Ninguna transición de inscripción puede superar el cupo. Operaciones concurrentes deben quedar protegidas mediante transacción y bloqueo apropiado.
7. Las inscripciones sin cupo se envían a lista de espera cuando esta está habilitada.
8. La lista de espera conserva un orden determinístico; una cancelación puede generar una oferta al siguiente participante elegible.
9. Cada oferta tiene vencimiento; al expirar, libera su reserva y procede con el siguiente conforme a las reglas del servicio.
10. Un instructor no puede tener sesiones virtuales simultáneas incompatibles. Para cursos autogestionados sin sesiones programadas, esta regla no aplica.
11. Cada participante cumple los requisitos de inscripción establecidos para el curso/edición.
12. Las transiciones de inscripción, oferta, pago, evaluación y constancia deben tener estados válidos y quedar registradas cuando corresponda.
13. Las inscripciones sujetas a pago siguen el flujo de verificación establecido; no confiar en datos de confirmación enviados únicamente por el navegador.
14. Solo instructores autorizados o roles con permiso explícito registran asistencia y evaluaciones del grupo correspondiente.
15. Las constancias requieren asistencia mínima **si la edición exige sesiones** y los criterios de evaluación configurados, incluidos módulos y examen final si son obligatorios.
16. Para cursos autogestionados, usar criterios académicos apropiados: no exigir asistencia a sesiones que no existen.
17. Cada edición define acceso indefinido o limitado. Para cursos gratuitos limitados, el predeterminado es 30 días desde activación, configurable, con prórroga gratuita opcional de 14 días una vez. Vencer bloquea nuevas acciones sujetas a plazo, conserva historial y nunca genera cargos automáticos.
18. Las operaciones de desactivación conservarán trazabilidad conforme a la política de eliminación lógica/auditoría.
19. Las cifras del dashboard se calcularán desde datos reales, no desde valores fijos de demostración.
20. Las importaciones CSV validarán formato, permisos, duplicados y errores por fila; no permitirán crear cuentas privilegiadas.
21. Los formativos admiten intentos ilimitados; los finales por módulo tienen tres intentos por ciclo por defecto y tiempo configurable, verificado en servidor. La institución puede imponer configuración sobre la del creador.
22. Agotar intentos sin aprobar exige recompletar actividades obligatorias de reforzamiento después del cierre para abrir otro ciclo. No se reinician notas de otros módulos ni se eliminan intentos previos.
23. Versionar contenido y criterios aplicados; no recalcular ni anular acreditaciones históricas por cambios de curso. La selección inicial propuesta es mejor intento válido, pendiente de integración y publicación por curso.
24. Las constancias son internas; los avisos de vencimiento deben ser idempotentes. Extensiones pagadas requieren aceptación y pago explícitos.

Estas reglas son criterios para las pruebas, no una afirmación de que ya se hayan implementado.
