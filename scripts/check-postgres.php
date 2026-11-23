<?php

// Comprueba Laravel, PDO y el rol efectivo. El modo aislado permite migrar la BD de prueba.
declare(strict_types=1);

require dirname(__DIR__).'/vendor/autoload.php';

function checkPostgres(array $arguments): int
{
    $isolatedDatabase = null;
    if ($arguments !== []) {
        if (count($arguments) !== 2 || $arguments[0] !== '--migrate-isolated') {
            throw new LogicException('Uso: check-postgres.php [--migrate-isolated BASE_AISLADA]');
        }
        $isolatedDatabase = $arguments[1];
    }

    $app = require dirname(__DIR__).'/bootstrap/app.php';
    if ($isolatedDatabase !== null && $app->configurationIsCached()) {
        throw new LogicException('La prueba de migraciones requiere configuración sin caché.');
    }

    $console = $app->make(Illuminate\Contracts\Console\Kernel::class);
    $console->bootstrap();
    $db = Illuminate\Support\Facades\DB::connection();
    if ($db->getDriverName() !== 'pgsql') {
        throw new LogicException('Se requiere PostgreSQL.');
    }

    if ($isolatedDatabase !== null
        && (! $app->environment('testing')
            || ! preg_match('/\Aaula_test_[a-z0-9_]{1,53}\z/', $isolatedDatabase)
            || $db->getDatabaseName() !== $isolatedDatabase
            || $db->selectOne('SELECT current_database() AS name', [], false)->name !== $isolatedDatabase)) {
        throw new LogicException('La conexión activa debe apuntar a la base PostgreSQL aislada.');
    }

    $role = $db->selectOne(
        'SELECT rolsuper, rolcreatedb, rolcreaterole, rolreplication FROM pg_roles WHERE rolname = current_user',
        [],
        false,
    );
    if ($role === null) {
        throw new LogicException('No se pudo comprobar el rol de PostgreSQL.');
    }

    foreach ((array) $role as $flag) {
        if (! in_array($flag, [false, 0, '0', 'f'], true)) {
            throw new LogicException('El usuario de aplicación tiene privilegios administrativos.');
        }
    }

    echo "PostgreSQL conectado desde Laravel; usuario sin privilegios administrativos.\n";
    if ($isolatedDatabase !== null) {
        $output = new Symfony\Component\Console\Output\ConsoleOutput();
        $exitCode = $console->call('migrate', ['--force' => true], $output);
        if ($exitCode !== 0) {
            return $exitCode;
        }

        $exitCode = $console->call('migrate:status', [], $output);
        if ($exitCode !== 0) {
            return $exitCode;
        }
    }

    echo 'Migraciones registradas: '.$db->table('migrations')->count()."\n";

    return 0;
}

try {
    exit(checkPostgres(array_slice($argv, 1)));
} catch (LogicException $exception) {
    fwrite(STDERR, $exception->getMessage()."\n");
    exit(1);
} catch (Throwable) {
    fwrite(STDERR, "Falló la comprobación de PostgreSQL. Revisa los registros locales.\n");
    exit(1);
}
