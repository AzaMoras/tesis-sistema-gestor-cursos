# Aula Verde — Análisis de impacto para MariaDB y Laravel

**AVISO:** no es una migración ejecutable ni una afirmación sobre el esquema actual. Requiere comparar los nombres/columnas reales del repositorio antes de crear código. La auditoría previa identificó 35 migraciones, 32 modelos, tablas de cursos, ediciones, grupos, evaluaciones, inscripciones, usuarios, solicitudes, constancias y asignaciones. Evitar duplicar lo que ya existe.

## 1. Matriz de impacto

| Tema | Tablas/modelos existentes a inspeccionar | Extensión mínima probable | Servicios y controladores |
|---|---|---|---|
| Registro público | `users`, `participantes`, roles, usuario_rol, solicitudes y estado de cuenta | Aprovechar `email_verified_at`, estado; no añadir duplicados; adaptar perfil público y relación institucional | Nuevo controlador/servicio de registro sin privilegios; evento `Registered`, verificación y políticas |
| Prerrequisitos | `requisitos`, `cursos`, acreditaciones, evidencias | `tipo`, referencia a curso/módulo acreditado o evidencias privadas y estado de verificación si faltan | `RequisitoService` y adaptaciones a `InscripcionService` |
| Vigencia | `ediciones`, `inscripciones` | Configuración de duración/tipo de vigencia en edición; `acceso_inicia_at`, `acceso_vence_at`, `tipo_prorroga`, estado/política según esquema | `VigenciaAccesoService`, política de cursos y tarea de avisos |
| Prórrogas | `inscripciones`, `pagos`, bitácora | Historial `prorrogas_acceso` si no existe; relacionar quién autorizó, vencimiento anterior/nuevo, motivo, precio/pago opcional | `ProrrogaService` y autorización por institución |
| Exámenes | `evaluaciones`, `examenes`, `intentos`, `preguntas`, `respuestas` | Tipo formativo/final, intentos por ciclo, límite de tiempo, umbral, política de nota y versionado; nuevos campos solo si ausentes | `ExamenService`, `CalificacionService`, validación de disponibilidad |
| Ciclos de recuperación | Intentos y completados de módulo | Nueva entidad `ciclos_evaluacion` o equivalente con `numero_ciclo`, estados, apertura/cierre, evidencias de reforzamiento | `RecuperacionService` con transacciones e idempotencia |
| Progreso versión | `modulos`, `contenidos`, `actividades`, resultados | Tabla/versiones estables o snapshot de requisitos al evaluar; no reescribir notas previas | `VersionCursoService` / lógica de equivalencias |
| Mentoría | instructores, grupos, `sesiones`, materiales/videos, entregas | Aprovechar sesiones con URL virtual; recursos multimedia con origen, propietario y metadatos si faltan | `SesionVirtualService`, `MaterialService`, feedback y permisos |
| Constancias | `constancias`, servicios actuales | Folio, criterio cumplido, versión/fecha de emisión y verificación según esquema | Completar `ConstanciaService`, no emitir antes de cálculo definitivo |

## 2. Principios de migración

1. Inspeccionar cada tabla/modelo y datos existentes (`php artisan migrate:status`, `Schema::hasColumn` solo como herramienta de análisis, `SHOW CREATE TABLE`, etc.).
2. Crear **migraciones nuevas** y aditivas. No editar migraciones ya ejecutadas en otros equipos ni ejecutar `migrate:fresh` sobre MariaDB de desarrollo.
3. Hacer copia de seguridad de la BD antes de migrar. Confirmar que el respaldo pueda restaurarse.
4. Añadir columnas nullable/default donde existan filas antiguas, backfill controlado y solo después índices/constraints compatibles; preferir normalización y claves únicas estables.
5. No eliminar ni alterar documentos históricos durante la adaptación a autorregistro; desactivar el requisito general de documentos en la lógica y establecer control de retención.
6. Crear rollback que deshaga únicamente los cambios nuevos y nunca borre historia previa.
7. Las pruebas automatizadas destructivas deben ejecutarse únicamente contra SQLite temporal o una BD aislada designada, nunca contra la BD de desarrollo.
8. Tests de concurrencia/migraciones con MariaDB requieren base **exclusiva de pruebas**, no el servidor compartido de compañeros.

## 3. Contrato de clases y operaciones (adaptar al esquema actual)

### `RegistroParticipanteService`

- `registrar(datos_minimos)`: valida, normaliza email, crea cuenta con hash y estado compatible, perfil de participante sin privilegios, envía verificación (o registra evento) y audita. No asigna administrador/coordinador/recepción/instructor.
- Inscripción institucional: resolver institución a partir de la edición/curso solicitado o una invitación legítima; no confiar en un `institution_id` arbitrario del formulario.
- Revisar el funcionamiento actual del middleware que exige una institución inequívoca: el usuario público no debe obtener una institución privilegiada por defecto.

### `VigenciaAccesoService`

- `obtenerVencimiento(inscripcion)`; `puedeIniciarActividad(inscripcion, instante_servidor)`; `solicitarProrroga(...)`; `aplicarProrroga(...)`; `registrarAviso(...)`.
- La comprobación de acceso se hace en cada acción sensible, no solo al procesar una tarea programada.
- Protección contra repetición de pagos/prórrogas, con transacción y control de concurrencia.

### `EvaluacionFinalService`

- `abrirIntento(participante, evaluacion, ciclo)`: verifica inscripción/estado/cupo temporal, módulo completado, acceso vigente, permisos y contador de intentos.
- `entregarIntento(intento, respuestas, instante_servidor)`: valida titularidad, vencimiento de intento, idempotencia y cálculo/pendientes de revisión humana.
- `cerrarCicloSiAgotaIntentos(...)`: transición a recuperación, nunca eliminar historia.
- `iniciarNuevoCiclo(...)`: solo tras comprobar nuevamente las tareas de reforzamiento obligatorias posteriores al cierre anterior.
- Registrar transiciones e intentos de forma transaccional, imponiendo unicidad donde proceda.

### `InstructorMentorService`

- `programarSesion(grupo_asignado, fecha_hora_zona, enlace)`; `publicarMaterial(...)`; `registrarFeedback(...)`.
- Asegurar vínculo a institución/curso/grupo, permisos y almacenamiento privado apropiado. Prohibir ediciones de evaluaciones publicadas que cambien los resultados históricos sin nueva versión.

## 4. Invariantes de seguridad y datos

- No autorizar por la presencia de botones. Autorización en cada operación web por usuario, rol, institución, recurso y estado.
- No cambiar automáticamente el rol a instructor/admin si el registro trae un parámetro malicioso.
- Correo único es identificación de cuenta, NO validación de identidad civil ni garantía anti-multicuenta.
- Plazos y estados se calculan desde reloj del servidor con fechas UTC almacenadas y zona horaria de presentación configurable.
- Limitar intentos de login y endpoints de inicio/entrega de exámenes; la política de exámenes formativos ilimitados no implica aceptar spam ilimitado.
- Versionar parámetros de examen: cambiar un límite no altera intentos ya iniciados.
- Mantener evidencia de `curso_version` o criterios vigentes por intento para evitar recalcular calificaciones históricas con reglas nuevas.
- Completar la configuración `ReglasOperacion` antes de integrar operaciones reales: los estados de apertura, finales y reservas deben quedar definidos.

## 5. Prueba de integración previa al despliegue

Revisar compatibilidad con Laravel 13, MariaDB instalado, `phpunit.xml`, `tests/TestCase.php`, `AppServiceProvider` y middleware. Inspeccionar `AGENTS.md` y los modelos reales antes de generar cualquier archivo. Registrar en documentación los cambios de base de datos, su propósito, reversibilidad y casos probados.
