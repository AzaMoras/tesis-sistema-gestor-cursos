# Aula Verde — Plan de pruebas y criterios de aceptación v2

**Fecha:** 2026-11-10. Cada caso necesita al menos un test automatizado pertinente o prueba manual justificada, con identificación de entorno. No afirmar que ya pasó sin ejecutarlo.

| ID | Escenario | Resultado esperado |
|---|---|---|
| TA-01 | Dos usuarios distintos con el mismo nombre | Pueden existir con IDs distintos y correos diferentes. |
| TA-02 | Registro con correo existente | Se rechaza duplicado sin revelar información sensible adicional. |
| TA-03 | Solicitud de registro con `role=admin`, `institution_id` ajena o estado activo | Se ignoran/rechazan esos campos y no se otorgan privilegios. |
| TA-04 | Cuenta aún sin correo verificado | No accede a acciones privadas que exigen verificación. |
| TA-05 | Curso abierto sin prerrequisitos | No solicita documentos personales ni constancia previa. |
| TA-06 | Excel avanzado exige Excel básico acreditado internamente | Se valida historial del mismo usuario y versión/criterios aplicables. |
| TA-07 | Evidencia externa de prerrequisito | No se considera validada automáticamente si requiere revisión humana. |
| TA-08 | Curso gratuito limitado activado | Se fija vencimiento correcto con zona horaria/UTC. |
| TA-09 | Acceso aún vigente | Permite realizar actividad autorizada. |
| TA-10 | Acceso caducado | Bloquea nuevo intento/entrega; conserva historial y resultados. |
| TA-11 | Prórroga de 14 días habilitada, no utilizada | Se aplica una vez y queda bitácora; segundo uso se rechaza. |
| TA-12 | Curso gratuito permanente | No recibe vencimiento automático de 30 días. |
| TA-13 | Curso con oferta de extensión pagada | No genera deuda, cargo ni consentimiento implícito por vencer. |
| TA-14 | Prueba formativa | Permite repetir tras fallos sin bloqueo académico por número de intentos. |
| TA-15 | Examen final con tres intentos | Cuarto intento dentro del mismo ciclo se rechaza. |
| TA-16 | Tiempo máximo de examen final | El servidor impide aceptar entregas fuera de plazo salvo regla explícita documentada. |
| TA-17 | Falló tres veces | El módulo pasa a estado de reforzamiento y no abre nuevo ciclo automáticamente. |
| TA-18 | Reforzamiento incompleto | No permite abrir nuevo ciclo. |
| TA-19 | Reforzamiento terminado después del cierre | Abre nuevo ciclo con hasta tres intentos y registra el número de ciclo. |
| TA-20 | Nuevo ciclo abierto | Historial de intentos antiguos y notas permanece disponible e inalterado. |
| TA-21 | Módulo 1 acreditado y módulo 2 reprobado | No elimina ni reinicia la acreditación del módulo 1. |
| TA-22 | Participante sin módulo obligatorio acreditado | No emite constancia. |
| TA-23 | Curso autogestionado sin sesiones | No exige asistencia presencial/síncrona como regla universal. |
| TA-24 | Instructor no asignado a grupo | No puede publicar materiales ni revisar calificaciones ajenas. |
| TA-25 | Instructor asignado | Puede programar clase virtual con enlace externo y publicar videos autorizados. |
| TA-26 | Cambio menor de material | Conserva progreso anterior; registra versión aplicable. |
| TA-27 | Cambio sustantivo de evaluación | No reescribe resultados ya emitidos; define equivalencias/actividad de actualización. |
| TA-28 | Participante modifica URL o identificador | No puede ver registros de otro participante o institución. |
| TA-29 | Dos solicitudes simultáneas por último cupo | No sobrepasa capacidad; verificar sobre MariaDB de pruebas con concurrencia real. |
| TA-30 | Tarea de avisos ejecutada dos veces | No duplica notificaciones ni modifica vencimiento. |

## Evidencia esperada por PR

- Tests nuevos y suite anterior en verde, o informe específico de bloqueadores.
- Comparación entre reglas documentadas y casos cubiertos.
- Migraciones probadas en base aislada con datos existentes simulados.
- `php artisan migrate:status`, `php artisan route:list`, `git diff --check` cuando proceda.
- No exponer contraseñas, INE/CURP, tokens, `.env` ni datos personales en commits o capturas.
- Anotar explícitamente casos aún no verificables. Evitar pruebas falsas que solo asertan que la ruta responde 200.
