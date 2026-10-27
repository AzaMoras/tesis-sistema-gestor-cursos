<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    // El estado de cuenta, la verificación y los accesos se asignan explícitamente.

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'ultimo_acceso_at' => 'datetime',
        ];
    }

    /** Todas las asignaciones; para autorización usar rolesEnInstitucion(). */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Rol::class, 'usuario_rol', 'user_id', 'rol_id')
            ->using(UsuarioRol::class)
            ->withPivot('id', 'institucion_id', 'asignado_por')
            ->withTimestamps();
    }

    public function rolesEnInstitucion(int $institucionId): BelongsToMany
    {
        return $this->roles()->withPivotValue('institucion_id', $institucionId);
    }

    /** Consulta de permisos sin mezclar asignaciones de otras instituciones. */
    public function permisosEnInstitucion(int $institucionId): Builder
    {
        return Permiso::query()->whereHas('roles.usuarios', function (Builder $query) use ($institucionId): void {
            $query->where('users.id', $this->getKey())
                ->where('usuario_rol.institucion_id', $institucionId);
        });
    }

    public function instituciones(): BelongsToMany
    {
        return $this->belongsToMany(Institucion::class, 'usuario_rol', 'user_id', 'institucion_id')
            ->using(UsuarioRol::class)
            ->withPivot('id', 'rol_id', 'asignado_por')
            ->withTimestamps();
    }

    public function participante(): HasOne
    {
        return $this->hasOne(Participante::class, 'user_id');
    }

    public function instructor(): HasOne
    {
        return $this->hasOne(Instructor::class, 'user_id');
    }

    public function rolesAsignados(): HasMany
    {
        return $this->hasMany(UsuarioRol::class, 'asignado_por');
    }

    public function solicitudesRevisadas(): HasMany
    {
        return $this->hasMany(SolicitudRegistro::class, 'revisado_por');
    }

    public function solicitudesVinculadas(): HasMany
    {
        return $this->hasMany(SolicitudRegistro::class, 'usuario_vinculado_id');
    }

    public function instructoresValidados(): HasMany
    {
        return $this->hasMany(Instructor::class, 'validado_por');
    }

    public function requisitosRevisados(): HasMany
    {
        return $this->hasMany(ParticipanteRequisito::class, 'revisado_por');
    }

    public function bitacoras(): HasMany
    {
        return $this->hasMany(Bitacora::class, 'usuario_id');
    }

    public function desactivaciones(): HasMany
    {
        return $this->hasMany(Inactivo::class, 'desactivado_por');
    }

    public function restauraciones(): HasMany
    {
        return $this->hasMany(Inactivo::class, 'restaurado_por');
    }

    public function evaluacionesCalificadas(): HasMany
    {
        return $this->hasMany(Evaluacion::class, 'calificado_por');
    }

    public function asistenciasRegistradas(): HasMany
    {
        return $this->hasMany(Asistencia::class, 'registrado_por');
    }

    public function pagosVerificados(): HasMany
    {
        return $this->hasMany(Pago::class, 'verificado_por');
    }
}
