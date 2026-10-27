<?php

namespace App\Services\Soporte;

use App\Models\Curso;
use App\Models\Edicion;
use App\Models\Grupo;
use App\Models\Inscripcion;
use App\Models\Institucion;
use App\Models\Participante;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use LogicException;

final class AutorizacionInstitucional
{
    /** Recarga al actor: no confía en atributos o relaciones precargadas. */
    public function usuario(User $actor, int $institucionId, ?string $permiso = null): User
    {
        if (! $actor->exists || ! $actor->getKey()) {
            throw new AuthorizationException('Se necesita una cuenta autenticada.');
        }

        $usuario = User::query()->find($actor->getKey());
        if (! $usuario || $usuario->estado_cuenta !== 'activa') {
            throw new AuthorizationException('La cuenta no está activa.');
        }

        $this->institucion($institucionId);
        if (! $usuario->rolesEnInstitucion($institucionId)->exists()) {
            throw new AuthorizationException('No tienes acceso a esta institución.');
        }
        if ($permiso !== null && ! $usuario->permisosEnInstitucion($institucionId)->where('permisos.clave', $permiso)->exists()) {
            throw new AuthorizationException('No tienes el permiso '.$permiso.' en esta institución.');
        }

        return $usuario;
    }

    public function institucion(int $institucionId): Institucion
    {
        return Institucion::query()->where('estado', 'activa')->findOrFail($institucionId);
    }

    public function exigirRol(User $actor, int $institucionId, string $rol): void
    {
        if (! $actor->rolesEnInstitucion($institucionId)->where('roles.clave', $rol)->exists()) {
            throw new AuthorizationException('No tienes el rol requerido en esta institución.');
        }
    }

    public function participante(int $institucionId, int $participanteId): Participante
    {
        $participante = Participante::query()->with('usuario')->findOrFail($participanteId);
        $usuario = $participante->usuario;
        if (! $usuario || $usuario->estado_cuenta !== 'activa') {
            throw new AuthorizationException('El participante no tiene una cuenta activa.');
        }
        $this->exigirRol($usuario, $institucionId, 'participante');

        return $participante;
    }

    public function exigirPropietarioOPermiso(User $actor, int $institucionId, Participante $participante, string $permiso): void
    {
        if ((int) $participante->user_id !== (int) $actor->getKey()) {
            $this->usuario($actor, $institucionId, $permiso);
        } else {
            $this->exigirRol($actor, $institucionId, 'participante');
        }
    }

    /** Orden común de bloqueo: edición -> grupo -> inscripción -> pago/entrega. */
    public function grupo(int $institucionId, int $grupoId, bool $bloquear = false): Grupo
    {
        if ($bloquear && DB::transactionLevel() === 0) {
            throw new LogicException('Los bloqueos requieren una transacción.');
        }

        $referencia = Grupo::query()->whereHas('edicion.curso', fn ($query) => $query->where('institucion_id', $institucionId))
            ->findOrFail($grupoId);
        $edicion = Edicion::query()->whereHas('curso', fn ($query) => $query->where('institucion_id', $institucionId))
            ->when($bloquear, fn ($query) => $query->lockForUpdate())->findOrFail($referencia->edicion_id);
        $grupo = Grupo::query()->where('edicion_id', $edicion->getKey())
            ->when($bloquear, fn ($query) => $query->lockForUpdate())->findOrFail($grupoId);
        $curso = Curso::query()->where('institucion_id', $institucionId)
            ->when($bloquear, fn ($query) => $query->sharedLock())->findOrFail($edicion->curso_id);

        // Las FK no garantizan que la categoría pertenezca a la misma institución.
        if (! $curso->categoria()->where('institucion_id', $institucionId)
            ->when($bloquear, fn ($query) => $query->sharedLock())->first()) {
            throw new AuthorizationException('La categoría del curso no corresponde a esta institución.');
        }

        $edicion->setRelation('curso', $curso);
        $grupo->setRelation('edicion', $edicion);

        return $grupo;
    }

    public function inscripcion(int $institucionId, int $inscripcionId, bool $bloquear = false): Inscripcion
    {
        $referencia = Inscripcion::query()
            ->whereHas('grupo.edicion.curso', fn ($query) => $query->where('institucion_id', $institucionId))
            ->findOrFail($inscripcionId);
        $grupo = $this->grupo($institucionId, $referencia->grupo_id, $bloquear);
        $inscripcion = Inscripcion::query()->where('grupo_id', $grupo->getKey())
            ->when($bloquear, fn ($query) => $query->lockForUpdate())->findOrFail($inscripcionId);
        $inscripcion->setRelation('grupo', $grupo);
        $inscripcion->setRelation('participante', $this->participante($institucionId, $inscripcion->participante_id));

        return $inscripcion;
    }

    public function exigirGrupoInstructor(User $actor, int $institucionId, Grupo $grupo): void
    {
        $roles = $actor->rolesEnInstitucion($institucionId)->pluck('roles.clave');
        if ($roles->contains('instructor') && $roles->intersect(['administrador', 'coordinador'])->isEmpty()
            && ! $grupo->instructores()->where('instructores.user_id', $actor->getKey())->exists()) {
            throw new AuthorizationException('El instructor no está asignado a este grupo.');
        }
    }
}
