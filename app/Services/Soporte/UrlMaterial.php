<?php

namespace App\Services\Soporte;

/** Solo enlaces externos; nunca descarga, incrusta HTML ni consulta el destino. */
final class UrlMaterial
{
    public static function segura(mixed $url): bool
    {
        if (! is_string($url) || strlen($url) > 1000
            || preg_match('/[\x00-\x20\x7f\\\\]/', $url)
            || ! filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }
        $partes = parse_url($url);
        $host = strtolower($partes['host'] ?? '');
        if (($partes['scheme'] ?? '') !== 'https' || isset($partes['user']) || isset($partes['pass'])
            || (isset($partes['port']) && $partes['port'] !== 443)
            || ! str_contains($host, '.') || str_ends_with($host, '.local')
            || str_ends_with($host, '.localhost') || str_ends_with($host, '.test')) {
            return false;
        }

        return ! filter_var($host, FILTER_VALIDATE_IP)
            || (bool) filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    }
}
