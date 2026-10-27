<?php

namespace Tests\Unit\Services;

use App\Services\Soporte\ReglasOperacion;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ReglasOperacionTest extends TestCase
{
    public function test_schema_defaults_are_preserved_and_missing_state_is_explicit(): void
    {
        $rules = new ReglasOperacion;
        self::assertSame('pendiente', $rules->estado('solicitud.pendiente'));
        self::assertSame('pendiente_pago', $rules->estado('inscripcion.pendiente_pago'));
        self::assertSame('enviada', $rules->estado('entrega.enviada'));
        self::assertNull($rules->estadoOpcional('solicitud.aprobada'));
        $this->expectException(LogicException::class);
        $rules->estado('solicitud.aprobada');
    }

    public function test_explicit_server_policy_is_returned_without_replacing_defaults(): void
    {
        $rules = new ReglasOperacion(['pago.verificado' => 'validado'], ['grupo' => ['abierto']], 45, false);
        self::assertSame('validado', $rules->estado('pago.verificado'));
        self::assertSame('pendiente', $rules->estado('pago.pendiente'));
        self::assertSame(['abierto'], $rules->estadosDeApertura('grupo'));
        self::assertSame(45, $rules->minutosDeReserva());
        self::assertFalse($rules->cupoNuloEsIlimitado());
        self::assertTrue((new ReglasOperacion(cupoNuloIlimitado: true))->cupoNuloEsIlimitado());
    }

    #[DataProvider('invalidPolicies')]
    public function test_invalid_or_ambiguous_policy_is_refused(array $arguments): void
    {
        $this->expectException(InvalidArgumentException::class);
        new ReglasOperacion(...$arguments);
    }

    public static function invalidPolicies(): array
    {
        return [
            [['estados' => ['rol.administrador' => 'activo']]],
            [['estados' => ['pago.verificado' => 'pendiente']]],
            [['estados' => ['pago.verificado' => 'Verificado']]],
            [['estados' => ['pago.verificado' => 'válido']]],
            [['estados' => ['pago.verificado' => str_repeat('a', 26)]]],
            [['apertura' => ['grupo' => ['borrador']]]],
            [['apertura' => ['grupo' => []]]],
            [['apertura' => ['usuario' => ['activo']]]],
            [['minutosReserva' => 0]],
            [['minutosReserva' => -1]],
        ];
    }

    #[DataProvider('missingDecisions')]
    public function test_missing_operational_decision_cannot_be_assumed(string $method, array $arguments): void
    {
        $rules = new ReglasOperacion;
        $this->expectException(LogicException::class);
        $rules->$method(...$arguments);
    }

    public static function missingDecisions(): array
    {
        return [['minutosDeReserva', []], ['cupoNuloEsIlimitado', []], ['estadosDeApertura', ['edicion']]];
    }
}
