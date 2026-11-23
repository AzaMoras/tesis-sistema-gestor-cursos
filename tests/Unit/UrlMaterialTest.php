<?php

namespace Tests\Unit;

use App\Services\Soporte\UrlMaterial;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class UrlMaterialTest extends TestCase
{
    #[DataProvider('enlaces')]
    public function test_solo_permite_urls_https_externas_sin_credenciales(mixed $url, bool $esperado): void
    {
        self::assertSame($esperado, UrlMaterial::segura($url));
    }

    public static function enlaces(): array
    {
        return [
            ['https://www.wikipedia.org/', true], ['https://www.youtube.com/watch?v=abc-123', true],
            ['https://example.org/recurso.pdf?version=2#pagina', true], ['https://example.org:443/', true],
            ['http://example.org/', false], ['javascript:alert(1)', false], ['data:text/html,algo', false],
            ['file:///privado.pdf', false], ['//example.org/', false], ['', false], [null, false], [[], false],
            ['https://usuario:clave@example.org/', false], ['https://example.org:3307/', false],
            ['https://localhost/', false], ['https://ejemplo.local/', false], ['https://ejemplo.test/', false],
            ['https://127.0.0.1/', false], ['https://192.168.1.1/', false], ['https://10.0.0.1/', false],
            ['https://[::1]/', false], ["https://example.org/\nmalicioso", false], ['https://example.org\\@localhost/', false],
        ];
    }
}
