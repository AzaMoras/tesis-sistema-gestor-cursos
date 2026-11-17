# Aula Verde — Reglas de negocio v2 (normativas de producto)

**Fecha:** 2026-11-10. Aplicar solo tras revisar esquema actual; este documento es la fuente funcional para tests de aceptación.

## Cuentas, identidad y autorización

- **RN-REG-01:** registro público de participantes mediante nombre, correo y contraseña; otros datos solo si son necesarios y justificados.
- **RN-REG-02:** correo único normalizado y verificado antes de habilitar funciones privadas de participante. Si no está verificado, mostrar ruta/aviso para verificar; prevenir enumeración de correos cuando corresponda.
- **RN-REG-03:** nunca aceptar `role`, `institution_id`, `permissions`, `account_status` o `email_verified_at` directamente de datos confiados al navegador como privilegios. Asignarlos únicamente desde reglas del servidor y en contexto institucional autorizado.
- **RN-REG-04:** usar `user_id` como identidad técnica; nombres repetidos son permitidos. No prometer prueba de identidad física ni detección perfecta de multicuentas.
- **RN-REG-05:** no pedir INE, CURP ni documentos para el registro general. Evidencias de prerrequisitos se solicitan por curso específico, se minimizan y se almacenan privadamente.
- **RN-REG-06:** conservar la gestión administrativa del personal y asignación de instructores; el público nunca elige rol privilegiado.

## Cursos, prerrequisitos e inscripción

- **RN-CUR-01:** la modalidad es `autogestionado` o `acompanado`, siempre 100 % virtual. Sesiones síncronas solo si corresponde.
- **RN-CUR-02:** los cursos abiertos no requieren grados académicos. Los cursos especializados pueden configurar prerrequisitos académicos específicos, proporcionales y visibles antes de inscribirse.
- **RN-CUR-03:** un curso o módulo acreditado **dentro de Aula Verde** puede satisfacer automáticamente un prerrequisito; evidencia externa requiere revisión humana autorizada, sin prometer conexión a API.
- **RN-CUR-04:** verificar institución, estado del grupo, cupo, requisitos y pago cuando proceda, dentro de la operación de negocio.
- **RN-CUR-05:** un participante no puede tener dos inscripciones activas al mismo grupo. Si se permite reinscripción tras cancelación, definir transición de estado y mantener historial sin violar restricciones existentes.
- **RN-CUR-06:** manejar concurrencia real de cupos con transacciones y bloqueo/condición atómica, probados sobre MariaDB, no solo SQLite.

## Acceso y vigencia

- **RN-VIG-01:** cada edición tiene `sin_vencimiento` o `con_vencimiento`, y una duración o fecha de expiración definida y visible antes de contratar o inscribirse.
- **RN-VIG-02:** modalidad predeterminada propuesta para **curso gratuito limitado**: 30 días desde activación, configurables por institución; prórroga gratuita opcional de 14 días una vez por inscripción. No forzar esos valores a cursos gratuitos permanentes o de pago.
- **RN-VIG-03:** advertir preferentemente a 7, 3 y 1 día de vencer; implementar notificaciones idempotentes y respetar preferencias/canales.
- **RN-VIG-04:** el vencimiento deshabilita nuevos intentos y nuevas entregas sujetas al plazo, no borra progreso histórico, evaluaciones, constancias ni evidencias existentes.
- **RN-VIG-05:** si existe prórroga o ampliación pagada, requerir aceptación y pago explícitos; ningún cargo ni deuda automática por vencer/reprobar.
- **RN-VIG-06:** estados y cambios de vigencia deben poder auditarse y calcularse desde fechas del servidor; no depender exclusivamente de cron para denegar acceso vencido.
- **RN-VIG-07:** casos de caída técnica, accesibilidad y cambios de política podrán recibir una extensión administrativa justificada con bitácora.

## Actividades, intentos y acreditación

- **RN-EVA-01:** cuestionarios de control/formativos tienen intentos ilimitados; mostrar retroalimentación pedagógica y evitar confundirlos con acreditación final.
- **RN-EVA-02:** finales por módulo o bloque tienen por defecto 3 intentos por **ciclo**. El límite y la duración del intento son configurables por el creador autorizado dentro de límites institucionales. Valor ilustrativo de duración: 30 minutos.
- **RN-EVA-03:** tiempo y consumos se verifican en servidor. Cada intento tiene inicio, fecha límite, envío, estado y resultado. La pérdida de conexión no debe crear un intento adicional inadvertidamente: operaciones idempotentes.
- **RN-EVA-04:** la calificación mínima se configura por curso; un valor ilustrativo es 80/100, no una regla global obligatoria.
- **RN-EVA-05:** un módulo se acredita según evaluaciones finales y actividades obligatorias aprobadas. La constancia requiere acreditar todos los módulos obligatorios y criterios complementarios establecidos; asistencia solo si aplica al formato acompañado.
- **RN-EVA-06:** agotados los intentos sin aprobar, el módulo entra en `reforzamiento_pendiente`. El participante debe **recompletar las actividades obligatorias definidas para el ciclo de recuperación** antes de abrir otro ciclo de finales.
- **RN-EVA-07:** mantener históricos inmutables de intentos, calificaciones, recuperaciones y versión del contenido. No borrar ni poner en cero módulos ya acreditados por reprobar otro.
- **RN-EVA-08:** actividades de respuesta objetiva pueden calificarse automáticamente con banco y respuestas; evidencias abiertas requieren revisión humana. Proteger respuestas correctas hasta el momento autorizado por la política académica.
- **RN-EVA-09:** la regla de selección de nota (por ejemplo, mejor intento) debe definirse y publicarse por curso; valor inicial propuesto: mejor intento válido. No cambiar retroactivamente después de recibir intentos, salvo proceso auditado.
- **RN-EVA-10:** ningún estudiante puede abrir finales con acceso vencido, salvo prórroga válida; permitir revisar sus resultados e historial cuando la cuenta esté activa.

## Instructores y materiales

- **RN-INS-01:** conservar el rol `instructor` como mentor. Puede gestionar recursos del curso/grupo al que fue asignado y según permisos de institución; no adquiere control global.
- **RN-INS-02:** ofrecer materiales y videos de conceptos y ejercicios, con metadatos, orden, disponibilidad y control de acceso.
- **RN-INS-03:** programar clases en vivo por enlace a proveedor externo, no implementar motor de videollamadas propio. Definir fecha, zona horaria, anfitrión, enlace y política de grabación.
- **RN-INS-04:** instructor revisa entregas humanas, da retroalimentación y atiende dudas. La institución define si asistencia es requisito de aprobación.
- **RN-INS-05:** cambios que alteren una evaluación o un curso publicado deben quedar auditados y seguir límites de edición/versión.

## Versiones, constancias y privacidad

- **RN-VER-01:** conservar versión del contenido/criterios bajo la cual se evaluó un intento. Actualizaciones menores no anulan el progreso ni obligan a volver a pagar el mismo acceso adquirido; cambios sustantivos pueden definir una actualización complementaria, sin alterar condiciones comerciales aceptadas retroactivamente.
- **RN-CON-01:** emitir **constancias internas** con folio único y verificación solo tras comprobar criterios definitivos. No se anuncian como reconocimiento oficial.
- **RN-PRIV-01:** almacenar la mínima información necesaria, separar evidencias privadas de contenido público, restringir su acceso y definir retención/borrado conforme a políticas.
- **RN-AUD-01:** registrar cambios de roles, criterios de acreditación, prórrogas, resultados manuales y políticas relevantes.

## Casos pendientes de política institucional (no bloquean definición de esquema)

- Precisión de umbrales, máximos y mínimos configurables; tiempos de recuperación por unidad, pausas de exámenes y acomodos de accesibilidad.
- Número máximo de ciclos de recuperación: **inicialmente sin límite global**, sujeto a acceso vigente y reglas institucionales; cada ciclo exige reforzamiento auténtico y mantiene historial.
- Tratamiento de evidencias académicas externas y revisión/retención.
- Diferencias operativas de cursos gratuitos, pagados y promociones.
- Responsables de publicar nuevas versiones de un curso y decidir equivalencias.
