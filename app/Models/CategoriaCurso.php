<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CategoriaCurso extends Model
{
    use SoftDeletes;

    protected $table = 'categorias_curso';

    protected $fillable = [
        'nombre',
        'slug',
        'descripcion',
    ];

    public function institucion(): BelongsTo
    {
        return $this->belongsTo(Institucion::class, 'institucion_id');
    }

    public function cursos(): HasMany
    {
        return $this->hasMany(Curso::class, 'categoria_id');
    }

}
