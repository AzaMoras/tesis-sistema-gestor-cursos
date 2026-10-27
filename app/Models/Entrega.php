<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Entrega extends Model
{
    protected $table = 'entregas';

    protected $fillable = [
        'respuestas_json',
    ];

    protected function casts(): array
    {
        return [
            'numero_intento' => 'integer',
            'respuestas_json' => 'array',
            'fecha_entrega' => 'datetime',
        ];
    }

    public function actividad(): BelongsTo
    {
        return $this->belongsTo(Actividad::class, 'actividad_id');
    }

    public function inscripcion(): BelongsTo
    {
        return $this->belongsTo(Inscripcion::class, 'inscripcion_id');
    }

    public function evaluacion(): HasOne
    {
        return $this->hasOne(Evaluacion::class, 'entrega_id');
    }

}
