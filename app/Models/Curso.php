<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Curso extends Model
{
    use SoftDeletes;

    protected $table = 'cursos';

    protected $fillable = [
        'titulo',
        'descripcion',
        'objetivos',
    ];

    public function institucion(): BelongsTo
    {
        return $this->belongsTo(Institucion::class, 'institucion_id');
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(CategoriaCurso::class, 'categoria_id');
    }

    public function ediciones(): HasMany
    {
        return $this->hasMany(Edicion::class, 'curso_id');
    }

    public function modulos(): HasMany
    {
        return $this->hasMany(ModuloCurso::class, 'curso_id');
    }

    public function actividades(): HasMany
    {
        return $this->hasMany(Actividad::class, 'curso_id');
    }

    public function requisitos(): BelongsToMany
    {
        return $this->belongsToMany(Requisito::class, 'curso_requisito', 'curso_id', 'requisito_id')
            ->using(CursoRequisito::class)
            ->withPivot('obligatorio');
    }

}
