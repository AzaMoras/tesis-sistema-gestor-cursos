<?php

namespace App\Services\Soporte;

use Illuminate\Validation\ValidationException;

final class Importes
{
    /** Convierte DECIMAL(12,2) sin floats, redondeos ni notación científica. */
    public static function centavos(mixed $importe, string $campo = 'monto'): int
    {
        if ((! is_string($importe) && ! is_int($importe))
            || ! preg_match('/\A[0-9]{1,10}(?:\.[0-9]{1,2})?\z/', (string) $importe)) {
            throw ValidationException::withMessages([$campo => 'Utiliza un decimal no negativo con máximo diez enteros y dos decimales.']);
        }

        [$entero, $decimal] = array_pad(explode('.', (string) $importe, 2), 2, '');

        return (int) $entero * 100 + (int) str_pad($decimal, 2, '0');
    }

    public static function decimal(int $centavos): string
    {
        if ($centavos < 0) {
            throw ValidationException::withMessages(['monto' => 'El importe no puede ser negativo.']);
        }

        return intdiv($centavos, 100).'.'.str_pad((string) ($centavos % 100), 2, '0', STR_PAD_LEFT);
    }
}
