# Sistema de Gestión de Cursos e Inscripciones en Línea

> **Guía de contexto y estilo del proyecto — propuesta para revisión (26/10/2026).**  
> Repositorio previsto: `https://github.com/AzaMoras/tesis-sistema-gestor-cursos`  
> Este archivo recoge decisiones conocidas de conversaciones y de la **documentación del paquete de migraciones de 32 entidades**. **No se pudo inspeccionar la carpeta `documentos/` completa de la laptop ni el código actualizado del repositorio**. Todo cambio futuro debe comprobar la estructura real antes de editar archivos.

## 1. Propósito

Sistema de información desarrollado como aplicación web para que diferentes instituciones gestionen capacitación **100 % virtual**: catálogo, solicitudes de acceso controlado, roles y permisos, ediciones y grupos, sesiones en línea, materiales, actividades, entregas, evaluaciones, inscripciones, listas de espera, pagos y constancias.

No es una escuela presencial. La tabla `sedes`, requerida por el profesor, representa datos administrativos o de infraestructura de alojamiento, **no salones donde se imparten cursos**.

## 2. Estado actual: confirmado vs. por implementar

| Área | Convención o decisión de diseño | Estado verificado |
|---|---|---|
| Lenguaje y framework | PHP 8.4, Laravel 13, Blade | Definido para el proyecto; comprobar versiones instaladas |
| BD | MariaDB, cuenta propia sin root | Definido; conexión real no comprobada aquí |
| Modelo | 32 entidades de negocio en propuesta, con `users` estándar y 31 tablas adicionales | Documentadas en paquete de migraciones; ejecución en MariaDB no verificada |
| Permisos | `users` ↔ `usuario_rol` ↔ `roles` ↔ `rol_permiso` ↔ `permisos`, ámbito institución | Modelo propuesto; falta código completo de autorización |
| Registro | Solicitud pública y revisión por personal autorizado, sin autorregistro | Regla de negocio definida; no implementada por estas vistas |
| Diseño | Cinco verdes + fuentes de Google + SVG, sin emojis | Guía de diseño acordada |
| Docker | Despliegue reproducible futuro | Propuesta; no incluido ni probado |
| Frontend | Componentes Blade reutilizables en vez de cinco vistas duplicadas | Plantillas de ejemplo incluidas en este paquete |

## 3. Identidad visual y estilo

### Paleta

| Token | HEX | Uso previsto |
|---|---|---|
| `--light-green` | `#BEEF9E` | Acentos destacados, progreso |
| `--muted-olive` | `#A6C36F` | Acciones secundarias, iconos |
| `--palm-leaf` | `#828C51` | Etiquetas y texto auxiliar |
| `--pine-teal` | `#335145` | Botones, navegación y superficies |
| `--evergreen` | `#1E352F` | Titulares, sidebar y texto principal |

Blanco, fondos neutros claros y grises suaves son colores de apoyo por accesibilidad.

### Tipografía e iconos

- **Manrope:** cuerpo, navegación, botones, formularios y dashboards.
- **Noto Serif:** títulos grandes de sitio público, cuando aporte jerarquía visual.
- Quicksand y Noto Serif Georgian estaban en el enlace inicial, pero no se consideran fuentes principales.
- **No usar emojis en interfaces.** Solo iconos SVG coherentes; incluye el componente `<x-icon>`.
- Interfaces adaptables a móvil, tablet y escritorio, contraste claro, estados de teclado y foco visibles.
- Componentes sobrios, esquinas ligeramente redondeadas, sin exceso de degradados ni elementos decorativos.
- Voz en español de México: mensajes claros, sin tecnicismos innecesarios ni texto genérico en inglés.
- `Aula Verde` es **nombre ilustrativo del prototipo**, NO nombre comercial confirmado del proyecto. Sustituir al elegir branding definitivo.

## 4. Arquitectura objetivo (Laravel)

```text
Usuario / navegador
    ↓
Rutas HTTP + auth + middleware de permisos
    ↓
Controladores + Form Requests + Policies
    ↓
Services (reglas de negocio y transacciones)
    ↓
Modelos Eloquent y relaciones
    ↓
MariaDB / almacenamiento privado / correo / pasarela de pagos
```

- Controladores: pequeños; coordinan la solicitud, no concentran cálculos de negocio.
- Services: validan cupos, lista de espera, pagos, aprobación, constancias y registro de cuentas.
- Policies o Gates: autorización por **acción, recurso e institución**. No basta esconder botones.
- Views: plantillas Blade reutilizadas entre roles; adaptar acciones por permisos.
- Form Requests: validan datos de entrada. No confiar en el formulario HTML para decidir permisos.
- Migraciones: fuente versionada de la estructura. No editar migraciones ejecutadas en entornos compartidos sin plan de compatibilidad.
- `inactivos`: historial de baja/restauración; `deleted_at`/`SoftDeletes` implementan baja lógica real.

## 5. Roles, permisos y comportamiento

Roles propuestos: **Administrador, Coordinador, Recepción, Instructor, Participante**.

- Administrador: instituciones, cuentas, revisión/activación de solicitudes, roles/permisos y auditoría.
- Coordinador: cursos, ediciones, grupos, instructores, módulos, actividades, criterios y sesiones.
- Recepción: requisitos, inscripciones, lista de espera y verificación manual de pagos si se autoriza.
- Instructor: grupos asignados, asistencias, revisión de entregas y evaluaciones.
- Participante: catálogo, inscripciones, pagos, aula virtual, entregas, exámenes, progreso y constancias.

Una misma vista puede mostrar distintas acciones según permisos. **Una ruta privada debe exigir autenticación y autorización real**, independientemente de los botones visibles.

## 6. Reglas centrales de negocio

1. **Registro controlado:** visitante envía solicitud y documentos; autoridad revisa; sistema crea o vincula cuenta con rol permitido y envía enlace de activación. Nunca mandar contraseñas por correo.
2. **Unicidad de inscripción:** un participante no puede tener dos inscripciones al mismo grupo.
3. **Cupo:** validación de cupo y reservas de pago con transacción/bloqueo para evitar carreras.
4. **Requisitos:** deben cumplirse antes de confirmar inscripción.
5. **Lista de espera:** orden, oferta, vencimiento y cancelación controlados.
6. **Pagos:** confirmados por pasarela con webhook verificado o por personal autorizado. Los correos son una notificación, no la prueba financiera.
7. **Aprendizaje:** modalidades autogestionadas y con acompañamiento; contenidos y actividades por módulos, entregas y evaluaciones.
8. **Constancias:** se generan solo si cumplen los criterios de asistencia, actividades, calificación y plazos cuando correspondan.
9. **Multiinstitución:** ninguna institución puede ver o modificar datos privados de otra sin autorización.
10. **Auditoría:** operaciones relevantes en bitácora; eliminación física de datos de negocio nunca por defecto.

## 7. Estructura de archivos del paquete Blade

```text
resources/views/
├── components/
│   └── icon.blade.php
├── layouts/
│   ├── app-base.blade.php
│   ├── sitio-publico.blade.php
│   ├── portal-publico.blade.php
│   ├── portal-privado.blade.php
│   └── portal-estudiante.blade.php
├── partials/
│   ├── navegacion-publica.blade.php
│   ├── navegacion-privada.blade.php
│   └── pie-publico.blade.php
└── tesis/
    ├── inicio.blade.php
    ├── catalogo.blade.php
    ├── estudiante/inicio.blade.php
    └── privado/inicio.blade.php
public/css/tesis-ui.css
routes/tesis_vistas_demo.php
```

### Función de las cuatro plantillas solicitadas

1. `sitio-publico.blade.php`: shell de landing pública: navegación, contenido y pie de página.
2. `portal-publico.blade.php`: variante del sitio público para catálogo, búsquedas y vistas abiertas.
3. `portal-privado.blade.php`: shell de áreas privadas, sidebar, encabezado de usuario y contenido interior.
4. `portal-estudiante.blade.php`: especialización del portal privado con navegación y contexto de aprendizaje.

`app-base.blade.php` evita duplicar HTML general, fuentes y CSS entre estas cuatro plantillas. `@yield` define espacios editables; `@section` los llena y `@include` o `<x-icon>` reutilizan componentes.

## 8. Integración en un Laravel existente (segura)

1. Confirmar estado del repositorio: `git status` y revisar archivos reales.
2. Crear rama `feature/layouts-blade` si se integrará como cambio nuevo.
3. **No sobrescribir** archivos existentes con el mismo nombre sin compararlos.
4. Copiar `resources/views/`, `public/css/tesis-ui.css` y, de forma opcional, `routes/tesis_vistas_demo.php`.
5. Para cargar demos desde `routes/web.php` añadir solo si no existe una ruta equivalente:

   ```php
   require __DIR__.'/tesis_vistas_demo.php';
   ```

6. Las rutas `/\_demo-tesis` (sin barra escapada en la URL) y `/\_demo-tesis/cursos` son públicas. Las rutas `/\_demo-tesis/estudiante` y `/\_demo-tesis/privado` llevan `auth` y requieren tener autenticación instalada. Agregar autorización por permisos antes de habilitarlas fuera de desarrollo.
7. Para previsualizar, correr `php artisan serve`; para ver las plantillas públicas visitar `http://127.0.0.1:8000/_demo-tesis`.
8. Después, sustituir datos de demostración por variables enviadas desde controladores (nunca consultas de BD directamente en las vistas).
9. Verificar con `php artisan view:clear` / `php artisan route:list` y pruebas manuales de móvil/teclado.
10. Crear commit tras revisar diferencias con `git diff`.

**Importante:** las vistas son prototipos, no CRUD funcional, pagos reales, sesiones ni permisos implementados. No dejar expuestos datos de ejemplo en producción.

## 9. Criterios para futuras modificaciones (equipo y agentes IA)

Antes de modificar el proyecto:

1. Leer este `README.md`, documentación interna vigente, migraciones, rutas, vistas y modelos realmente existentes.
2. Detectar qué piezas ya están implementadas y cuáles son prototipos; no asumir que la base de datos ya existe o está migrada.
3. Mantener Laravel 13, convenciones MVC y capa `app/Services` para lógica de negocio.
4. **Reutilizar layouts y componentes Blade**. No duplicar cinco veces la misma página para cambiar botones por rol.
5. Mantener los cinco colores exactos, Manrope, Noto Serif opcional y solo iconos SVG.
6. Elegir nombres coherentes con los usados en migraciones: `cursos`, `ediciones`, `grupos`, `inscripciones`, `pagos`, etc.; `users` y `password_reset_tokens` se mantienen como Laravel.
7. No inventar sedes físicas ni registro libre: todo es capacitación virtual con altas controladas.
8. No introducir dependencias ni plugins sin justificarlo; preferir herramientas ya presentes.
9. Jamás subir `.env`, claves, documentos personales, `vendor/` o `node_modules/`.
10. **Nunca ejecutar `migrate:fresh`, `migrate:reset`, `db:wipe` o `docker compose down -v`** en BD con datos importantes sin respaldo y autorización expresa.
11. Describir los archivos que se crean/cambian, las decisiones tomadas y los comandos necesarios para Windows PowerShell/Antigravity.
12. Añadir pruebas de permisos, duplicados, cupo, pagos y constancias antes de integrar a `main`.

## 10. Documentos pendientes de inspección

Para pasar de esta guía propuesta al README **definitivo** hace falta revisar la carpeta de documentos actual, los prototipos reales exportados de Stitch, los modelos, los controladores y las rutas vigentes. Este README no certifica haberlos inspeccionado ni garantiza que el estado actual de GitHub coincida con el paquete de migraciones disponible.

## 11. Fuentes internas revisadas

- Paquete preparado anteriormente: `migraciones_tesis_laravel13_32_tablas.zip`, especialmente `README.md`, `docs/RELACIONES.md` y `docs/DECISIONES_PENDIENTES.md`.
- Decisiones funcionales y visuales confirmadas durante el diseño conversado del sistema.
