# Pruebas de servicios

La suite utiliza PHPUnit 12, Laravel y las migraciones reales del proyecto.
No instala Pest ni otro framework. Ejecutar desde la raíz:

```powershell
php artisan test
```

Para una revisión puntual:

```powershell
php artisan test --filter=InscripcionServiceTest
```

## Aislamiento

`phpunit.xml` fuerza `APP_ENV=testing`, `DB_CONNECTION=sqlite`,
`DB_DATABASE=:memory:` y `DB_URL` vacío. `Tests\TestCase` comprueba estos valores
antes de que el trait `RefreshDatabase` pueda migrar y rechaza configuración
cacheada. Después elimina de la configuración en memoria las conexiones externas.

Por tanto, esta suite no permite conexiones MariaDB/MySQL ni archivos SQLite
persistentes. No modifica `.env` ni la configuración de BD del sistema.
`RefreshDatabase` puede ejecutar internamente `migrate:fresh`, exclusivamente
sobre la SQLite en memoria ya verificada. Los datos desaparecen al terminar.

Una futura suite específica de MariaDB necesita un arnés separado, credenciales
exclusivas y verificación de una BD descartable; no se debe desactivar esta
protección para apuntar a la BD de desarrollo.

## Fixtures y reglas pendientes

`Support/ServiceTestCase.php` construye instituciones, cursos, ediciones, grupos,
usuarios, roles y permisos mediante Eloquent y la factory de User. Los datos
controlados de los fixtures usan asignación explícita; no alteran fillable de los
modelos productivos. No se ejecutan seeders de desarrollo o producción.

El reloj se fija para probar reservas, vencimientos y fechas sin depender del
momento de ejecución. Los valores de estados finales, apertura y reserva son una
política exclusiva del fixture inyectada en `ReglasOperacion`; no establecen
decisiones de negocio de producción. También se comprueba el comportamiento
cuando falta esa política.

## Archivos y cobertura funcional

| Archivo | Casos principales |
| --- | --- |
| `Feature/Services/SolicitudRegistroServiceTest.php` | Solicitud pública sin privilegios, validación, duplicados, aprobación autorizada e idempotente, cuenta pendiente, documentos, rechazo, correo existente, institución y rollback ante fallo de auditoría |
| `Feature/Services/InscripcionServiceTest.php` | Inscripción gratuita y de pago, precio real de edición, duplicados incluso cancelados, último lugar, reservas vigentes/vencidas, cupo cero/nulo, propietarios, personal autorizado, requisitos documentales y confirmación con fondos verificados |
| `Feature/Services/PagoServiceTest.php` | Registro pendiente, referencias de reintento y externas únicas, precisión, permisos, evidencia y fecha de revisión, moneda, abonos/sobrepago, rechazo y pagos tardíos sin recuperar lugares |
| `Feature/Services/EvaluacionServiceTest.php` | Evaluación manual, límites de puntaje, idempotencia, correcciones bloqueadas, instructor asignado, institución, módulo/curso, intento y fechas, inscripción confirmada, privacidad y desglose exacto sin nota final inventada |
| `Feature/Services/ConstanciaServiceTest.php` | Diagnóstico sin emisión ficticia, reglas faltantes, inscripción pendiente, actividades sin evaluar, asistencias de otro grupo, permisos, propiedad y consulta de registros existentes |
| `Feature/Services/AutorizacionInstitucionalTest.php` | Cuenta recargada, cuenta/institución inactiva o eliminada, roles y permisos por institución, ausencia de bypass de Administrador, categoría ajena y bloqueos que requieren transacción |
| `Feature/Services/AuditoriaServiceTest.php` | Actor e institución reales, auditoría anónima admitida por esquema, rechazo de registros no persistidos, transacción requerida y rollback conjunto |
| `Unit/Services/ImportesTest.php` | Conversión decimal exacta, límites, cero y rechazo de float, negativos, precisión extra, notación científica y tipos inválidos |
| `Unit/Services/ReglasOperacionTest.php` | Valores del esquema, opciones explícitas, decisiones ausentes, estados inválidos/duplicados, borradores y duración inválida |
| `Feature/TestingEnvironmentTest.php` | Driver real SQLite, BD en memoria, entorno testing y ausencia de conexiones externas |

Las pruebas de integración ejecutan los servicios y consultas reales; no sustituyen
la autorización por mocks que conceden acceso. El fallo de auditoría se provoca
con un listener de eventos de Eloquent para verificar la reversión real de cuenta,
perfil y asignación del rol. Las rutas y documentos de los fixtures no representan
integraciones reales de almacenamiento o generación de PDF.

## Límite de concurrencia

Los casos de cupo comprueban secuencialmente el último lugar y las reservas.
SQLite ignora `FOR UPDATE`; estos resultados no demuestran el comportamiento de
bloqueos, aislamiento, deadlocks ni solicitudes simultáneas de MariaDB. Eso requiere
pruebas adicionales contra una instancia MariaDB de testing aislada.

Resultado de la ejecución realizada en esta etapa: **113 pruebas aprobadas y
353 aserciones**, incluidas las dos pruebas de ejemplo preexistentes. Las pruebas
nuevas no modifican los servicios ni afirman que se emiten constancias o se
procesan pagos mediante una pasarela.
