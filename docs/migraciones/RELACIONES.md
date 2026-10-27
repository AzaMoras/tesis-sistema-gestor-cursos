# Relaciones clave del modelo

- `sedes (1) → instituciones (N opcional)` mediante `instituciones.sede_id`.
- `users (N) ↔ roles (N)` mediante `usuario_rol` + `institucion_id`.
- `roles (N) ↔ permisos (N)` mediante `rol_permiso`.
- `users (1) → participantes (0..1)`; `users (1) → instructores (0..1)`.
- `instituciones (1) → solicitudes_registro (N)` y `solicitudes_registro (1) → documentos_solicitud (N)`.
- `instituciones (1) → cursos (N)`; `categorias_curso (1) → cursos (N)`; `cursos (1) → ediciones (N)`; `ediciones (1) → grupos (N)`.
- `grupos (N) ↔ instructores (N)` con `grupo_instructor`; `grupos (1) → sesiones (N)`.
- `cursos (N) ↔ requisitos (N)` mediante `curso_requisito`; `participantes (N) ↔ requisitos (N)` mediante `participante_requisito`.
- `participantes (N) ↔ grupos (N)` mediante `inscripciones`; se garantiza una inscripción por par participante-grupo.
- `lista_espera` conecta participante y grupo; registra posición/oferta simplificada.
- `cursos (1) → modulos_curso (N) → materiales_curso (N)`; `actividades` referencia cursos y opcionalmente módulo y grupo.
- `inscripciones (1) → entregas (N)` y `actividades (1) → entregas (N)`; `entregas (1) → evaluaciones (0..1)`.
- `inscripciones (1) → asistencias (N)`; `sesiones (1) → asistencias (N)`.
- `inscripciones (1) → constancias (0..1)`.
- `inscripciones (1) → pagos (N)`.
- `bitacora` e `inactivos` usan par entidad/registro_id como referencia lógica, no FK polimórfica.
