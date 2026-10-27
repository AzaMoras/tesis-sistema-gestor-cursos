<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Evaluacion extends Model
{
    protected $table = 'evaluaciones';

    protected $fillable = [
        'retroalimentacion',
    ];

    protected function casts(): array
    {
        return [
            'calificacion' => 'decimal:2',
            'fecha_evaluacion' => 'datetime',
        ];
    }

    public function entrega(): BelongsTo
    {
        return $this->belongsTo(Entrega::class, 'entrega_id');
    }

    public function calificador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'calificado_por');
    }

}
