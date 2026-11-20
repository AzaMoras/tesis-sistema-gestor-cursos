<?php

namespace App\Console\Commands\Concerns;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use PDO;

trait VerificaPreparacionLocal
{
    protected function destino(): ?Connection
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

    protected function comprobarRespaldo(): ?string
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
