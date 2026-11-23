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
            $app = file_get_contents($root.'/.docker/local/app.env');
            $db = file_get_contents($root.'/.docker/local/db.env');
            preg_match('/^APP_KEY=base64:(.+)$/m', $app, $match);
            self::assertSame(32, strlen(base64_decode($match[1], true)));
            preg_match('/^DB_PASSWORD=(.+)$/m', $app, $password);
            self::assertStringContainsString('APP_DB_PASSWORD='.$password[1], $db);
            self::assertStringNotContainsString($password[1], $first->getOutput());
            self::assertSame("DB_CONNECTION=mysql\n", file_get_contents($root.'/.env'));

            $second = new Process([PHP_BINARY, $root.'/scripts/docker-init.php']);
            $second->run();
            self::assertSame(1, $second->getExitCode());
            self::assertSame($app, file_get_contents($root.'/.docker/local/app.env'));
            self::assertSame($db, file_get_contents($root.'/.docker/local/db.env'));
        } finally {
            foreach (['.docker/local/app.env', '.docker/local/db.env', 'scripts/docker-init.php', '.env.docker.example', '.env'] as $file) {
                if (is_file($root.'/'.$file)) {
                    unlink($root.'/'.$file);
                }
            }
            foreach (['.docker/local', '.docker', 'scripts', ''] as $directory) {
                if (is_dir($root.'/'.$directory)) {
                    rmdir($root.'/'.$directory);
                }
            }
        }
    }
}
