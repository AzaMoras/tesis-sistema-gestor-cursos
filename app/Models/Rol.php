<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Rol extends Model
{
    protected $table = 'roles';

    protected $fillable = [
        'nombre',
        'descripcion',
    ];

    public function usuarios(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'usuario_rol', 'rol_id', 'user_id')
            ->using(UsuarioRol::class)
            ->withPivot('id', 'institucion_id', 'asignado_por')
            ->withTimestamps();
    }

    public function usuariosEnInstitucion(int $institucionId): BelongsToMany
    {
        return $this->usuarios()->withPivotValue('institucion_id', $institucionId);
    }

    public function permisos(): BelongsToMany
    {
        return $this->belongsToMany(Permiso::class, 'rol_permiso', 'rol_id', 'permiso_id')
            ->using(RolPermiso::class);
    }

}
