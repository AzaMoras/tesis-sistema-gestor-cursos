<?php

declare(strict_types=1);

$domain = getenv('AULA_DOMAIN');
$key = getenv('APP_KEY');
$decodedKey = is_string($key) && str_starts_with($key, 'base64:')
    ? base64_decode(substr($key, 7), true)
    : false;

$validDomain = is_string($domain)
    && $domain !== 'aula.invalid'
    && preg_match('/\A[a-z0-9-]+(?:\.[a-z0-9-]+)+\z/', $domain) === 1
    && filter_var($domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) !== false
    && filter_var($domain, FILTER_VALIDATE_IP) === false;

$checks = [
    'APP_ENV' => getenv('APP_ENV') === 'production',
    'APP_DEBUG' => getenv('APP_DEBUG') === 'false',
    'APP_KEY' => is_string($decodedKey) && strlen($decodedKey) === 32
        && $key === 'base64:'.base64_encode($decodedKey),
    'AULA_DOMAIN' => $validDomain,
    'APP_URL' => $validDomain && getenv('APP_URL') === 'https://'.$domain,
    'SESSION_SECURE_COOKIE' => getenv('SESSION_SECURE_COOKIE') === 'true',
    'SESSION_HTTP_ONLY' => getenv('SESSION_HTTP_ONLY') === 'true',
    'SESSION_SAME_SITE' => getenv('SESSION_SAME_SITE') === 'lax',
];

$invalid = array_keys(array_filter($checks, static fn (bool $valid): bool => ! $valid));

if ($invalid !== []) {
    fwrite(STDERR, 'Configuración de producción inválida: '.implode(', ', $invalid).PHP_EOL);
    exit(1);
}
