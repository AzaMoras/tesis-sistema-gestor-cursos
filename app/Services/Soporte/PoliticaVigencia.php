<?php

namespace App\Services\Soporte;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use InvalidArgumentException;

/** Política resuelta en servidor; la ausencia de política no significa acceso perpetuo. */
final readonly class PoliticaVigencia
{
    public function __construct(
        public string $tipo,
        public ?int $dias = null,
        public ?int $diasProrroga = null,
    ) {
        if (! in_array($tipo, ['sin_vencimiento', 'con_vencimiento'], true)
            || ($tipo === 'sin_vencimiento' && ($dias !== null || $diasProrroga !== null))
            || ($tipo === 'con_vencimiento' && ($dias === null || $dias < 1 || $dias > 65535))
            || ($diasProrroga !== null && ($diasProrroga < 1 || $diasProrroga > 65535))) {
            throw new InvalidArgumentException('Define una vigencia válida; la prórroga solo corresponde a acceso limitado.');
        }
    }

    /** Usar solo cuando el servidor haya clasificado la edición como gratuita limitada. */
    public static function gratuitaLimitada(int $dias = 30, ?int $diasProrroga = null): self
    {
        return new self('con_vencimiento', $dias, $diasProrroga);
    }

    public function vencimiento(DateTimeInterface $activacion): ?CarbonImmutable
    {
        return $this->dias === null ? null : CarbonImmutable::instance($activacion)->utc()->addDays($this->dias);
    }
}
