<?php

namespace Tests\Unit\Services;

use App\Services\Soporte\Importes;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ImportesTest extends TestCase
{
    #[DataProvider('validAmounts')]
    public function test_decimal_conversion_is_exact(string|int $amount, int $cents, string $normalized): void
    {
        self::assertSame($cents, Importes::centavos($amount));
        self::assertSame($normalized, Importes::decimal($cents));
    }

    public static function validAmounts(): array
    {
        return [[0, 0, '0.00'], ['0.01', 1, '0.01'], ['12.3', 1230, '12.30'], ['0001.25', 125, '1.25'], ['9999999999.99', 999999999999, '9999999999.99']];
    }

    #[DataProvider('invalidAmounts')]
    public function test_invalid_amounts_are_rejected_without_rounding_or_coercion(mixed $amount): void
    {
        $this->expectException(ValidationException::class);
        Importes::centavos($amount);
    }

    public static function invalidAmounts(): array
    {
        return [[-1], ['-0.01'], [1.25], [true], [null], [[]], ['1.001'], ['1e3'], [' 1.00'], ['1,00'], ['10000000000.00'], ['NaN']];
    }

    public function test_negative_cents_cannot_be_formatted_as_valid_amount(): void
    {
        $this->expectException(ValidationException::class);
        Importes::decimal(-1);
    }
}
