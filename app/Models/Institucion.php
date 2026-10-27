<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Institucion extends Model
{
    use SoftDeletes;

    protected $table = 'instituciones';

    protected $fillable = [
        'nombre',
        'slug',
        'correo_contacto',
    ];

    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class, 'sede_id');
    }

    public function categorias(): HasMany
    {
        return $this->hasMany(CategoriaCurso::class, 'institucion_id');
    }

    public function cursos(): HasMany
    {
        return $this->hasMany(Curso::class, 'institucion_id');
    }

    public function requisitos(): HasMany
    {
        return $this->hasMany(Requisito::class, 'institucion_id');
    }

    public function solicitudes(): HasMany
    {
        return $this->hasMany(SolicitudRegistro::class, 'institucion_id');
    }

    public function bitacoras(): HasMany
    {
        return $this->hasMany(Bitacora::class, 'institucion_id');
    }

    public function inactivos(): HasMany
    {
        return $this->hasMany(Inactivo::class, 'institucion_id');
    }

    public function usuarios(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'usuario_rol', 'institucion_id', 'user_id')
            ->using(UsuarioRol::class)
            ->withPivot('id', 'rol_id', 'asignado_por')
            ->withTimestamps();
    }

}
