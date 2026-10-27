<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ModuloCurso extends Model
{
    use SoftDeletes;

    protected $table = 'modulos_curso';

    protected $fillable = [
        'titulo',
        'descripcion',
        'orden',
        'obligatorio',
    ];

    protected function casts(): array
    {
        return [
            'orden' => 'integer',
            'obligatorio' => 'boolean',
        ];
    }

    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class, 'curso_id');
    }

    public function materiales(): HasMany
    {
        return $this->hasMany(MaterialCurso::class, 'modulo_id');
    }

    public function actividades(): HasMany
    {
        return $this->hasMany(Actividad::class, 'modulo_id');
    }

}
