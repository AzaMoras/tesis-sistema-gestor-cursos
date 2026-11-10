<?php

namespace App\Services\Soporte;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use InvalidArgumentException;

/** Reglas sin persistencia; nunca reemplazan autorización ni conteos bloqueados en BD. */
final readonly class PoliticaEvaluacion
{
    public function __construct(public int $intentosPorCiclo = 3, public int $minutos = 30)
    {
        if ($intentosPorCiclo < 1 || $minutos < 1) {
            throw new InvalidArgumentException('Los intentos por ciclo y la duración deben ser positivos.');
        }
    }

    /** Valores impuestos por institución prevalecen; ambos mapas proceden del servidor. */
    public static function resolver(array $curso = [], array $institucion = []): self
    {
        foreach ([$curso, $institucion] as $configuracion) {
            foreach ($configuracion as $clave => $valor) {
                if (! in_array($clave, ['intentosPorCiclo', 'minutos'], true) || ! is_int($valor)) {
                    throw new InvalidArgumentException('Configuración de evaluación desconocida o no entera.');
                }
            }
        }

        return new self(...array_replace(['intentosPorCiclo' => 3, 'minutos' => 30], $curso, $institucion));
    }

    public function puedeAbrirIntento(string $tipo, int $consumidos, bool $moduloAprobado = false): bool
    {
        if (! in_array($tipo, ['formativa', 'final'], true) || $consumidos < 0) {
            throw new InvalidArgumentException('Tipo de evaluación o contador inválido.');
        }

        return $tipo === 'formativa' || (! $moduloAprobado && $consumidos < $this->intentosPorCiclo);
    }

    public function estadoFinal(int $consumidos, bool $aprobado): string
    {
        if ($consumidos < 0 || $consumidos > $this->intentosPorCiclo) {
            throw new InvalidArgumentException('El contador no corresponde al ciclo final.');
        }

        return $aprobado ? 'aprobado' : ($consumidos === $this->intentosPorCiclo ? 'reforzamiento_pendiente' : 'en_curso');
    }

    public function fechaLimite(DateTimeInterface $inicio): CarbonImmutable
    {
        return CarbonImmutable::instance($inicio)->utc()->addMinutes($this->minutos);
    }

    public function dentroDelPlazo(DateTimeInterface $inicio, DateTimeInterface $ahoraServidor): bool
    {
        return $ahoraServidor >= $inicio && $ahoraServidor < $this->fechaLimite($inicio);
    }

    /** IDs y completados deben cargarse de evidencia autorizada de la unidad y ciclo. */
    public function puedeAbrirNuevoCiclo(
        int $consumidos,
        bool $aprobado,
        DateTimeInterface $cierre,
        array $actividadesObligatorias,
        array $completadasEn,
        DateTimeInterface $ahoraServidor,
    ): bool {
        if ($this->estadoFinal($consumidos, $aprobado) !== 'reforzamiento_pendiente'
            || $cierre > $ahoraServidor || $actividadesObligatorias === []) {
            return false;
        }
        foreach ($actividadesObligatorias as $actividadId) {
            $fecha = $completadasEn[$actividadId] ?? null;
            if (! $fecha instanceof DateTimeInterface || $fecha <= $cierre || $fecha > $ahoraServidor) {
                return false;
            }
        }

        return true;
    }
}
