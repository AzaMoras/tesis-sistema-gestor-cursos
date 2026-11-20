<?php

namespace App\Services;

use App\Models\Grupo;
use App\Models\Inscripcion;
use App\Models\Participante;
use App\Models\ParticipanteRequisito;
use App\Models\Rol;
use App\Models\User;
use App\Models\UsuarioRol;
use App\Services\Soporte\AuditoriaService;
use App\Services\Soporte\AutorizacionInstitucional;
use App\Services\Soporte\DecisionOperacionPendiente;
use App\Services\Soporte\Importes;
use App\Services\Soporte\ReglasOperacion;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class InscripcionService
{
    public function __construct(
        private readonly AutorizacionInstitucional $acceso,
        private readonly AuditoriaService $auditoria,
        private readonly ReglasOperacion $reglas,
    ) {}

    /** Autorregistro: la pertenencia mínima solo se confirma junto con la inscripción. */
    public function inscribirGratuita(User $actor, int $cursoId, int $grupoId): Inscripcion
    {
        return DB::transaction(function () use ($actor, $cursoId, $grupoId): Inscripcion {
            $usuario = User::query()->lockForUpdate()->find($actor->getKey());
            if (! $actor->exists || ! $usuario || $usuario->estado_cuenta !== 'activa') {
                throw new AuthorizationException('Se requiere una cuenta activa.');
            }
            $participante = $this->perfilParticipante($usuario, true);
            $curso = app(CatalogoCursosService::class)->cursos()->findOrFail($cursoId);
            $this->acceso->institucion($curso->institucion_id)->newQuery()
                ->whereKey($curso->institucion_id)->where('estado', 'activa')->sharedLock()->firstOrFail();
            $grupo = $this->acceso->grupo($curso->institucion_id, $grupoId, true);
            abort_unless((int) $grupo->edicion->curso_id === $cursoId, 404);
            $this->exigirGratuidad($grupo);

            // No se crea un rol global desde una petición pública.
            $rol = Rol::query()->where('clave', 'participante')->sharedLock()->first();
            if (! $rol) {
                throw ValidationException::withMessages(['inscripcion' => 'La institución todavía no ha habilitado las inscripciones gratuitas. Solicita revisión institucional. No se guardaron cambios.']);
            }
            $asignacion = UsuarioRol::query()->where('user_id', $usuario->id)->where('rol_id', $rol->id)
                ->where('institucion_id', $curso->institucion_id)->lockForUpdate()->first();
            if (! $asignacion) {
                $asignacion = new UsuarioRol;
                $asignacion->forceFill(['user_id' => $usuario->id, 'rol_id' => $rol->id,
                    'institucion_id' => $curso->institucion_id, 'asignado_por' => $usuario->id])->save();
                $this->auditoria->registrar($usuario, $curso->institucion_id, 'usuario_rol.autovincular_participante', $asignacion,
                    ['origen' => 'inscripcion_gratuita', 'rol' => 'participante', 'grupo_id' => $grupoId]);
            }

            // Conserva los bloqueos, requisitos, cupo, unicidad y bitácora del contrato original.
            return $this->inscribir($usuario, $curso->institucion_id, $participante->id, $grupoId);
        }, 3);
    }

    public function perfilParticipante(User $usuario, bool $bloquear = false): Participante
    {
        $perfil = Participante::query()->where('user_id', $usuario->id)
            ->when($bloquear, fn ($q) => $q->lockForUpdate())->first();
        if (! $perfil) {
            throw ValidationException::withMessages(['participante' => 'Tu cuenta no tiene un perfil de participante activo. Solicita revisión institucional; no se creará otro perfil automáticamente.']);
        }

        return $perfil;
    }

    /** Orientación de lectura; la decisión definitiva siempre se repite bajo bloqueo. */
    public function estadoGratuita(Grupo $grupo, ?User $usuario): array
    {
        if ($usuario && $usuario->estado_cuenta !== 'activa') {
            return ['permitida' => false, 'mensaje' => 'Se necesita una cuenta activa para inscribirse.'];
        }
        try {
            $participante = $usuario ? $this->perfilParticipante($usuario) : null;
            if ($participante && ($inscripcion = $grupo->inscripciones()->where('participante_id', $participante->id)->first(['estado']))) {
                return ['permitida' => false, 'mensaje' => 'Ya tienes una inscripción en este grupo. Estado: '.$inscripcion->estado.'. Consulta Mis cursos.'];
            }
            $this->exigirGratuidad($grupo);
            $this->exigirApertura($grupo);
            $this->exigirCupo($grupo, bloquear: false);
            $obligatorios = $grupo->edicion->curso->requisitos()->wherePivot('obligatorio', true)->exists();
            if ($obligatorios && ! $participante) {
                return ['permitida' => true, 'mensaje' => 'Inicia sesión para comprobar los requisitos obligatorios antes de confirmar.'];
            }
            if ($participante) {
                $this->exigirRequisitos($grupo, $participante, $grupo->edicion->curso->institucion_id, false);
            }
            if (! Rol::query()->where('clave', 'participante')->exists()) {
                return ['permitida' => false, 'mensaje' => 'La institución todavía no ha habilitado las inscripciones gratuitas. Solicita revisión institucional.'];
            }
            $this->reglas->estado('inscripcion.confirmada');

            return ['permitida' => true, 'mensaje' => 'Inscripción gratuita disponible. El cupo se volverá a comprobar al confirmar.'];
        } catch (ValidationException $error) {
            return ['permitida' => false, 'mensaje' => collect($error->errors())->flatten()->first()];
        } catch (DecisionOperacionPendiente) {
            return ['permitida' => false, 'mensaje' => 'La institución debe completar una política de inscripción antes de habilitar este grupo.'];
        }
    }

    private function exigirGratuidad(Grupo $grupo): void
    {
        if (Importes::centavos($grupo->edicion->precio, 'precio') !== 0) {
            throw ValidationException::withMessages(['precio' => 'Esta edición es de pago. Su inscripción todavía no está habilitada.']);
        }
        if ($grupo->cupo_maximo === null || $grupo->cupo_maximo < 1) {
            throw ValidationException::withMessages(['cupo' => 'El grupo necesita un cupo positivo definido antes de admitir inscripciones.']);
        }
        if ($grupo->edicion->curso->estado !== config('operacion.publicacion.cursos')
            || ! in_array($grupo->edicion->modalidad, array_keys(CatalogoCursosService::MODALIDADES), true)) {
            throw ValidationException::withMessages(['curso' => 'El curso no tiene una oferta pública válida.']);
        }
    }

    public function inscribir(User $actor, int $institucionId, int $participanteId, int $grupoId): Inscripcion
    {
        try {
            return DB::transaction(function () use ($actor, $institucionId, $participanteId, $grupoId): Inscripcion {
                $actor = $this->acceso->usuario($actor, $institucionId);
                $grupo = $this->acceso->grupo($institucionId, $grupoId, true);
                $participante = $this->acceso->participante($institucionId, $participanteId);
                $this->acceso->exigirPropietarioOPermiso($actor, $institucionId, $participante, 'inscripciones.gestionar');
                $this->exigirApertura($grupo);
                $this->exigirRequisitos($grupo, $participante, $institucionId);

                if ($grupo->inscripciones()->where('participante_id', $participanteId)->lockForUpdate()->first()) {
                    throw ValidationException::withMessages(['inscripcion' => 'Ya existe una inscripción de este participante al grupo, incluso si fue cancelada.']);
                }
                $this->exigirCupo($grupo);
                $precio = Importes::centavos($grupo->edicion->precio, 'precio');
                if (! preg_match('/\A[A-Z]{3}\z/', $grupo->edicion->moneda)) {
                    throw ValidationException::withMessages(['moneda' => 'La edición no tiene una clave de moneda válida de tres letras.']);
                }

                $inscripcion = new Inscripcion;
                $inscripcion->participante_id = $participanteId;
                $inscripcion->grupo_id = $grupoId;
                $inscripcion->precio_acordado = Importes::decimal($precio);
                if ($precio === 0) {
                    $inscripcion->estado = $this->reglas->estado('inscripcion.confirmada');
                    $inscripcion->fecha_inscripcion = now();
                } else {
                    $inscripcion->estado = $this->reglas->estado('inscripcion.pendiente_pago');
                    $inscripcion->reserva_vence_at = now()->addMinutes($this->reglas->minutosDeReserva());
                }
                $inscripcion->save();
                $this->auditoria->registrar($actor, $institucionId, 'inscripciones.crear', $inscripcion, ['estado' => $inscripcion->estado]);

                return $inscripcion;
            }, 3);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['inscripcion' => 'La inscripción ya fue registrada por otra operación.']);
        }
    }

    /** Paso separado de verificar un pago; nunca reactiva una reserva vencida. */
    public function confirmarConPagosVerificados(User $actor, int $institucionId, int $inscripcionId): Inscripcion
    {
        return DB::transaction(function () use ($actor, $institucionId, $inscripcionId): Inscripcion {
            $actor = $this->acceso->usuario($actor, $institucionId, 'pagos.verificar');
            $inscripcion = $this->acceso->inscripcion($institucionId, $inscripcionId, true);
            $confirmada = $this->reglas->estado('inscripcion.confirmada');
            if ($inscripcion->estado === $confirmada) {
                return $inscripcion;
            }
            if ($inscripcion->estado !== $this->reglas->estado('inscripcion.pendiente_pago')
                || $inscripcion->reserva_vence_at === null || $inscripcion->reserva_vence_at->lte(now())) {
                throw ValidationException::withMessages(['inscripcion' => 'La inscripción no tiene una reserva de pago vigente. No se recuperará un lugar automáticamente.']);
            }
            $participante = $this->acceso->participante($institucionId, $inscripcion->participante_id);
            $this->exigirApertura($inscripcion->grupo, false);
            $this->exigirRequisitos($inscripcion->grupo, $participante, $institucionId);
            $this->exigirCupo($inscripcion->grupo, $inscripcion->getKey());

            $total = 0;
            $pagos = $inscripcion->pagos()->where('estado', $this->reglas->estado('pago.verificado'))->lockForUpdate()->get();
            foreach ($pagos as $pago) {
                if ($pago->verificado_por === null || $pago->fecha_pago === null || $pago->fecha_pago->gt(now())
                    || $pago->moneda !== $inscripcion->grupo->edicion->moneda) {
                    throw ValidationException::withMessages(['pago' => 'Hay pagos sin verificación válida o con moneda incompatible.']);
                }
                $monto = Importes::centavos($pago->monto);
                $reembolso = Importes::centavos($pago->monto_reembolsado);
                if ($reembolso > $monto || $total > PHP_INT_MAX - $monto) {
                    throw ValidationException::withMessages(['pago' => 'Los importes de los pagos son inconsistentes.']);
                }
                $total += $monto - $reembolso;
            }
            if ($total < Importes::centavos($inscripcion->precio_acordado)) {
                throw ValidationException::withMessages(['pago' => 'Los pagos verificados no cubren el precio acordado.']);
            }

            $inscripcion->estado = $confirmada;
            $inscripcion->fecha_inscripcion = now();
            $inscripcion->reserva_vence_at = null;
            $inscripcion->save();
            $this->auditoria->registrar($actor, $institucionId, 'inscripciones.confirmar', $inscripcion);

            return $inscripcion;
        }, 3);
    }

    public function cancelar(User $actor, int $institucionId, int $inscripcionId): Inscripcion
    {
        return DB::transaction(function () use ($actor, $institucionId, $inscripcionId): Inscripcion {
            $actor = $this->acceso->usuario($actor, $institucionId);
            $inscripcion = $this->acceso->inscripcion($institucionId, $inscripcionId, true);
            $participante = $this->acceso->participante($institucionId, $inscripcion->participante_id);
            $this->acceso->exigirPropietarioOPermiso($actor, $institucionId, $participante, 'inscripciones.gestionar');
            $cancelada = $this->reglas->estado('inscripcion.cancelada');
            if ($inscripcion->estado === $cancelada) {
                return $inscripcion;
            }
            if (! in_array($inscripcion->estado, [$this->reglas->estado('inscripcion.pendiente_pago'), $this->reglas->estado('inscripcion.confirmada')], true)) {
                throw ValidationException::withMessages(['inscripcion' => 'El estado actual no admite cancelación por este flujo.']);
            }
            if ($inscripcion->pagos()->where('estado', '!=', $this->reglas->estado('pago.pendiente'))->lockForUpdate()->first()
                || $inscripcion->entregas()->exists() || $inscripcion->asistencias()->exists() || $inscripcion->constancia()->exists()) {
                throw ValidationException::withMessages(['inscripcion' => 'La cancelación con pagos revisados o historial académico requiere una política específica.']);
            }

            $inscripcion->estado = $cancelada;
            $inscripcion->fecha_cancelacion = now();
            $inscripcion->reserva_vence_at = null;
            $inscripcion->save();
            $this->auditoria->registrar($actor, $institucionId, 'inscripciones.cancelar', $inscripcion);

            return $inscripcion;
        }, 3);
    }

    private function exigirApertura(Grupo $grupo, bool $nuevaInscripcion = true): void
    {
        foreach (['curso' => $grupo->edicion->curso, 'edicion' => $grupo->edicion, 'grupo' => $grupo] as $tipo => $registro) {
            if (! in_array($registro->estado, $this->reglas->estadosDeApertura($tipo), true)) {
                throw ValidationException::withMessages([$tipo => 'El estado de '.$tipo.' no permite esta inscripción.']);
            }
        }
        if ($grupo->edicion->curso->categoria?->estado !== 'activa') {
            throw ValidationException::withMessages(['categoria' => 'La categoría del curso no está activa.']);
        }
        if ($grupo->edicion->fecha_inicio === null || $grupo->edicion->fecha_fin === null
            || $grupo->edicion->fecha_inicio->gt($grupo->edicion->fecha_fin) || $grupo->edicion->fecha_fin->lt(today())) {
            throw ValidationException::withMessages(['edicion' => 'Las fechas de la edición no permiten esta operación.']);
        }
        if ($nuevaInscripcion && $grupo->edicion->cierre_inscripciones_at?->lte(now())) {
            throw ValidationException::withMessages(['edicion' => 'El plazo de inscripción ya cerró.']);
        }
    }

    private function exigirRequisitos(Grupo $grupo, Participante $participante, int $institucionId, bool $bloquear = true): void
    {
        $requisitos = $grupo->edicion->curso->requisitos()->wherePivot('obligatorio', true)
            ->when($bloquear, fn ($q) => $q->lockForUpdate())->get();
        foreach ($requisitos as $requisito) {
            if ((int) $requisito->institucion_id !== $institucionId) {
                throw ValidationException::withMessages(['requisitos' => 'Un requisito pertenece a otra institución.']);
            }
            if ($requisito->estado !== 'activo') {
                throw ValidationException::withMessages(['requisitos' => 'Un requisito obligatorio necesita revisión institucional antes de inscribirte.']);
            }
            $validacion = ParticipanteRequisito::query()->where('participante_id', $participante->getKey())
                ->where('requisito_id', $requisito->getKey())->when($bloquear, fn ($q) => $q->lockForUpdate())->first();
            if (! $validacion || $validacion->estado !== $this->reglas->estado('requisito.validado')
                || $validacion->revisado_por === null || $validacion->fecha_validacion === null
                || $validacion->fecha_validacion->gt(now()) || ($requisito->requiere_documento && ! $validacion->ruta_evidencia)) {
                throw ValidationException::withMessages(['requisitos' => 'Tienes requisitos obligatorios pendientes de validación institucional. Su revisión todavía no está disponible en esta interfaz.']);
            }
        }
    }

    private function exigirCupo(Grupo $grupo, ?int $excluirInscripcion = null, bool $bloquear = true): void
    {
        if ($grupo->cupo_maximo === null) {
            if ($this->reglas->cupoNuloEsIlimitado()) {
                return;
            }
            throw ValidationException::withMessages(['cupo' => 'El grupo no tiene un cupo definido.']);
        }

        $pendiente = $this->reglas->estado('inscripcion.pendiente_pago');
        $cancelada = $this->reglas->estado('inscripcion.cancelada');
        // Lectura bloqueante actual: un COUNT normal puede ver una instantánea vieja
        // bajo REPEATABLE READ, aunque se haya esperado por el bloqueo del grupo.
        $ocupaciones = $grupo->inscripciones()->where('estado', '!=', $cancelada)
            ->when($excluirInscripcion !== null, fn ($query) => $query->whereKeyNot($excluirInscripcion))
            ->where(fn ($query) => $query->where('estado', '!=', $pendiente)
                ->orWhereNull('reserva_vence_at')->orWhere('reserva_vence_at', '>', now()))
            ->when($bloquear, fn ($q) => $q->lockForUpdate())->get(['inscripciones.id']);

        if ($ocupaciones->count() >= $grupo->cupo_maximo) {
            throw ValidationException::withMessages(['cupo' => 'El grupo ya no tiene lugares disponibles.']);
        }
    }
}
