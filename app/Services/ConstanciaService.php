<?php

namespace App\Services;

use App\Models\Constancia;
use App\Models\User;
use App\Services\Soporte\AutorizacionInstitucional;
use App\Services\Soporte\ReglasOperacion;

final class ConstanciaService
{
    public function __construct(
        private readonly AutorizacionInstitucional $acceso,
        private readonly EvaluacionService $evaluaciones,
        private readonly ReglasOperacion $reglas,
    ) {}

    /** Diagnóstico: no equivale a acreditar ni a emitir un documento. */
    public function comprobarElegibilidad(User $actor, int $institucionId, int $inscripcionId): array
    {
        $this->acceso->usuario($actor, $institucionId, 'constancias.emitir');
        $inscripcion = $this->acceso->inscripcion($institucionId, $inscripcionId);
        $this->acceso->participante($institucionId, $inscripcion->participante_id);
        $desglose = $this->evaluaciones->desgloseParaConstancia($actor, $institucionId, $inscripcionId);
        $motivos = $desglose['pendientes'];
        $estadoConfirmada = $this->reglas->estadoOpcional('inscripcion.confirmada');
        if ($estadoConfirmada === null) {
            $motivos[] = 'Definir el estado inscripcion.confirmada para comprobar la inscripción.';
        } elseif ($inscripcion->estado !== $estadoConfirmada || $inscripcion->fecha_inscripcion === null) {
            $motivos[] = 'La inscripción no está confirmada.';
        }
        $faltantes = 0;
        foreach ($desglose['actividades'] as $actividad) {
            if (! array_filter($actividad['intentos'], fn (array $intento): bool => $intento['calificacion'] !== null)) {
                $faltantes++;
            }
        }
        if ($faltantes > 0) {
            $motivos[] = 'Hay '.$faltantes.' actividades sin una evaluación registrada.';
        }

        $sesiones = $inscripcion->grupo->sesiones()->get();
        $asistencias = $inscripcion->asistencias()->get();
        $sesionesValidas = $sesiones->modelKeys();
        if ($asistencias->contains(fn ($asistencia): bool => ! in_array((int) $asistencia->sesion_id, $sesionesValidas, true)
            || $asistencia->fecha_registro->gt(now()))) {
            $motivos[] = 'El expediente tiene asistencias de otro grupo o con fechas inconsistentes.';
        }
        $motivos[] = 'Definir los estados de sesiones y asistencias que cuentan para el porcentaje y los casos sin sesiones.';
        $motivos[] = 'Definir las condiciones de finalización, el folio y la generación real del PDF antes de emitir.';
        $existente = $inscripcion->constancia()->first();

        return [
            'inscripcion_id' => $inscripcionId,
            'elegible' => false,
            'requiere_definiciones' => true,
            'motivos' => array_values(array_unique($motivos)),
            'calificacion_final' => null,
            'porcentaje_asistencia' => null,
            'calificacion_minima' => $inscripcion->grupo->edicion->calificacion_minima,
            'asistencia_minima' => $inscripcion->grupo->edicion->asistencia_minima,
            'sesiones_por_estado' => $sesiones->countBy('estado')->all(),
            'asistencias_por_estado' => $asistencias->countBy('estado')->all(),
            'constancia_existente_id' => $existente?->getKey(),
            'desglose_academico' => $desglose,
        ];
    }

    public function obtenerExistente(User $actor, int $institucionId, int $inscripcionId): ?Constancia
    {
        $actor = $this->acceso->usuario($actor, $institucionId);
        $inscripcion = $this->acceso->inscripcion($institucionId, $inscripcionId);
        $participante = $this->acceso->participante($institucionId, $inscripcion->participante_id);
        $this->acceso->exigirPropietarioOPermiso($actor, $institucionId, $participante, 'constancias.emitir');

        return $inscripcion->constancia()->first();
    }
}
