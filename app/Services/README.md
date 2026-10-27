# Capa de servicios

Los servicios usan los modelos y columnas actuales. No contienen controladores,
rutas, seeders, integraciones de pasarela, correos de activación ni generadores de
PDF. La creación de estos archivos no ejecuta operaciones en MariaDB.

## Autorización

Cada operación privada recibe un `User`, el identificador de institución y los
identificadores del recurso. Recarga la cuenta, exige `estado_cuenta = activa`,
institución activa y asignación en `usuario_rol`. Los permisos se consultan a
través de `permisosEnInstitucion()`, sin excepciones automáticas de administrador.
Los recursos se resuelven por sus relaciones institucionales, sin confiar en
atributos modificados de modelos recibidos desde otra capa.

| Operación | Autorización adicional |
| --- | --- |
| Crear solicitud | Pública, solo institución activa y datos de contacto validados |
| Consultar/rechazar solicitud | `solicitudes.revisar` |
| Aprobar y provisionar Participante pendiente | `solicitudes.revisar` y rol `administrador` en la institución |
| Inscribir/cancelar | Participante propietario con rol institucional, o `inscripciones.gestionar` |
| Registrar pago pendiente | Participante propietario, o `pagos.registrar` |
| Verificar/rechazar pago | `pagos.verificar` |
| Confirmar inscripción cubierta por pagos verificados | `pagos.verificar` |
| Registrar evaluación | `evaluaciones.calificar`; instructores sin rol de gestión solo en grupos asignados |
| Consultar desglose académico | Participante propietario, o `evaluaciones.calificar` con el mismo control de grupo |
| Diagnosticar elegibilidad de constancia | `constancias.emitir` |
| Consultar una constancia existente | Participante propietario, o `constancias.emitir` |

Estas claves son las convenciones aprobadas para esta etapa, no registros
insertados en la BD. Si falta un rol, permiso o asignación, se deniega la operación.

## Reglas todavía pendientes

`Soporte/ReglasOperacion` es una política de servidor inmutable. Sus parámetros
no deben obtenerse del formulario. El contenedor puede resolver los servicios sin
configuración adicional, pero las operaciones que requieran una regla no definida
se detienen con `LogicException`, antes de confirmar escrituras.

Se conservan los valores iniciales definidos por las migraciones: solicitud
`pendiente`, inscripción `pendiente_pago`, pago `pendiente` y entrega `enviada`.
La evaluación manual usa `tipo_calificacion = manual`, también definido en el
esquema. No se eligen automáticamente nombres de estados finales.

Antes de habilitar cada operación se debe acordar y suministrar una instancia de
`ReglasOperacion` desde código del servidor (por ejemplo, un enlace en un provider
en una etapa posterior), con las decisiones necesarias:

| Parámetro | Contenido |
| --- | --- |
| `estados` | Mapa de las claves siguientes a los valores de estado acordados |
| `apertura` | Listas de estados inscribibles para `curso`, `edicion` y `grupo` |
| `minutosReserva` | Duración positiva de una reserva de pago |
| `cupoNuloIlimitado` | Decisión explícita sobre `grupos.cupo_maximo = null` |

Claves configurables de `estados`:

- `solicitud.aprobada`, `solicitud.rechazada`.
- `inscripcion.confirmada`, `inscripcion.cancelada`.
- `pago.verificado`, `pago.rechazado`.
- `requisito.validado`, `actividad.evaluable`.
- `documento.aprobado`, si la solicitud tiene documentos adjuntos.

Los valores son claves de hasta 25 caracteres en minúsculas y sin acentos,
compatibles con las columnas existentes. Se rechazan estados repetidos en las
transiciones de una misma entidad. `borrador` no habilita inscripciones.

No se modificó ningún provider ni configuración para elegir estas reglas sin
decisión del equipo. Aprobar la estructura no define automáticamente estos valores.

## Operaciones y límites

### SolicitudRegistroService

`crear()` valida nombre, apellidos y correo. Serializa las solicitudes de una
institución para impedir duplicar una solicitud pendiente por correo. No acepta
roles, contraseñas, estados ni identidad del revisor desde el formulario.

`consultar()`, `rechazar()` y `aprobar()` respetan la institución. Aprobar requiere
Administrador y permiso de revisión, bloquea la solicitud y crea atómicamente un
usuario pendiente, su perfil Participante, la asignación del rol `participante` y
su auditoría. El rol debe existir previamente. No crea cuentas privilegiadas,
no activa cuentas ni envía contraseñas. La contraseña aleatoria se almacena con
el cast de hash original de User y no se devuelve en el resultado.

Si hay documentos adjuntos, todos deben tener el estado de aprobación acordado;
el servicio no los marca como revisados por sí mismo. Queda pendiente definir
qué tipos de documento son obligatorios y el flujo de su revisión. La solicitud
pública se audita con `bitacora.usuario_id` nulo, permitido por la migración.

La coincidencia de correo con una cuenta existente, incluso eliminada lógicamente,
bloquea el aprovisionamiento: falta implementar la verificación de identidad para
una vinculación segura. La activación mediante enlace también queda pendiente.

### InscripcionService

`inscribir()` valida institución, categoría, curso, edición, grupo y participante.
Exige rol Participante del inscrito, estados de apertura acordados, fechas válidas,
cierre de inscripciones y requisitos obligatorios validados con revisor y fecha.
Los requisitos documentales necesitan una ruta de evidencia registrada; este
servicio no sube ni revisa documentos.

El precio procede de `ediciones.precio`. Una inscripción gratuita se confirma;
una de pago conserva `pendiente_pago` y reserva con la duración acordada. Nunca
se reutiliza silenciosamente una inscripción cancelada: la restricción única
participante/grupo se mantiene. Un cupo cero no admite inscripciones.

`cancelar()` es idempotente, libera la reserva y registra auditoría. La cancelación
con pagos ya revisados, entregas, asistencias o constancias se bloquea hasta definir
las reglas financieras y académicas de ese caso. No realiza devoluciones.

`confirmarConPagosVerificados()` es un paso separado de verificar un pago. Exige
reserva vigente, requisitos y cupo, y fondos verificados suficientes en la moneda
de la edición. No cuenta pagos pendientes ni importes reembolsados. No recupera
reservas vencidas ni cambia automáticamente un pago real recibido fuera de plazo.

Se consideran ocupados los registros no cancelados, excepto reservas de pago con
vencimiento conocido ya cumplido. Los estados desconocidos y reservas sin fecha
se cuentan conservadoramente para no liberar lugares sin una regla.

### PagoService

`registrar()` necesita una `referenciaInterna` UUID reutilizable en reintentos.
Guarda metadatos de un pago pendiente; el importe se recibe como texto decimal
o entero, no como float. El precio y la moneda no se toman del formulario. Repetir
la referencia con los mismos datos devuelve el registro existente; cambiar los
datos produce un error. Las referencias externas únicas también se respetan.

`verificarManual()` es una declaración explícita de personal con `pagos.verificar`:
necesita fecha real no futura y evidencia textual de su revisión, conservada en
bitácora. No valida una transferencia o pasarela automáticamente. No confirma
la inscripción; después se debe invocar la operación correspondiente del servicio
de inscripciones. Así se puede registrar un pago real tardío sin conceder un lugar.

`rechazar()` solo cambia pagos pendientes y registra el motivo en bitácora. No
revoca pagos verificados ni simula reembolsos. Los sobrepagos se bloquean hasta
acordar una política. Las cargas de comprobantes y webhooks verificados quedan
fuera de este alcance.

El esquema no guarda una moneda histórica en la inscripción. Hasta contar con
una política de versionado, no debe cambiarse la moneda de una edición con pagos:
los servicios rechazan discrepancias en lugar de convertir monedas.

### EvaluacionService

`registrar()` valida entrega, curso, módulo, grupo, inscripción confirmada,
intento permitido y puntaje entre cero y `puntaje_maximo`, compatible con
`DECIMAL(8,2)`. Registra evaluación manual y auditoría en la misma transacción.
Un reintento idéntico devuelve la evaluación existente; una corrección diferente
se bloquea hasta definir su política.

`desglose()` devuelve los resultados por actividad e intento. Calcula el porcentaje
de cada intento como cociente exacto (numerador/denominador), sin inventar un
redondeo o selección de intentos. No devuelve respuestas ni configuración privada
de las actividades. `calificacion_final` permanece nula, con las decisiones pendientes
enumeradas: intentos, faltantes, ponderaciones, redondeo y plazos. No corrige
exámenes automáticamente ni cambia el estado de la entrega a un valor inventado.

`desgloseParaConstancia()` reutiliza el cálculo con el permiso de emisión, sin
obligar a que quien verifica constancias pueda también calificar.

### ConstanciaService

`comprobarElegibilidad()` devuelve mínimos de la edición, actividades sin evaluar,
estados de sesiones y asistencias, inconsistencias de grupo y decisiones faltantes.
Mientras falten reglas académicas o documentales devuelve `elegible = false` y
porcentajes/calificación final nulos; no presenta un diagnóstico incompleto como
una acreditación real. `obtenerExistente()` consulta una constancia ya registrada.

No hay método que finja emitir: falta definir asistencia, acreditación, plazos,
folio y generación real del PDF. `constancias.ruta_pdf` es obligatoria y no se
rellena con rutas ficticias. No se crean ni modifican constancias en esta etapa.

## Transacciones y concurrencia

Las escrituras de negocio y bitácora usan transacciones con hasta tres intentos
ante bloqueos concurrentes recuperables. Las operaciones de inscripción, pago y
evaluación siguen el orden edición -> grupo -> inscripción -> pago/entrega.

El conteo de cupo usa lecturas bloqueantes de las inscripciones, no un COUNT de
una instantánea antigua bajo el aislamiento REPEATABLE READ de MariaDB. Las
reservas vigentes ocupan lugar. El pago y la confirmación vuelven a bloquear la
misma edición y grupo, y la confirmación revalida requisitos y vencimiento.

Las restricciones únicas del esquema siguen siendo la última protección. Cualquier
futuro flujo de escritura de estos recursos debe respetar el mismo protocolo.

## Integración pendiente

Los servicios no se conectaron a nuevas rutas, vistas o controladores. Esa capa
deberá conservar los middleware, construir parámetros validados y presentar las
excepciones de autorización (403), validación (422) y recurso inexistente (404).
Las decisiones ausentes producen errores de configuración, no estados ficticios.
La verificación de concurrencia con MariaDB y los tests automatizados corresponden
a la etapa de pruebas indicada por el profesor.
