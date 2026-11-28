<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class ProductionEnvironmentTest extends TestCase
{
    public function test_accepts_a_valid_production_environment(): void
    {
        $process = $this->runPreflight();

        self::assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        self::assertSame('', $process->getOutput());
    }

    #[DataProvider('invalidSettings')]
    public function test_rejects_invalid_settings_without_echoing_their_values(string $name, string|false $value): void
    {
        $process = $this->runPreflight([$name => $value]);

        self::assertSame(1, $process->getExitCode());
        self::assertSame('', $process->getOutput());
        $fields = $name === 'AULA_DOMAIN' ? 'AULA_DOMAIN, APP_URL' : $name;
        self::assertSame(
            'Configuración de producción inválida: '.$fields.PHP_EOL,
            $process->getErrorOutput(),
        );
    }

    public static function invalidSettings(): array
    {
        return [
            'entorno local' => ['APP_ENV', 'local'],
            'depuración activa' => ['APP_DEBUG', 'true'],
            'depuración ausente' => ['APP_DEBUG', false],
            'clave ausente' => ['APP_KEY', false],
            'clave inválida' => ['APP_KEY', 'base64:solo-para-pruebas'],
            'clave corta' => ['APP_KEY', 'base64:'.base64_encode('solo para pruebas')],
            'dominio de plantilla' => ['AULA_DOMAIN', 'aula.invalid'],
            'host no válido' => ['AULA_DOMAIN', 'test/example'],
            'dirección IP' => ['AULA_DOMAIN', '192.0.2.1'],
            'URL HTTP' => ['APP_URL', 'http://aula.example.test'],
            'URL con host distinto' => ['APP_URL', 'https://otro.example.test'],
            'URL con ruta' => ['APP_URL', 'https://aula.example.test/admin'],
            'cookie sin Secure' => ['SESSION_SECURE_COOKIE', 'false'],
            'cookie sin HttpOnly' => ['SESSION_HTTP_ONLY', 'false'],
            'cookie sin SameSite Lax' => ['SESSION_SAME_SITE', 'none'],
        ];
    }

    private function runPreflight(array $overrides = []): Process
    {
        $process = new Process(
            [PHP_BINARY, dirname(__DIR__, 2).'/scripts/check-production-env.php'],
            env: $overrides + [
                'AULA_DOMAIN' => 'aula.example.test',
                'APP_ENV' => 'production',
                'APP_DEBUG' => 'false',
                'APP_KEY' => 'base64:'.base64_encode(str_repeat('x', 32)),
                'APP_URL' => 'https://aula.example.test',
                'SESSION_SECURE_COOKIE' => 'true',
                'SESSION_HTTP_ONLY' => 'true',
                'SESSION_SAME_SITE' => 'lax',
            ],
        );
        $process->run();

        return $process;
    }
}
