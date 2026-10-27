<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Constancia extends Model
{
    protected $table = 'constancias';

    // La emisión y sus resultados se asignan explícitamente.
    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'calificacion_final' => 'decimal:2',
            'porcentaje_asistencia' => 'decimal:2',
            'fecha_emision' => 'datetime',
        ];
    }

    public function inscripcion(): BelongsTo
    {
        return $this->belongsTo(Inscripcion::class, 'inscripcion_id');
    }

}
