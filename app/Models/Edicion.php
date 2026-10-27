<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Edicion extends Model
{
    use SoftDeletes;

    protected $table = 'ediciones';

    protected $fillable = [
        'clave',
        'modalidad',
        'fecha_inicio',
        'fecha_fin',
        'cierre_inscripciones_at',
        'asistencia_minima',
        'calificacion_minima',
    ];

    protected function casts(): array
    {
        return [
            'fecha_inicio' => 'date',
            'fecha_fin' => 'date',
            'cierre_inscripciones_at' => 'datetime',
            'precio' => 'decimal:2',
            'asistencia_minima' => 'decimal:2',
            'calificacion_minima' => 'decimal:2',
        ];
    }

    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class, 'curso_id');
    }

    public function grupos(): HasMany
    {
        return $this->hasMany(Grupo::class, 'edicion_id');
    }

}
