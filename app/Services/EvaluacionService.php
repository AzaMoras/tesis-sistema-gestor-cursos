<?php

namespace App\Services;

use App\Models\Actividad;
use App\Models\Entrega;
use App\Models\Evaluacion;
use App\Models\Grupo;
use App\Models\Inscripcion;
use App\Models\User;
use App\Services\Soporte\AuditoriaService;
use App\Services\Soporte\AutorizacionInstitucional;
use App\Services\Soporte\Importes;
use App\Services\Soporte\ReglasOperacion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class EvaluacionService
{
    public function __construct(
        private readonly AutorizacionInstitucional $acceso,
        private readonly AuditoriaService $auditoria,
        private readonly ReglasOperacion $reglas,
    ) {}

    public function registrar(User $actor, int $institucionId, int $entregaId, string $calificacion, ?string $retroalimentacion = null): Evaluacion
    {
        $puntos = Importes::centavos($calificacion, 'calificacion');
        Validator::make(['retroalimentacion' => $retroalimentacion], ['retroalimentacion' => ['nullable', 'string', 'max:4000']])->validate();

        return DB::transaction(function () use ($actor, $institucionId, $entregaId, $puntos, $retroalimentacion): Evaluacion {
            $actor = $this->acceso->usuario($actor, $institucionId, 'evaluaciones.calificar');
            $referencia = Entrega::query()->whereHas('inscripcion.grupo.edicion.curso', fn ($query) => $query->where('institucion_id', $institucionId))
                ->findOrFail($entregaId);
            $inscripcion = $this->acceso->inscripcion($institucionId, $referencia->inscripcion_id, true);
            $this->acceso->exigirGrupoInstructor($actor, $institucionId, $inscripcion->grupo);
            $entrega = Entrega::query()->where('inscripcion_id', $inscripcion->getKey())->lockForUpdate()->findOrFail($entregaId);
            $actividad = Actividad::query()->lockForUpdate()->findOrFail($entrega->actividad_id);
            $this->validarActividad($actividad, $inscripcion->grupo);

            if ($inscripcion->estado !== $this->reglas->estado('inscripcion.confirmada') || $inscripcion->fecha_inscripcion === null
                || $inscripcion->fecha_inscripcion->gt(now()) || $entrega->fecha_entrega->lt($inscripcion->fecha_inscripcion)
                || $entrega->estado !== $this->reglas->estado('entrega.enviada') || $entrega->fecha_entrega->gt(now())
                || $entrega->numero_intento < 1 || $entrega->numero_intento > $actividad->intentos_maximos) {
                throw ValidationException::withMessages(['entrega' => 'La inscripción, el estado o el intento de la entrega no permiten evaluarla.']);
            }
            $maximo = Importes::centavos($actividad->puntaje_maximo, 'puntaje_maximo');
            if ($maximo <= 0 || $puntos > $maximo || $puntos > 99999999) {
                throw ValidationException::withMessages(['calificacion' => 'La calificación debe estar entre cero y el puntaje máximo de la actividad, dentro de DECIMAL(8,2).']);
            }
            $existente = $entrega->evaluacion()->lockForUpdate()->first();
            $decimal = Importes::decimal($puntos);
            if ($existente) {
                if ($existente->fecha_evaluacion->gt(now()) || $existente->fecha_evaluacion->lt($entrega->fecha_entrega)
                    || ($existente->tipo_calificacion === 'manual' && $existente->calificado_por === null)) {
                    throw ValidationException::withMessages(['evaluacion' => 'La evaluación existente tiene datos inconsistentes.']);
                }
                if ($existente->calificacion === $decimal && $existente->retroalimentacion === $retroalimentacion && $existente->tipo_calificacion === 'manual') {
                    return $existente;
                }
                throw ValidationException::withMessages(['evaluacion' => 'La entrega ya fue evaluada. Las correcciones requieren una política de revisión.']);
            }

            $evaluacion = new Evaluacion;
            $evaluacion->entrega_id = $entregaId;
            $evaluacion->calificado_por = $actor->getKey();
            $evaluacion->calificacion = $decimal;
            $evaluacion->retroalimentacion = $retroalimentacion;
            $evaluacion->tipo_calificacion = 'manual';
            $evaluacion->fecha_evaluacion = now();
            $evaluacion->save();
            $this->auditoria->registrar($actor, $institucionId, 'evaluaciones.registrar', $evaluacion, ['calificacion' => $decimal]);

            return $evaluacion;
        }, 3);
    }

    public function desglose(User $actor, int $institucionId, int $inscripcionId): array
    {
        $actor = $this->acceso->usuario($actor, $institucionId);
        $inscripcion = $this->acceso->inscripcion($institucionId, $inscripcionId);
        $participante = $this->acceso->participante($institucionId, $inscripcion->participante_id);
        $this->acceso->exigirPropietarioOPermiso($actor, $institucionId, $participante, 'evaluaciones.calificar');
        if ((int) $participante->user_id !== (int) $actor->getKey()) {
            $this->acceso->exigirGrupoInstructor($actor, $institucionId, $inscripcion->grupo);
        }

        return $this->construirDesglose($inscripcion);
    }

    /** La consulta para emisión usa su propio permiso, sin exigir calificar. */
    public function desgloseParaConstancia(User $actor, int $institucionId, int $inscripcionId): array
    {
        $this->acceso->usuario($actor, $institucionId, 'constancias.emitir');

        return $this->construirDesglose($this->acceso->inscripcion($institucionId, $inscripcionId));
    }

    private function construirDesglose(Inscripcion $inscripcion): array
    {
        $pendientes = [
            'Definir selección de intentos, actividades faltantes, ponderaciones y redondeo de la calificación final.',
            'Definir el origen del plazo_dias y el tratamiento de entregas fuera de plazo.',
        ];
        $estadoEvaluable = $this->reglas->estadoOpcional('actividad.evaluable');
        if ($estadoEvaluable === null) {
            $pendientes[] = 'Definir el estado actividad.evaluable para seleccionar las actividades del expediente.';

            return ['inscripcion_id' => $inscripcion->getKey(), 'actividades' => [], 'calificacion_final' => null, 'pendientes' => $pendientes];
        }

        $actividades = Actividad::query()->where('curso_id', $inscripcion->grupo->edicion->curso_id)
            ->where('estado', $estadoEvaluable)
            ->where(fn ($query) => $query->whereNull('grupo_id')->orWhere('grupo_id', $inscripcion->grupo_id))->orderBy('id')->get();
        $entregas = $inscripcion->entregas()->with('evaluacion')->get()->groupBy('actividad_id');
        $detalle = [];
        if ($entregas->keys()->diff($actividades->modelKeys())->isNotEmpty()) {
            $pendientes[] = 'Hay entregas cuyas actividades no pertenecen al expediente evaluable actual; revisar su historial.';
        }
        foreach ($actividades as $actividad) {
            $this->validarActividad($actividad, $inscripcion->grupo);
            $intentos = [];
            foreach ($entregas->get($actividad->getKey(), collect()) as $entrega) {
                $evaluacion = $entrega->evaluacion;
                $maximo = Importes::centavos($actividad->puntaje_maximo, 'puntaje_maximo');
                $puntos = $evaluacion ? Importes::centavos($evaluacion->calificacion, 'calificacion') : null;
                if ($maximo <= 0 || ($puntos !== null && $puntos > $maximo)
                    || $entrega->numero_intento < 1 || $entrega->numero_intento > $actividad->intentos_maximos
                    || $entrega->fecha_entrega->gt(now())
                    || ($evaluacion && ($evaluacion->fecha_evaluacion->gt(now()) || $evaluacion->fecha_evaluacion->lt($entrega->fecha_entrega)
                        || ($evaluacion->tipo_calificacion === 'manual' && $evaluacion->calificado_por === null)))) {
                    throw ValidationException::withMessages(['evaluacion' => 'El expediente contiene puntajes, fechas o intentos inconsistentes.']);
                }
                $intentos[] = [
                    'entrega_id' => $entrega->getKey(), 'numero_intento' => $entrega->numero_intento,
                    'fecha_entrega' => $entrega->fecha_entrega->toIso8601String(),
                    'calificacion' => $evaluacion?->calificacion,
                    // Porcentaje exacto como cociente; no introduce redondeo académico.
                    'porcentaje_exacto' => $puntos === null ? null : ['numerador' => $puntos * 100, 'denominador' => $maximo],
                ];
            }
            $detalle[] = [
                'actividad_id' => $actividad->getKey(), 'puntaje_maximo' => $actividad->puntaje_maximo,
                'ponderacion' => $actividad->ponderacion, 'plazo_dias' => $actividad->plazo_dias, 'intentos' => $intentos,
            ];
        }
        if ($actividades->isEmpty()) {
            $pendientes[] = 'No hay actividades evaluables; falta definir si esta edición admite acreditación sin actividades.';
        }

        return ['inscripcion_id' => $inscripcion->getKey(), 'actividades' => $detalle, 'calificacion_final' => null, 'pendientes' => $pendientes];
    }

    private function validarActividad(Actividad $actividad, Grupo $grupo): void
    {
        if ((int) $actividad->curso_id !== (int) $grupo->edicion->curso_id
            || ($actividad->grupo_id !== null && (int) $actividad->grupo_id !== (int) $grupo->getKey())
            || ($actividad->modulo_id !== null && ! $actividad->modulo()->where('curso_id', $actividad->curso_id)->exists())
            || $actividad->estado !== $this->reglas->estado('actividad.evaluable')) {
            throw ValidationException::withMessages(['actividad' => 'La actividad o su módulo no corresponden al curso, grupo o estado evaluable.']);
        }
    }
}
