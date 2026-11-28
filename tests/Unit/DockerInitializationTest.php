<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class DockerInitializationTest extends TestCase
{
    public function test_initialization_keeps_keys_persistent_and_preserves_mysql_environment(): void
    {
        $root = sys_get_temp_dir().'/aula-docker-'.bin2hex(random_bytes(8));
        mkdir($root.'/scripts', 0700, true);
        $project = dirname(__DIR__, 2);
        copy($project.'/scripts/docker-init.php', $root.'/scripts/docker-init.php');
        copy($project.'/.env.docker.example', $root.'/.env.docker.example');
        file_put_contents($root.'/.env', "DB_CONNECTION=mysql\n");
        try {
            $first = new Process([PHP_BINARY, $root.'/scripts/docker-init.php']);
            $first->mustRun();
            $app = file_get_contents($root.'/.docker/local/app-mariadb.env');
            $db = file_get_contents($root.'/.docker/local/db-mariadb.env');
            preg_match('/^APP_KEY=base64:(.+)$/m', $app, $match);
            self::assertSame(32, strlen(base64_decode($match[1], true)));
            preg_match('/^DB_PASSWORD=(.+)$/m', $app, $password);
            self::assertStringContainsString("DB_CONNECTION=mariadb\n", $app);
            self::assertStringContainsString("DB_USERNAME=aula_app\n", $app);
            self::assertStringContainsString("MARIADB_USER=aula_app\n", $db);
            self::assertStringContainsString("MARIADB_PASSWORD={$password[1]}\n", $db);
            self::assertStringNotContainsString("MARIADB_ROOT_PASSWORD={$password[1]}\n", $db);
            self::assertStringNotContainsString('MARIADB_ROOT_PASSWORD', $app);
            self::assertStringNotContainsString($password[1], $first->getOutput());
            self::assertSame("DB_CONNECTION=mysql\n", file_get_contents($root.'/.env'));

            $second = new Process([PHP_BINARY, $root.'/scripts/docker-init.php']);
            $second->run();
            self::assertSame(1, $second->getExitCode());
            self::assertSame($app, file_get_contents($root.'/.docker/local/app-mariadb.env'));
            self::assertSame($db, file_get_contents($root.'/.docker/local/db-mariadb.env'));

            mkdir($root.'/.docker/production', 0700);
            $previous = "APP_KEY=base64:{$match[1]}\nAPP_URL=https://aula.example.test\n"
                ."DB_CONNECTION=pgsql\nDB_PORT=5432\nDB_URL=pgsql://example.test\n";
            file_put_contents($root.'/.docker/production/app.env', $previous);
            $upgrade = new Process([PHP_BINARY, $root.'/scripts/docker-init.php', '--production']);
            $upgrade->run();
            self::assertSame(1, $upgrade->getExitCode());
            self::assertFileDoesNotExist($root.'/.docker/production/app-mariadb.env');

            $upgrade = new Process([PHP_BINARY, $root.'/scripts/docker-init.php', '--production', '--from-postgres']);
            $upgrade->mustRun();
            $newConfig = file_get_contents($root.'/.docker/production/app-mariadb.env');
            self::assertStringContainsString("APP_KEY=base64:{$match[1]}\n", $newConfig);
            self::assertStringContainsString("APP_URL=https://aula.example.test\n", $newConfig);
            self::assertStringContainsString("DB_PORT=3306\n", $newConfig);
            self::assertStringNotContainsString('pgsql', $newConfig);
            self::assertSame($previous, file_get_contents($root.'/.docker/production/app.env'));
        } finally {
            foreach ([
                '.docker/local/app-mariadb.env', '.docker/local/db-mariadb.env',
                '.docker/production/app.env', '.docker/production/app-mariadb.env', '.docker/production/db-mariadb.env',
                'scripts/docker-init.php', '.env.docker.example', '.env',
            ] as $file) {
                if (is_file($root.'/'.$file)) {
                    unlink($root.'/'.$file);
                }
            }
            foreach (['.docker/production', '.docker/local', '.docker', 'scripts', ''] as $directory) {
                if (is_dir($root.'/'.$directory)) {
                    rmdir($root.'/'.$directory);
                }
            }
        }
    }
}
