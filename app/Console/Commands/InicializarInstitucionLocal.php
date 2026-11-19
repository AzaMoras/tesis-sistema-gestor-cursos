<?php

namespace App\Console\Commands;

use App\Models\Rol;
use App\Models\User;
use App\Services\InicializacionInstitucionalService;
use Illuminate\Console\Command;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PDO;
use Throwable;

class InicializarInstitucionLocal extends Command
{
    protected $signature = 'aula-verde:inicializar-institucion
        {--base= : Nombre exacto de la base prevista}
        {--host=127.0.0.1 : Host local previsto}
        {--puerto=3306 : Puerto MariaDB previsto}
        {--usuario= : ID explícito de una cuenta existente}
        {--respaldo= : Archivo privado .sql, .sql.gz o .dump ya comprobado}
        {--sha256= : Checksum SHA-256 previamente calculado del respaldo}
        {--autorizar-pruebas : Solo para testing con SQLite :memory:}';

    protected $description = 'Inicializa una institución demostrativa y su administrador en MariaDB local, con confirmaciones';

    public function handle(InicializacionInstitucionalService $inicializacion): int
    {
        if (! app()->environment(['local', 'testing']) || app()->configurationIsCached()) {
            $this->error('Rechazado: solo local o testing autorizado, sin configuración cacheada. Producción está excluida.');

            return self::FAILURE;
        }
        if (! $this->input->isInteractive() || $this->option('quiet') || $this->option('silent')
            || $this->output->isQuiet() || $this->output->isSilent()) {
            $this->error('Se requiere ejecución interactiva con salida visible y confirmaciones del operador.');

            return self::FAILURE;
        }
        try {
            $conexion = $this->destino();
            if (! $conexion) {
                return self::FAILURE;
            }
            $sha256 = $this->comprobarRespaldo();
            if ($sha256 === null) {
                return self::FAILURE;
            }
            $id = $this->option('usuario') ?? $this->ask('ID de la cuenta existente que autorizas como administrador');
            if (! is_scalar($id) || filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
                $this->error('Identifica explícitamente una cuenta mediante un ID entero positivo.');

                return self::FAILURE;
            }
            $usuario = User::query()->find((int) $id);
            if (! $usuario || $usuario->estado_cuenta !== 'activa') {
                $this->error('La cuenta seleccionada no existe, fue eliminada o no está activa. No se activará automáticamente.');

                return self::FAILURE;
            }
            $this->table(['ID', 'Nombre', 'Correo', 'Estado'], [[$usuario->id, $usuario->name, $usuario->email, $usuario->estado_cuenta]]);
            $this->info('Institución: '.InicializacionInstitucionalService::NOMBRE.' / '.InicializacionInstitucionalService::SLUG);
            $asignaciones = Rol::where('clave', 'administrador')->first()?->usuarios()->count() ?? 0;
            $this->line('Escrituras previstas, únicamente cuando falten:');
            $this->line('1. instituciones: institución demostrativa activa.');
            $this->line('2. roles: administrador. 3. permisos: cursos.gestionar.');
            $this->line('4. rol_permiso: asociación GLOBAL del permiso al rol administrador.');
            $this->warn('Asignaciones actuales del rol administrador: '.$asignaciones.'. Todas recibirán ese permiso si todavía falta.');
            $this->line('5. usuario_rol: asignación a esta cuenta dentro de la institución demostrativa.');
            $this->line('6. bitacora: auditoría de los cambios, sin credenciales ni contenido del respaldo.');
            $this->line('No cambia contraseña, correo verificado, perfil de participante ni migraciones.');
            if (! $this->confirm('¿Confirmas que el respaldo corresponde a esta base y comprobaste su restauración en un entorno separado?', false)
                || ! $this->confirm('¿Autorizas asignar los privilegios administrativos indicados a la cuenta mostrada?', false)) {
                $this->warn('Cancelado sin escrituras.');

                return self::FAILURE;
            }
            $institucion = $inicializacion->inicializar($usuario->id, $usuario->email, $usuario->name, $sha256);
            $this->info('Inicialización completada. Institución ID: '.$institucion->id.'. Inicia sesión con la cuenta seleccionada y abre /gestion.');

            return self::SUCCESS;
        } catch (ValidationException $error) {
            foreach ($error->errors() as $mensajes) {
                foreach ($mensajes as $mensaje) {
                    $this->error($mensaje);
                }
            }
        } catch (Throwable) {
            // Los mensajes de conexión/SQL pueden contener información sensible.
            $this->error('No se completó la inicialización. Revisa conexión/esquema; la transacción revierte cualquier escritura de este intento.');
        }

        return self::FAILURE;
    }

    private function destino(): ?Connection
    {
        $base = $this->option('base');
        if (! is_string($base) || trim($base) === '') {
            $this->error('Debes indicar --base con el nombre exacto esperado; no se infiere ni se elige automáticamente.');

            return null;
        }
        $original = config('database.connections.'.config('database.default'));
        if (! empty($original['read']) || ! empty($original['write'])) {
            $this->error('No se admiten destinos separados de lectura/escritura.');

            return null;
        }
        $conexion = DB::connection();
        $config = $conexion->getConfig();
        $this->line('Base seleccionada: '.($config['database'] ?? '(sin nombre)').' | Conexión: '.$conexion->getName());
        if (($config['database'] ?? null) !== $base || ! empty($config['read']) || ! empty($config['write']) || ! empty($config['prefix'])) {
            $this->error('La base no coincide o la conexión usa destinos de lectura/escritura o prefijos no admitidos.');

            return null;
        }
        if (app()->environment('testing')) {
            if (! $this->option('autorizar-pruebas') || ($config['driver'] ?? null) !== 'sqlite'
                || $base !== ':memory:' || ! empty($config['url'])
                || $conexion->getPdo()->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'sqlite') {
                $this->error('Testing requiere --autorizar-pruebas y SQLite :memory: aislada.');

                return null;
            }

            return $conexion;
        }
        $host = $config['host'] ?? null;
        if ($this->option('autorizar-pruebas') || ! in_array($config['driver'] ?? null, ['mysql', 'mariadb'], true)
            || ! in_array($host, ['127.0.0.1', 'localhost', '::1'], true)
            || $host !== $this->option('host') || (string) ($config['port'] ?? '') !== $this->option('puerto')
            || ! empty($config['unix_socket'])) {
            $this->error('Se requiere MariaDB local por TCP y coincidencia exacta de host/puerto esperados.');

            return null;
        }
        $this->line('Servidor previsto: '.$host.':'.$config['port']);
        if ($conexion->getPdo()->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql') {
            $this->error('El driver efectivo no corresponde a MariaDB/MySQL.');

            return null;
        }
        $servidor = $conexion->selectOne('SELECT DATABASE() AS base_actual, VERSION() AS version_actual, @@port AS puerto_actual');
        if ($servidor->base_actual !== $base || stripos($servidor->version_actual, 'MariaDB') === false
            || (string) $servidor->puerto_actual !== $this->option('puerto')) {
            $this->error('El servidor efectivo no coincide con la base/puerto previstos o no es MariaDB.');

            return null;
        }
        $this->info('MariaDB local comprobada. Base efectiva: '.$servidor->base_actual);
        $tablas = ['users', 'instituciones', 'roles', 'permisos', 'rol_permiso', 'usuario_rol', 'bitacora'];
        $motores = $conexion->table('information_schema.TABLES')->where('TABLE_SCHEMA', $base)
            ->whereIn('TABLE_NAME', $tablas)->pluck('ENGINE', 'TABLE_NAME');
        if ($motores->count() !== count($tablas) || $motores->contains(fn ($motor) => strcasecmp($motor, 'InnoDB') !== 0)) {
            $this->error('Faltan tablas requeridas o no usan InnoDB. Revisa el esquema; este comando no ejecuta migraciones.');

            return null;
        }

        return $conexion;
    }

    private function comprobarRespaldo(): ?string
    {
        $ruta = $this->option('respaldo');
        $sha256 = $this->option('sha256');
        if (! is_string($ruta) || ! preg_match('/\.(sql|sql\.gz|dump)\z/i', $ruta)
            || ! is_file($ruta) || ! is_readable($ruta) || filesize($ruta) < 1
            || ! is_string($sha256) || ! preg_match('/\A[0-9a-f]{64}\z/i', $sha256)
            || ! hash_equals(strtolower($sha256), hash_file('sha256', $ruta))) {
            $this->error('Requiere --respaldo legible y no vacío (.sql, .sql.gz o .dump), con --sha256 coincidente.');

            return null;
        }
        $this->info('Respaldo presente y checksum SHA-256 coincidente. La restauración debe haber sido comprobada por el operador.');

        return strtolower($sha256);
    }
}
