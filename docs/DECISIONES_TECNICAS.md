# Decisiones técnicas del equipo

## Confirmadas
- Producto: sistema web de gestión de cursos e inscripciones en línea, **solo virtual**.
- Stack objetivo: PHP 8.4, Laravel 13, Composer, MariaDB, Blade, HTML/CSS/JS.
- Organización: MVC con capa de servicios, middleware y Policies de autorización.
- Interfaz: español, responsive, accesibilidad básica, paleta verde y Manrope.
- Tema oscuro: incluir un botón de cambio visible fuera del menú de opciones.
- No usar emojis en ningún archivo de código o interfaz; utilizar iconos SVG.
- Registro público: autorregistro de participantes con correo verificado y sin privilegios; administración interna de personal y solicitudes especiales. No exigir INE/CURP general.
- Un conjunto de vistas compartidas, habilitando opciones según rol y permisos.
- Sedes: información administrativa o de infraestructura; no sedes educativas físicas.
- Documentación: Memoria en LaTeX versionada con Git.

## Por verificar en el código existente
- Nombres reales de tablas, modelos y estados; no asumir que coinciden con este documento.
- Configuración de MariaDB, versión instalada de Laravel y configuración de pruebas.
- Flujos actuales de inscripciones, lista de espera, pagos y constancias.
- Servicios ya creados, pruebas existentes y recursos Blade reutilizables.
- Políticas multiinstitución y disponibilidad de correo real.

## Cambios de alcance
- 2026-11-10: se incorporan [decisiones de producto v2](DECISIONES_PRODUCTO_V2.md). La [auditoría de Fase 00](AUDITORIA_FASE_00.md) detalla esquema real, propuestas y cobertura. El nuevo servicio de vigencia requiere migración y configuración explícitas; no está conectado a rutas. Las reglas de evaluación son componentes sin persistencia de ciclos; no habilitan exámenes.
- No reutilizar `reserva_vence_at` como plazo académico ni tratar `evaluaciones` como definiciones de examen: son calificaciones de entregas. `ediciones.modalidad` usa actualmente `acompanada`; el término funcional v2 `acompanado` no autoriza reescribir datos sin adaptación.
- Conservar `ReglasOperacion` sin inventar estados finales/apertura, duración de reserva ni significado de cupo nulo: v2 no define esos valores. No enlazar configuración de producción en `AppServiceProvider` hasta acordarlos.
- Los campos nuevos de vigencia son anulables para conservar registros previos; inicio nulo significa pendiente de activar, no acceso perpetuo. La activación toma una instantánea de edición, sin backfill automático ni cambio por futuras ediciones de política. Fechas UTC y reloj de servidor.
- La prórroga opcional requiere invocación explícita, una vez por inscripción y auditoría. Se añaden los días al mayor entre vencimiento anterior y aceptación en servidor, para no consumir una prórroga tardía en días ya transcurridos. Este criterio técnico queda visible para revisión del equipo antes de exposición HTTP.
- 2026-11-05: se reafirma que el producto es exclusivamente virtual. Eliminar de la planeación salas, espacios físicos, equipos de inventario y modalidades presenciales/mixtas. Mantener sesiones virtuales, horarios de instructores y enlaces de reuniones.

## Reglas de cambio
Antes de añadir migraciones o dependencias, inspeccionar lo que ya existe, explicar la necesidad, validar compatibilidad y obtener aprobación del equipo.
