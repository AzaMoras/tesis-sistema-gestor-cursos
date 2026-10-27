<?php

namespace App\Services\Soporte;

use InvalidArgumentException;
use LogicException;

/** Opciones del servidor; nunca construir esta política con datos de formularios. */
final class ReglasOperacion
{
    private const INICIALES = [
        'solicitud.pendiente' => 'pendiente',
        'inscripcion.pendiente_pago' => 'pendiente_pago',
        'pago.pendiente' => 'pendiente',
        'entrega.enviada' => 'enviada',
    ];

    private const CONFIGURABLES = [
        'solicitud.aprobada', 'solicitud.rechazada',
        'inscripcion.confirmada', 'inscripcion.cancelada',
        'pago.verificado', 'pago.rechazado',
        'requisito.validado', 'actividad.evaluable', 'documento.aprobado',
    ];

    public function __construct(
        private readonly array $estados = [],
        private readonly array $apertura = [],
        private readonly ?int $minutosReserva = null,
        private readonly ?bool $cupoNuloIlimitado = null,
    ) {
        foreach ($estados as $clave => $valor) {
            if (! in_array($clave, self::CONFIGURABLES, true)) {
                throw new InvalidArgumentException('Estado configurable desconocido: '.$clave);
            }
            $this->validarEstado($valor);
        }

        $todos = $estados + self::INICIALES;
        foreach (['solicitud', 'inscripcion', 'pago'] as $entidad) {
            $valores = array_filter($todos, fn (string $clave): bool => str_starts_with($clave, $entidad.'.'), ARRAY_FILTER_USE_KEY);
            if (count($valores) !== count(array_unique($valores))) {
                throw new InvalidArgumentException('Las transiciones de '.$entidad.' necesitan estados diferentes.');
            }
        }

        foreach ($apertura as $entidad => $valores) {
            if (! in_array($entidad, ['curso', 'edicion', 'grupo'], true) || ! is_array($valores) || $valores === []) {
                throw new InvalidArgumentException('Define una lista de estados de apertura para curso, edicion o grupo.');
            }
            foreach ($valores as $valor) {
                $this->validarEstado($valor);
                if ($valor === 'borrador') {
                    throw new InvalidArgumentException('Un borrador no puede admitir inscripciones.');
                }
            }
        }

        if ($minutosReserva !== null && $minutosReserva < 1) {
            throw new InvalidArgumentException('La duración de reserva debe ser positiva.');
        }
    }

    public function estado(string $clave): string
    {
        return $this->estadoOpcional($clave)
            ?? throw new LogicException('Decisión de negocio pendiente: definir el estado '.$clave.'.');
    }

    /** Para diagnósticos: una decisión ausente se informa sin suponer un valor. */
    public function estadoOpcional(string $clave): ?string
    {
        return self::INICIALES[$clave] ?? $this->estados[$clave] ?? null;
    }

    public function estadosDeApertura(string $entidad): array
    {
        return $this->apertura[$entidad]
            ?? throw new LogicException('Decisión de negocio pendiente: estados de apertura de '.$entidad.'.');
    }

    public function minutosDeReserva(): int
    {
        return $this->minutosReserva
            ?? throw new LogicException('Decisión de negocio pendiente: duración de las reservas de pago.');
    }

    public function cupoNuloEsIlimitado(): bool
    {
        return $this->cupoNuloIlimitado
            ?? throw new LogicException('Decisión de negocio pendiente: significado de cupo_maximo nulo.');
    }

    private function validarEstado(mixed $estado): void
    {
        if (! is_string($estado) || ! preg_match('/\A[a-z][a-z0-9_]{0,24}\z/', $estado)) {
            throw new InvalidArgumentException('Los estados deben ser claves de hasta 25 caracteres en minúsculas.');
        }
    }
}
