<?php
// Genera secretos privados sin leer ni modificar el .env de MySQL.
declare(strict_types=1);
$production = in_array('--production', $argv, true);
$root = dirname(__DIR__);
$dir = $root.'/.docker/'.($production ? 'production' : 'local');
if (file_exists($dir.'/app.env') || file_exists($dir.'/db.env')) {
    fwrite(STDERR, "Inicialización detenida: ya existen archivos privados. No se sobrescriben claves.\n");
    exit(1);
}
if (!is_dir($dir) && !mkdir($dir, 0700, true)) {
    throw new RuntimeException('No se pudo crear el directorio privado.');
}
$password = bin2hex(random_bytes(32));
$key = 'base64:'.base64_encode(random_bytes(32));
$template = file_get_contents($root.'/'.($production ? '.env.production.example' : '.env.docker.example'));
$template = str_replace(["APP_KEY=\n", "DB_PASSWORD=\n"], ["APP_KEY=".$key."\n", "DB_PASSWORD=".$password."\n"], str_replace("\r\n", "\n", $template));
file_put_contents($dir.'/app.env', $template, LOCK_EX);
file_put_contents($dir.'/db.env', "POSTGRES_USER=aula_admin\nPOSTGRES_DB=aula_verde\nPOSTGRES_PASSWORD=".bin2hex(random_bytes(32))."\nAPP_DB_PASSWORD=".$password."\n", LOCK_EX);
chmod($dir.'/app.env', 0600);
chmod($dir.'/db.env', 0600);
echo "Archivos privados creados. Conserva respaldos protegidos; no se muestran secretos.\n";
