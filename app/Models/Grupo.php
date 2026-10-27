<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Grupo extends Model
{
    use SoftDeletes;

    protected $table = 'grupos';

    protected $fillable = [
        'codigo',
        'cupo_maximo',
    ];

    protected function casts(): array
    {
        return [
            'cupo_maximo' => 'integer',
        ];
    }

    public function edicion(): BelongsTo
    {
        return $this->belongsTo(Edicion::class, 'edicion_id');
    }

    public function sesiones(): HasMany
    {
        return $this->hasMany(Sesion::class, 'grupo_id');
    }

    public function inscripciones(): HasMany
    {
        return $this->hasMany(Inscripcion::class, 'grupo_id');
    }

    public function listaEspera(): HasMany
    {
        return $this->hasMany(ListaEspera::class, 'grupo_id');
    }

    public function actividades(): HasMany
    {
        return $this->hasMany(Actividad::class, 'grupo_id');
    }

    public function instructores(): BelongsToMany
    {
        return $this->belongsToMany(Instructor::class, 'grupo_instructor', 'grupo_id', 'instructor_id')
            ->using(GrupoInstructor::class)
            ->withPivot('funcion', 'fecha_asignacion');
    }

}
