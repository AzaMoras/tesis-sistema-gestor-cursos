# Auditoría y políticas académicas — Fase 00

Fecha: 10/11/2026. Copia: `C:/Users/ching/tesis-sistema-gestor-cursos`.
Rama inspeccionada y conservada: `feature/politicas-academicas-v2`.

Esta entrega prepara documentación, vigencia y reglas académicas reutilizables.
No entrega login/logout, autorregistro, interfaces académicas ni un motor de exámenes.
Las políticas aprobadas son [decisiones v2](DECISIONES_PRODUCTO_V2.md),
[reglas v2](REGLAS_NEGOCIO_V2.md) y [alcance v2](VISION_MISION_Y_ALCANCE_V2.md).
La solicitud de Fase 00 autoriza fusionarlas en la documentación central.

## Evidencia y alcance de inspección

- Git inicial: siete documentos nuevos sin seguimiento, sin modificaciones de archivos
  versionados. Se preservaron los siete documentos aportados sin sobrescribirlos.
- PHP CLI 8.4.25; Laravel Framework 13.36.0.
- `php artisan migrate:status --no-ansi`: código 0, las 35 migraciones previas
  figuran como `[1] Ran`. Esta consulta lee el registro; no aplica migraciones.
- Lectura de metadatos: driver `mariadb`, versión `12.3.3-MariaDB`; columnas
  de ediciones, inscripciones, actividades, entregas, evaluaciones, módulos y
  sesiones compatibles con las migraciones locales. No se consultaron datos personales.
- Se inspeccionaron AGENTS, README, documentos normativos, flujo y plan, modelos
  implicados, migraciones, servicios y soporte, User, AppServiceProvider, rutas,
  tests y configuración de aislamiento. No se leyó ni copió `.env`.
- No hay respaldo/restauración verificado en esta sesión: ninguna migración se
  aplica a MariaDB. Tampoco se certifican concurrencia ni datos históricos reales.

## Mapa de esquema y compatibilidad

| Área | Estructura real | Diferencia con v2 / acción |
|---|---|---|
| Cuentas | `users.email` único, `email_verified_at`, `estado_cuenta`; `participantes.user_id` único; `usuario_rol` institucional | No duplicar identidad ni roles. User aún no implementa `MustVerifyEmail`; rutas, correo y contexto seguro de autorregistro pendientes |
| Solicitudes | `solicitudes_registro`, `documentos_solicitud`, servicio de revisión y aprovisionamiento pendiente | Conservar flujo especial/interno y sus evidencias; deja de ser requisito universal para participantes |
| Prerrequisitos | `requisitos`, `curso_requisito`, `participante_requisito` con revisión/evidencia | Curso sin requisitos no exige documentos; falta enlazar acreditaciones internas y versiones |
| Vigencia | `ediciones.fecha_inicio/fecha_fin`, `inscripciones.reserva_vence_at` y `fecha_inscripcion` | Fechas de edición y reserva no equivalen a acceso académico; se proponen siete columnas independientes |
| Modalidad | `ediciones.modalidad` predetermina `acompanada` | v2 dice `acompanado`; conservar clave actual, adaptar vocabulario/validación al integrar formularios sin alterar registros |
| Umbral | `ediciones.calificacion_minima` predetermina 70 | 80/100 en v2 es ilustrativo, no un nuevo umbral universal; no cambiar calificaciones ni migraciones antiguas |
| Unidades | `modulos_curso` con `obligatorio`, `actividades.modulo_id` anulable | No crear otra tabla de módulos. Falta acreditación estable por inscripción/unidad/versión |
| Evaluación | `actividades.tipo`, `intentos_maximos` (predeterminado 1), `plazo_dias`, `configuracion_json`; `entregas.numero_intento`; `evaluaciones` califica una entrega | No hay definición persistida de examen/ciclo ni inicio/límite de intento. No convertir notas en exámenes ni cambiar el límite antiguo globalmente |
| Unicidad | `entrega_act_ins_int_uq`: actividad/inscripción/número de intento | Reiniciar a 1 en otro ciclo colisionaría. Mantenerla; diseñar registro de intentos por ciclo separado |
| Mentoría | `grupo_instructor`, `sesiones.inicio_at/fin_at/enlace_virtual`, `materiales_curso` | Reutilizar asignaciones y enlaces. Calificación ya comprueba grupo; nuevos métodos de vigencia también. Publicación y zona horaria de sesiones pendientes |
| Historial | Entregas/notas persistidas, restricciones FK, bajas lógicas en catálogos | No borrar progreso al vencer ni reescribir notas. Falta snapshot/versionado académico completo |
| Constancias | Inscripción única, folio y código únicos, PDF obligatorio; servicio diagnostica elegibilidad | Emisión real pendiente; no sustituir PDF por ruta ficticia ni anunciar reconocimiento oficial |
| Configuración | `ReglasOperacion` exige estados/apertura/reserva/cupo explícitos; provider vacío | v2 no decide esos valores. No usar valores de fixtures para producción ni modificar provider para inventarlos |

## Esquema propuesto, no aplicado a MariaDB

Archivo: `database/migrations/2026_11_10_000001_add_vigencia_to_ediciones_and_inscripciones.php`.

| Tabla | Columnas nuevas | Semántica |
|---|---|---|
| `ediciones` | `tipo_vigencia VARCHAR(25) NULL`, `duracion_acceso_dias SMALLINT UNSIGNED NULL`, `prorroga_gratuita_dias SMALLINT UNSIGNED NULL` | Política resuelta por servidor: indefinida o limitada, dentro de restricciones institucionales; nulo indica decisión pendiente |
| `inscripciones` | `acceso_inicia_at TIMESTAMP NULL`, `acceso_vence_at TIMESTAMP NULL`, `prorroga_gratuita_dias SMALLINT UNSIGNED NULL`, `prorroga_gratuita_aplicada_at TIMESTAMP NULL` | Instantánea activada, vencimiento y uso único de prórroga; inicio nulo significa no activado, inicio presente con vencimiento nulo significa indefinido |

SQL compilado mediante `Connection::pretend()` con una conexión ficticia de
driver MariaDB, sin acceder a la conexión local ni ejecutar sentencias:

```sql
alter table `ediciones` add `tipo_vigencia` varchar(25) null;
alter table `ediciones` add `duracion_acceso_dias` smallint unsigned null;
alter table `ediciones` add `prorroga_gratuita_dias` smallint unsigned null;
alter table `inscripciones` add `acceso_inicia_at` timestamp null;
alter table `inscripciones` add `acceso_vence_at` timestamp null;
alter table `inscripciones` add `prorroga_gratuita_dias` smallint unsigned null;
alter table `inscripciones` add `prorroga_gratuita_aplicada_at` timestamp null;
```

No se añaden índices, FK o restricciones sobre datos antiguos. La validación
del dominio rechaza tipos desconocidos, días inválidos y prórrogas en acceso
permanente. No hay backfill: ninguna fila antigua se reclasifica automáticamente.
Los campos nuevos no son asignables masivamente. Los casts nuevos no consultan
columnas ausentes al ejecutar los servicios previos.

`down()` elimina únicamente las siete columnas nuevas; conserva tablas, filas y
columnas originales. Tras usar vigencia, revertir perdería sus fechas y configuración:
respaldarlas y revisar dependencia funcional antes de un rollback. El DDL de
MariaDB puede confirmar parcialmente y tomar bloqueos; el éxito de SQLite y la
compilación SQL no prueban duración de bloqueo, atomicidad o rollback en MariaDB.

Antes de aplicar: respaldo restaurable, revisión de `SHOW CREATE TABLE` e índices,
ensayo sobre restauración aislada con datos previos y verificación de privilegios,
zona UTC, migraciones, rollback y concurrencia en MariaDB de pruebas. El equipo
ejecuta y autoriza ese despliegue; no ejecutar `migrate:fresh` sobre desarrollo.

## Componentes incorporados

- `PoliticaVigencia`: duración validada y cálculo inmutable en UTC. La fábrica
  gratuita limitada ofrece 30 días; la prórroga se habilita expresamente con 14
  días como propuesta predeterminada. No aplicar esa fábrica a todo curso gratuito
  ni inferir acceso indefinido a partir de una política ausente.
- `VigenciaAccesoService::activar`: permiso `inscripciones.gestionar`, cuenta,
  institución y recurso recargados, inscripción confirmada, transacción y bloqueos
  del protocolo edición → grupo → inscripción. Guarda instantánea y bitácora.
  Repetir activación no renueva el plazo ni reemplaza la política adquirida.
- `exigirPuedeIniciar`: propietario o gestor autorizado, control de asignación de
  instructor y reloj de servidor. Rechaza acceso no activado/futuro/vencido,
  incluso exactamente al vencer. En una transacción bloquea los recursos; debe
  llamarse dentro de cada futura escritura de intento/entrega, no solo desde cron.
  Las consultas de historial y la revisión de entregas previas conservan su flujo.
- `aplicarProrrogaGratuita`: solicitud explícita del propietario o gestor autorizado,
  una vez, días capturados al activar y auditoría con vencimientos anterior/nuevo.
  No toca pagos, notas, entregas ni constancias. Se suma al mayor entre vencimiento
  anterior y aceptación actual. Esta decisión de borde queda para revisión antes de
  exponer HTTP; extensiones administrativas justificadas/pagadas aún no se implementan.
- `PoliticaEvaluacion`: reglas puras con precedencia institución impuesta → curso →
  predeterminados, tres intentos por ciclo y tiempo ilustrativo configurable de 30
  minutos. Formativos ilimitados; finales agotados sin aprobar requieren reforzamiento.
  Solo admite nuevo ciclo si todas las actividades obligatorias tienen completados
  posteriores al cierre y no futuros. No considera trabajos del ciclo anterior.
  Sin actividades de recuperación definidas no autoriza un ciclo vacío.

Los servicios no tienen nuevas rutas ni UI. Las reglas puras reciben información
de servidor y no validan por sí solas titularidad, evidencia ni conteos en BD.
No cambian el servicio previo de evaluación ni sus reglas de entregas antiguas.

## Diseño pendiente: ciclos, reforzamiento y versiones

No se crean tablas especulativas antes de definir el flujo de exámenes. Propuesta
de integración compatible, aún sin migración ejecutable:

1. Definición de examen asociada a actividad/unidad real, tipo, umbral, política de
   nota y versión inmutable. Capturar límites resueltos y preguntas/criterios aplicados.
2. Ciclo por inscripción/examen/versión con número positivo, apertura/cierre y estado;
   unicidad de esa combinación con número. Crear otro ciclo sin borrar el anterior.
3. Intentos por ciclo con ordinal único, inicio/límite/envío UTC, resultado y clave
   idempotente de solicitud. FK restrictivas. No eliminar ni reemplazar la unicidad
   de `entregas`; vincular evidencia cuando corresponda sin reiniciar su contador global.
4. Evidencias de reforzamiento por ciclo cerrado/actividad obligatoria/versionada,
   fecha de completado y revisión. Validar misma unidad, titularidad y completado
   posterior al cierre; no aceptar un booleano de formulario como evidencia.
5. Acreditaciones de módulo por inscripción/versión; aprobar otro módulo o abrir
   recuperación nunca modifica una acreditación anterior. Cambios sustantivos de
   contenido necesitan equivalencias o actividad complementaria explícita.
6. Transacciones que bloqueen inscripción, ciclo e intentos y vuelvan a comprobar
   acceso, número consumido, estado e idempotencia. Ensayar carreras reales sobre
   MariaDB exclusiva de pruebas antes de afirmar protección de concurrencia.

Separar el almacenamiento de política institucional de la configuración de
curso/edición. El resolver puro admite parámetros institucionales impuestos,
pero faltan persistencia, límites mínimos/máximos, permisos de edición y publicación.
No pasar datos de navegador a `ReglasOperacion` ni a resolvers como política confiable.

## Trazabilidad de decisiones

Implementado significa solo el componente indicado, con pruebas, sin implicar HTTP.
Parcial significa que faltan integración, persistencia o partes de la regla.

| Decisión | Modelo/servicio o documentación | Estado |
|---|---|---|
| DP-01 autorregistro | Documentación central unificada; User y solicitudes conservados | Pendiente de registro/verificación HTTP |
| DP-02 identidad | ID y correo único existentes; no prueba identidad civil | Parcial: falta normalización general de autorregistro |
| DP-03 prerrequisitos | Relaciones existentes e InscripcionService | Parcial: falta acreditación interna versionada y UI de evidencia externa |
| DP-04 modalidad | Edicion, Grupo, Sesion; alcance exclusivamente virtual | Parcial: faltan flujos de ambas modalidades |
| DP-05 vigencia | Migración, PoliticaVigencia, VigenciaAccesoService | Parcial: componente implementado; MariaDB/integración institucional y HTTP pendientes |
| DP-06 vencimiento | Denegación por reloj sin mutaciones; consulta de resultados preservada | Parcial: guarda implementada, falta conectar nuevas acciones |
| DP-07 pago explícito | Prórroga gratuita no crea pagos; PagoService conservado | Parcial: extensión pagada pendiente |
| DP-08 práctica | PoliticaEvaluacion | Parcial: regla pura implementada, motor formativo pendiente |
| DP-09 finales | PoliticaEvaluacion y diseño de intentos/ciclos | Parcial: reglas puras probadas, persistencia y operaciones pendientes |
| DP-10 recuperación | Regla pura de reforzamiento posterior al cierre | Parcial: falta verificar/persistir evidencia y apertura de ciclo |
| DP-11 acreditación | ConstanciaService conserva diagnóstico no elegible | Pendiente de cálculo definitivo y emisión |
| DP-12 mentoría | AutorizacionInstitucional y evaluación asignada | Parcial: publicación de recursos y programación pendientes |
| DP-13 versiones | Historial preservado al vencer y diseño documentado | Pendiente de versionado/equivalencias |
| DP-14 IA | Fuera de primera etapa | Pendiente, sin dependencia nueva |
| DP-15 seguridad | Middleware, alcance institucional, propietario y asignación en servicios | Parcial: faltan autorización/verificación en futuros endpoints |

## Cobertura de aceptación

| Casos | Evidencia automatizada | Límite / estado |
|---|---|---|
| TA-01–04 | Suite previa de usuarios/solicitudes no equivale a autorregistro | Pendiente de flujo HTTP nuevo y verificación |
| TA-05, TA-07 | InscripcionServiceTest y requisitos revisados existentes | Parcial; registro general y evidencias por HTTP pendientes |
| TA-06 | Diseño de acreditación interna | Pendiente |
| TA-08–13 | PoliticasAcademicasTest; VigenciaAccesoServiceTest: UTC, activación, acceso, vencimiento, prórroga, permanencia y ausencia de cargos | Implementado en componente; endpoints e integración pendientes |
| TA-14–19 | PoliticasAcademicasTest: formativos, contador final, tiempo y reforzamiento posterior | Parcial: prueba reglas puras; no apertura/envío de intentos ni ciclos persistidos |
| TA-20–21 | No hay escrituras académicas nuevas; prueba de vigencia conserva notas/entregas | Pendiente de prueba de recuperación con historial real por módulo |
| TA-22–23 | ConstanciaServiceTest y diagnóstico conservador previo | Parcial: no emisión, falta acreditación definitiva por módulo/modalidad |
| TA-24 | EvaluacionServiceTest existente y nuevo control de instructor en vigencia | Parcial: deniega grupos ajenos; faltan operaciones de publicación |
| TA-25 | Sesion y asignaciones existentes | Pendiente de servicio/HTTP de programación y publicación |
| TA-26–27 | Diseño de versión inmutable; evaluación manual previa rechaza sobreescritura | Pendiente de versionado completo |
| TA-28 | AutorizacionInstitucionalTest, EvaluacionServiceTest y VigenciaAccesoServiceTest | Parcial: aislamiento en servicios; futura manipulación de URL sin endpoints aún no verificable |
| TA-29 | No ejecutado con carreras reales en MariaDB | Pendiente; SQLite no acredita concurrencia MariaDB |
| TA-30 | Sin tarea de notificaciones | Pendiente |
| Esquema | VigenciaMigrationTest: filas previas, columnas anulables y rollback limitado | Probado solo en SQLite :memory: |

## Verificaciones

`phpunit.xml` fuerza `APP_ENV=testing`, `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`
y `DB_URL` vacío. `tests/TestCase.php` rechaza configuración cacheada, comprueba
el entorno antes de RefreshDatabase y elimina conexiones externas. La prueba
TestingEnvironmentTest verifica el driver real y que solo quede SQLite.

- `php artisan test --no-ansi`: código 1. Symfony Process informa que el directorio
  de trabajo no existe en su subproceso; no es un resultado verde de la suite.
- Primera ejecución directa sin desactivar caché: PHPUnit recorrió los tests,
  pero falló al persistir su caché por el mismo problema de reconocimiento de ruta.
- Alternativa reproducible: `php vendor/phpunit/phpunit/phpunit --do-not-cache-result --colors=never`.
  Resultado final literal: `OK (148 tests, 543 assertions)`, código 0.
  Son 27 casos nuevos sobre los 121 previos; solo SQLite `:memory:`.
- `php artisan route:list --no-ansi`: código 0, 8 rutas; no hay login, logout,
  registro ni endpoints nuevos de vigencia/exámenes. Incluye rutas automáticas de
  storage y salud; su presencia no certifica flujos académicos.
- Pint sobre los siete PHP nuevos: `{"tool":"pint","result":"passed"}`, código 0.
  No se reformatearon modelos antiguos completos.
- `php -l`: `No syntax errors detected` en los siete archivos PHP nuevos y los
  dos modelos modificados, código 0.
- `git diff --check`: sin salida, código 0.
- `migrate:status` final: la nueva migración figura como `Pending`; las 35 previas
  conservan `Ran`. No se ejecutó migración ni rollback sobre MariaDB.

Estado final de `git status --short` (los siete documentos aportados ya estaban
sin seguimiento al comenzar):

```text
 M AGENTS.md
 M README.md
 M app/Models/Edicion.php
 M app/Models/Inscripcion.php
 M app/Services/README.md
 M docs/CONTEXTO_PROYECTO.md
 M docs/DECISIONES_TECNICAS.md
 M docs/PLAN_NOVIEMBRE_2026.md
 M docs/REGLAS_NEGOCIO.md
?? app/Services/Soporte/PoliticaEvaluacion.php
?? app/Services/Soporte/PoliticaVigencia.php
?? app/Services/VigenciaAccesoService.php
?? database/migrations/2026_11_10_000001_add_vigencia_to_ediciones_and_inscripciones.php
?? docs/AUDITORIA_FASE_00.md
?? docs/CRITERIOS_ACEPTACION_Y_PRUEBAS.md
?? docs/DECISIONES_PRODUCTO_V2.md
?? docs/IMPACTO_BD_Y_ARQUITECTURA.md
?? docs/IMPACTO_MEMORIA_LATEX.md
?? docs/PLAN_DE_IMPLANTACION.md
?? docs/REGLAS_NEGOCIO_V2.md
?? docs/VISION_MISION_Y_ALCANCE_V2.md
?? tests/Feature/Services/VigenciaAccesoServiceTest.php
?? tests/Feature/VigenciaMigrationTest.php
?? tests/Unit/Services/PoliticasAcademicasTest.php
```

## Archivos y entrega para revisión

| Acción | Archivos |
|---|---|
| Modificados | `AGENTS.md`, `README.md`, `docs/CONTEXTO_PROYECTO.md`, `docs/REGLAS_NEGOCIO.md`, `docs/DECISIONES_TECNICAS.md`, `docs/PLAN_NOVIEMBRE_2026.md` |
| Modificados | `app/Models/Edicion.php`, `app/Models/Inscripcion.php`, `app/Services/README.md` |
| Nuevos | Este informe; `app/Services/Soporte/PoliticaVigencia.php`, `app/Services/Soporte/PoliticaEvaluacion.php`, `app/Services/VigenciaAccesoService.php` |
| Nueva migración | `database/migrations/2026_11_10_000001_add_vigencia_to_ediciones_and_inscripciones.php` |
| Nuevas pruebas | `tests/Unit/Services/PoliticasAcademicasTest.php`, `tests/Feature/Services/VigenciaAccesoServiceTest.php`, `tests/Feature/VigenciaMigrationTest.php` |
| Aportados previamente, preservados | Los siete documentos sin seguimiento: criterios, decisiones v2, impacto BD, impacto Memoria LaTeX, implantación, reglas v2 y visión/misión |

## Compatibilidad con login/logout y ruta de commits del equipo

No se modifican autenticación, sesiones, contraseñas, roles existentes, provider,
middleware ni rutas. Se puede continuar con login/logout nativos sin esperar
interfaces de pagos o exámenes. Luego implementar autorregistro con contexto
institucional resuelto en servidor y verificación de correo antes de funciones
privadas; no asignar una institución por defecto ni aceptar privilegios del formulario.

El equipo puede revisar y preparar dos commits separados en esta misma rama,
sin incluir archivos ajenos ni el repositorio LaTeX:

1. `docs: integrar politicas academicas v2 y auditoria de Fase 00` — documentación
   central, siete fuentes aportadas y este informe con resultados reales.
2. `feat(domain): preparar vigencia y reglas de evaluacion v2` — migración,
   casts, servicios/reglas, README de servicios y pruebas.

No se ejecutaron add/commit/push/merge/reset ni cambios de rama. Las instrucciones
de actualización de ramas de otros documentos son guía para el equipo, no permiso
de ejecutarlas automáticamente durante esta tarea. La Memoria y sus objetivos
aprobados no se modificaron.
