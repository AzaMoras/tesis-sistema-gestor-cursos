# Decisiones técnicas del equipo

## Confirmadas
- Producto: sistema web de gestión de cursos e inscripciones en línea, **solo virtual**.
- Stack objetivo: PHP 8.4, Laravel 13, Composer, MariaDB, Blade, HTML/CSS/JS.
- Organización: MVC con capa de servicios, middleware y Policies de autorización.
- Interfaz: español, responsive, accesibilidad básica, paleta verde y Manrope.
- Tema oscuro: incluir un botón de cambio visible fuera del menú de opciones.
- No usar emojis en ningún archivo de código o interfaz; utilizar iconos SVG.
- Registro público: recepción de solicitudes, no generación automática de cuentas administrativas.
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
- 2026-11-05: se reafirma que el producto es exclusivamente virtual. Eliminar de la planeación salas, espacios físicos, equipos de inventario y modalidades presenciales/mixtas. Mantener sesiones virtuales, horarios de instructores y enlaces de reuniones.

## Reglas de cambio
Antes de añadir migraciones o dependencias, inspeccionar lo que ya existe, explicar la necesidad, validar compatibilidad y obtener aprobación del equipo.
