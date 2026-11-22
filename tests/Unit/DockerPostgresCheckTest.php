<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class DockerPostgresCheckTest extends TestCase
{
    public function test_isolated_migration_rejects_the_main_database_before_connecting(): void
    {
        $process = new Process(
            [PHP_BINARY, 'scripts/check-postgres.php', '--migrate-isolated', 'aula_test_verificacion'],
            dirname(__DIR__, 2),
            [
                'APP_ENV' => 'testing',
                'APP_CONFIG_CACHE' => 'bootstrap/cache/config-docker-test.php',
                'DB_CONNECTION' => 'pgsql',
                'DB_HOST' => 'invalid.invalid',
                'DB_DATABASE' => 'aula_verde',
                'DB_URL' => '',
            ],
        );
        $process->run();

        self::assertNotSame(0, $process->getExitCode());
        self::assertStringContainsString(
            'La conexión activa debe apuntar a la base PostgreSQL aislada.',
            $process->getOutput().$process->getErrorOutput(),
        );
    }
}
