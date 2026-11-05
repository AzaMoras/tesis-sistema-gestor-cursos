# Reglas de negocio — capacitación virtual

1. Una persona puede enviar una solicitud pública, pero no obtener privilegios administrativos ni activarse automáticamente como usuario autorizado.
2. Solo personal autorizado revisa solicitudes, valida documentos y crea/vincula cuentas.
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
17. La fecha límite para finalizar un curso o la caducidad de inscripción será configurable; 30 días solo si el equipo lo aprueba como valor inicial.
18. Las operaciones de desactivación conservarán trazabilidad conforme a la política de eliminación lógica/auditoría.
19. Las cifras del dashboard se calcularán desde datos reales, no desde valores fijos de demostración.
20. Las importaciones CSV validarán formato, permisos, duplicados y errores por fila; no permitirán crear cuentas privilegiadas.

Estas reglas son criterios para las pruebas, no una afirmación de que ya se hayan implementado.
