<?php

declare(strict_types=1);

$arguments = array_slice($argv, 1);
if (array_diff($arguments, ['--production', '--from-postgres']) !== []) {
    fwrite(STDERR, "Uso: docker-init.php [--production] [--from-postgres]\n");
    exit(1);
}

$production = in_array('--production', $arguments, true);
$fromPostgres = in_array('--from-postgres', $arguments, true);
$root = dirname(__DIR__);
$directory = $root.'/.docker/'.($production ? 'production' : 'local');
$appPath = $directory.'/app-mariadb.env';
$dbPath = $directory.'/db-mariadb.env';
$legacyPath = $directory.'/app.env';

if (file_exists($appPath) || file_exists($dbPath)) {
    fwrite(STDERR, "Ya existen archivos privados de MariaDB. Se conservan las claves.\n");
    exit(1);
}

if (is_file($legacyPath) !== $fromPostgres) {
    fwrite(STDERR, "Para conservar una configuración Docker anterior, usa --from-postgres con su app.env disponible.\n");
    exit(1);
}

$source = $fromPostgres ? $legacyPath : $root.'/'.($production ? '.env.production.example' : '.env.docker.example');
$template = @file_get_contents($source);
if ($template === false) {
    fwrite(STDERR, "No se pudo leer la configuración de origen.\n");
    exit(1);
}

$template = str_replace("\r\n", "\n", $template);
if ($fromPostgres && ! preg_match('/^APP_KEY=base64:[A-Za-z0-9+\/]{43}=$/m', $template)) {
    fwrite(STDERR, "La configuración anterior requiere una APP_KEY válida del inicializador de Docker.\n");
    exit(1);
}

umask(0077);
$created = [];
try {
    if (! is_dir($directory) && ! @mkdir($directory, 0700, true) && ! is_dir($directory)) {
        throw new RuntimeException();
    }

    $password = bin2hex(random_bytes(32));
    $database = "DB_CONNECTION=mariadb\nDB_HOST=db\nDB_PORT=3306\nDB_DATABASE=aula_verde\n"
        ."DB_USERNAME=aula_app\nDB_PASSWORD={$password}\nDB_URL=\nDB_SOCKET=\n"
        ."DB_CHARSET=utf8mb4\nDB_COLLATION=utf8mb4_nopad_bin\n";

    if ($fromPostgres) {
        $template = preg_replace('/^\h*(?:export\h+)?DB_[A-Z0-9_]+=.*\n?/m', '', $template);
        $app = rtrim($template)."\n".$database;
    } else {
        $key = 'base64:'.base64_encode(random_bytes(32));
        $app = str_replace(["APP_KEY=\n", "DB_PASSWORD=\n"], ["APP_KEY={$key}\n", "DB_PASSWORD={$password}\n"], $template);
    }

    $adminPassword = bin2hex(random_bytes(32));
    $db = "MARIADB_DATABASE=aula_verde\nMARIADB_USER=aula_app\n"
        ."MARIADB_PASSWORD={$password}\nMARIADB_ROOT_PASSWORD={$adminPassword}\n";

    foreach ([$appPath => $app, $dbPath => $db] as $path => $content) {
        $file = @fopen($path, 'xb');
        if ($file === false) {
            throw new RuntimeException();
        }
        $created[] = $path;
        $written = @fwrite($file, $content);
        $closed = @fclose($file);
        if ($written !== strlen($content) || ! $closed) {
            throw new RuntimeException();
        }
    }
} catch (Throwable) {
    foreach ($created as $path) {
        @unlink($path);
    }
    fwrite(STDERR, "No se pudieron crear los archivos privados de MariaDB.\n");
    exit(1);
}

echo "Archivos privados de MariaDB creados. Conserva respaldos protegidos.\n";
