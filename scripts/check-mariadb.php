<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Console\Output\BufferedOutput;

require dirname(__DIR__).'/vendor/autoload.php';

final class MariaDbCheckFailed extends RuntimeException {}

function checkMariaDb(array $arguments): int
{
    $isolated = $arguments !== [];
    $database = 'aula_verde';
    if ($isolated) {
        if (count($arguments) !== 2 || $arguments[0] !== '--migrate-isolated'
            || ! preg_match('/\Aaula_test_[a-z0-9_]{1,53}\z/', $arguments[1])) {
            throw new MariaDbCheckFailed('Uso: check-mariadb.php [--migrate-isolated BASE_AISLADA]');
        }
        $database = $arguments[1];
    }

    $app = require dirname(__DIR__).'/bootstrap/app.php';
    if ($isolated && $app->configurationIsCached()) {
        throw new MariaDbCheckFailed('La prueba de migraciones requiere configuración sin caché.');
    }

    $console = $app->make(Kernel::class);
    $console->bootstrap();
    $db = DB::connection();
    if ($db->getDriverName() !== 'mariadb') {
        throw new MariaDbCheckFailed('Se requiere la conexión mariadb de Laravel.');
    }
    if ($isolated && (! $app->environment('testing') || $db->getDatabaseName() !== $database
        || $db->getConfig('host') !== 'db' || ! empty($db->getConfig('unix_socket'))
        || ! empty(config('database.connections.mariadb.url')))) {
        throw new MariaDbCheckFailed('La conexión activa debe apuntar a la base MariaDB aislada.');
    }
    if ($db->getDatabaseName() !== $database || $db->getConfig('username') !== 'aula_app') {
        throw new MariaDbCheckFailed('Se requiere la base esperada y el usuario aula_app.');
    }

    $server = $db->selectOne(
        'SELECT DATABASE() AS db_name, CURRENT_USER() AS account, VERSION() AS version,
            @@character_set_connection AS charset, @@collation_connection AS collation,
            @@collation_database AS schema_collation, @@session.time_zone AS time_zone,
            @@session.sql_mode AS sql_mode, @@session.foreign_key_checks AS foreign_key_checks',
        [],
        false,
    );
    if ($db->getPdo()->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql'
        || ! str_contains($server->version, 'MariaDB')
        || $server->db_name !== $database || $server->account !== 'aula_app@%') {
        throw new MariaDbCheckFailed('El servidor, la base o la cuenta efectiva difieren de la configuración esperada.');
    }

    if ($server->charset !== 'utf8mb4' || $server->collation !== 'utf8mb4_nopad_bin'
        || $server->schema_collation !== 'utf8mb4_nopad_bin' || $server->time_zone !== '+00:00'
        || ! array_intersect(['STRICT_TRANS_TABLES', 'STRICT_ALL_TABLES'], explode(',', $server->sql_mode))
        || (int) $server->foreign_key_checks !== 1) {
        throw new MariaDbCheckFailed('Se requieren utf8mb4_nopad_bin, UTC, modo estricto y claves foráneas activas.');
    }

    assertDatabaseGrants($db, $database);
    echo "MariaDB conectada mediante pdo_mysql con permisos limitados a la base de aplicación.\n";

    if ($isolated) {
        $tables = (int) $db->scalar('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()', [], false);
        if ($tables !== 0) {
            throw new MariaDbCheckFailed('Las pruebas requieren una base aislada nueva y vacía.');
        }

        foreach (['migrate', 'migrate:status', 'db:seed'] as $command) {
            if ($console->call($command, ['--no-interaction' => true], new BufferedOutput()) !== 0) {
                throw new MariaDbCheckFailed("Falló el comando {$command} en MariaDB aislada.");
            }
            echo "{$command}: completado en MariaDB aislada.\n";
        }

        $expected = array_map(static fn (string $path): string => basename($path, '.php'), glob(database_path('migrations/*.php')));
        $ran = $db->table('migrations')->orderBy('migration')->pluck('migration')->all();
        if ($ran !== $expected) {
            throw new MariaDbCheckFailed('Las migraciones registradas no coinciden con los archivos del proyecto.');
        }

        assertSeedAndConstraints($db);
        echo "Seeder y restricciones de unicidad y clave foránea verificados en MariaDB aislada.\n";
    }

    echo 'Migraciones registradas: '.$db->table('migrations')->count()."\n";

    return 0;
}

function assertDatabaseGrants(Connection $db, string $database): void
{
    $account = ' TO `aula_app`@`%`';
    $usage = 'GRANT USAGE ON *.*'.$account;
    $schemaGrant = 'GRANT ALL PRIVILEGES ON `'.str_replace('_', '\_', $database).'`.*'.$account;
    $hasSchemaGrant = false;
    foreach ($db->select('SHOW GRANTS FOR CURRENT_USER()', [], false) as $row) {
        // SHOW GRANTS puede incluir datos de autenticación. No se escriben en la salida.
        $grant = preg_replace("/ IDENTIFIED BY PASSWORD '\\*[0-9A-Fa-f]{40}'$/", '', array_values((array) $row)[0]);
        if ($grant === $schemaGrant) {
            $hasSchemaGrant = true;
        } elseif ($grant !== $usage) {
            throw new MariaDbCheckFailed('La cuenta de aplicación tiene permisos fuera del esquema permitido.');
        }
    }
    if (! $hasSchemaGrant) {
        throw new MariaDbCheckFailed('Faltan permisos de migración para el esquema de aplicación.');
    }
}

function assertSeedAndConstraints(Connection $db): void
{
    $user = User::query()->where('email', 'test@example.com')->sole();
    if ($user->name !== 'Test User') {
        throw new MariaDbCheckFailed('El seeder no creó el usuario esperado.');
    }

    $db->beginTransaction();
    try {
        try {
            $db->table('users')->insert(['name' => $user->name, 'email' => $user->email, 'password' => $user->password]);
            throw new MariaDbCheckFailed('MariaDB aceptó un correo duplicado.');
        } catch (UniqueConstraintViolationException) {
            // Laravel traduce el error 1062 al tipo que consumen los servicios.
        }

        try {
            $db->table('participantes')->insert(['user_id' => $user->id + 1, 'fecha_alta' => '2026-01-01']);
            throw new MariaDbCheckFailed('MariaDB aceptó una referencia a un usuario inexistente.');
        } catch (QueryException $exception) {
            if ((int) ($exception->errorInfo[1] ?? 0) !== 1452) {
                throw $exception;
            }
        }
    } finally {
        $db->rollBack();
    }
}

try {
    exit(checkMariaDb(array_slice($argv, 1)));
} catch (MariaDbCheckFailed $exception) {
    fwrite(STDERR, $exception->getMessage()."\n");
    exit(1);
} catch (Throwable) {
    fwrite(STDERR, "Falló la comprobación de MariaDB. Revisa los registros locales protegidos.\n");
    exit(1);
}
