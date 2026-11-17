# Impacto en la Memoria LaTeX: capítulos 1 y 2

**Fecha:** 2026-11-10. No usar este archivo para sustituir automáticamente los capítulos sin comparar contra su versión actual revisada por el profesor.

## Capítulo 1: Marco de Referencia

- **Introducción:** presentar Aula Verde como plataforma de gestión de cursos 100 % virtuales; público inicial de centros de capacitación pequeños. No prometer que es más barata sin estudio de costos.
- **Antecedentes:** NetAcad, Coursera, Moodle, Open edX y Capacítate para el Empleo pueden ilustrar modalidades y funcionalidades existentes; documentar con fuentes verificables y no copiar diseño ni texto.
- **Objetivo general:** preservar literalmente el aprobado.
- **Seis objetivos específicos:** preservar literalmente los aprobados.
- **Alcance:** actualizar la parte que implica administración de usuarios para distinguir autorregistro público de permisos internos; incluir vigencia, evaluación con recuperación, mentoría y conservación de progreso como funciones previstas cuando sea pertinente.
- **Limitación:** corregir la frase anterior `El registro de cuentas de usuario estará restringido al personal autorizado...` porque contradice el nuevo alcance. Propuesta de reemplazo: `El sistema permitirá el registro directo de participantes mediante información básica y verificación del correo electrónico. La asignación de roles administrativos y de instructor permanecerá restringida al personal autorizado. Los requisitos académicos específicos se verificarán únicamente cuando sean pertinentes para un curso determinado.` Conservar íntegros los demás límites sobre virtualidad, infraestructura y pruebas salvo contradicción directa.
- **Problemática:** aclarar que el proyecto no busca verificar identidad civil mediante documentos oficiales; problema principal: organizar cursos, inscripciones, seguimiento y administración, con acceso abierto y permisos diferenciados.
- **Justificación:** describir beneficios esperados de flexibilidad y organización, no resultados demostrados ni superioridad frente a LMS existentes.

## Capítulo 2: Marco Teórico

- **Educación virtual y LMS:** modalidades autogestionada y acompañada.
- **Gestión de cursos:** prerrequisitos internos y externos, verificación y progresión de formación.
- **Evaluación:** evaluación formativa, sumativa, intentos, límites de tiempo, recuperación y criterios de acreditación.
- **Plataformas web:** autenticación, verificación de correo, autorización RBAC, privacidad y separación entre cuenta e inscripción.
- **Bases de datos:** transacciones, integridad histórica, versiones de curso y progreso.
- **Despliegue/operación:** programación de avisos de caducidad, almacenamiento de videos/links externos y seguridad.
- No insertar precios, cifras o APIs no verificadas. No confundir constancia interna con reconocimiento oficial.

## Fragmento académico propuesto para el alcance (complemento, no sustitución de todo el apartado)

> La propuesta contempla un mecanismo de registro público de participantes mediante datos básicos y verificación del correo electrónico, conservando la asignación restringida de funciones administrativas y docentes. Los cursos podrán establecer prerrequisitos académicos particulares, vigencias de acceso configurables y evaluaciones formativas y finales. Los participantes que agoten los intentos de evaluación podrán realizar actividades de reforzamiento antes de iniciar un nuevo ciclo, manteniendo sus resultados históricos. Asimismo, se prevé que los instructores asignados proporcionen acompañamiento virtual mediante sesiones, recursos audiovisuales y retroalimentación de actividades.

## Integración segura

1. Crear rama `docs/actualizacion-politicas-aula-verde` partiendo de versión actual.
2. Revisar cambios manuales previos de capítulos 1 y 2; no sobrescribirlos.
3. Editar solo apartados afectados. No reformular objetivos aprobados.
4. Verificar correspondencia con la especificación de negocio y con el estado real del software.
5. Revisar referencias de documentos externos y `Referencias.bib`; no inventar entradas.
6. Compilar el archivo raíz del LaTeX, verificar índice y citas.
7. Commit separado del código Laravel.
