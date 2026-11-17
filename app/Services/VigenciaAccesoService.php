<?php

namespace App\Services;

use App\Models\Inscripcion;
use App\Models\User;
use App\Services\Soporte\AuditoriaService;
use App\Services\Soporte\AutorizacionInstitucional;
use App\Services\Soporte\PoliticaVigencia;
use App\Services\Soporte\ReglasOperacion;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class VigenciaAccesoService
{
    public function __construct(
        private readonly AutorizacionInstitucional $acceso,
        private readonly AuditoriaService $auditoria,
        private readonly ReglasOperacion $reglas,
    ) {}

    /** Operación administrativa explícita; no aplica políticas retroactivamente. */
    public function activar(User $actor, int $institucionId, int $inscripcionId): Inscripcion
    {
        return DB::transaction(function () use ($actor, $institucionId, $inscripcionId): Inscripcion {
            $actor = $this->acceso->usuario($actor, $institucionId, 'inscripciones.gestionar');
            $inscripcion = $this->acceso->inscripcion($institucionId, $inscripcionId, true);
            $this->acceso->exigirGrupoInstructor($actor, $institucionId, $inscripcion->grupo);
            $this->exigirConfirmada($inscripcion);
            if ($inscripcion->acceso_inicia_at !== null) {
                return $inscripcion;
            }
            if ($inscripcion->acceso_vence_at !== null || $inscripcion->prorroga_gratuita_aplicada_at !== null) {
                throw ValidationException::withMessages(['vigencia' => 'La inscripción tiene fechas de acceso inconsistentes.']);
            }
            $edicion = $inscripcion->grupo->edicion;
            if ($edicion->tipo_vigencia === null) {
                throw ValidationException::withMessages(['vigencia' => 'La edición aún no tiene una política de acceso definida.']);
            }
            $politica = new PoliticaVigencia($edicion->tipo_vigencia, $edicion->duracion_acceso_dias, $edicion->prorroga_gratuita_dias);
            $inscripcion->acceso_inicia_at = CarbonImmutable::now('UTC');
            $inscripcion->acceso_vence_at = $politica->vencimiento($inscripcion->acceso_inicia_at);
            $inscripcion->prorroga_gratuita_dias = $politica->diasProrroga;
            $inscripcion->save();
            $this->auditoria->registrar($actor, $institucionId, 'vigencia.activar', $inscripcion, [
                'tipo' => $politica->tipo, 'dias' => $politica->dias, 'dias_prorroga' => $politica->diasProrroga,
                'inicio' => $inscripcion->acceso_inicia_at->toIso8601String(),
                'vencimiento' => $inscripcion->acceso_vence_at?->toIso8601String(),
            ]);

            return $inscripcion;
        }, 3);
    }

    /** Llamar en cada futura escritura de entrega o intento, dentro de su transacción. */
    public function exigirPuedeIniciar(User $actor, int $institucionId, int $inscripcionId): Inscripcion
    {
        $actor = $this->acceso->usuario($actor, $institucionId);
        $inscripcion = $this->acceso->inscripcion($institucionId, $inscripcionId, DB::transactionLevel() > 0);
        $this->acceso->exigirGrupoInstructor($actor, $institucionId, $inscripcion->grupo);
        $this->acceso->exigirPropietarioOPermiso($actor, $institucionId, $inscripcion->participante, 'inscripciones.gestionar');
        $this->exigirConfirmada($inscripcion);
        $ahora = CarbonImmutable::now('UTC');
        if ($inscripcion->acceso_inicia_at === null || $inscripcion->acceso_inicia_at->gt($ahora)
            || ($inscripcion->acceso_vence_at !== null && $inscripcion->acceso_vence_at->lte($ahora))) {
            throw ValidationException::withMessages(['vigencia' => 'El acceso no está activado o ya venció. Puedes conservar y consultar tu historial.']);
        }

        return $inscripcion;
    }

    /** La invocación representa una solicitud explícita; no genera pagos ni cargos. */
    public function aplicarProrrogaGratuita(User $actor, int $institucionId, int $inscripcionId): Inscripcion
    {
        return DB::transaction(function () use ($actor, $institucionId, $inscripcionId): Inscripcion {
            $actor = $this->acceso->usuario($actor, $institucionId);
            $inscripcion = $this->acceso->inscripcion($institucionId, $inscripcionId, true);
            $this->acceso->exigirGrupoInstructor($actor, $institucionId, $inscripcion->grupo);
            $this->acceso->exigirPropietarioOPermiso($actor, $institucionId, $inscripcion->participante, 'inscripciones.gestionar');
            $this->exigirConfirmada($inscripcion);
            $ahora = CarbonImmutable::now('UTC');
            if ($inscripcion->acceso_inicia_at === null || $inscripcion->acceso_inicia_at->gt($ahora)
                || $inscripcion->acceso_vence_at === null || $inscripcion->acceso_vence_at->lte($inscripcion->acceso_inicia_at)
                || $inscripcion->prorroga_gratuita_dias === null || $inscripcion->prorroga_gratuita_dias < 1
                || $inscripcion->prorroga_gratuita_aplicada_at !== null) {
                throw ValidationException::withMessages(['prorroga' => 'La prórroga no está habilitada o ya fue utilizada.']);
            }
            $anterior = $inscripcion->acceso_vence_at;
            // Tras vencer, los días empiezan en la solicitud aceptada; antes, en el vencimiento.
            $base = $ahora->greaterThan($anterior) ? $ahora : $anterior;
            $inscripcion->acceso_vence_at = $base->addDays($inscripcion->prorroga_gratuita_dias);
            $inscripcion->prorroga_gratuita_aplicada_at = $ahora;
            $inscripcion->save();
            $this->auditoria->registrar($actor, $institucionId, 'vigencia.prorroga_gratuita', $inscripcion, [
                'vencimiento_anterior' => $anterior->toIso8601String(),
                'vencimiento_nuevo' => $inscripcion->acceso_vence_at->toIso8601String(),
            ]);

            return $inscripcion;
        }, 3);
    }

    private function exigirConfirmada(Inscripcion $inscripcion): void
    {
        if ($inscripcion->estado !== $this->reglas->estado('inscripcion.confirmada')
            || $inscripcion->fecha_inscripcion === null || $inscripcion->fecha_inscripcion->gt(now())
            || $inscripcion->fecha_cancelacion !== null) {
            throw ValidationException::withMessages(['inscripcion' => 'Se necesita una inscripción confirmada y no cancelada.']);
        }
    }
}
