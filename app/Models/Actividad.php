<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Actividad extends Model
{
    use SoftDeletes;

    protected $table = 'actividades';

    protected $fillable = [
        'titulo',
        'tipo',
        'puntaje_maximo',
        'ponderacion',
        'intentos_maximos',
        'plazo_dias',
    ];

    protected function casts(): array
    {
        return [
            'puntaje_maximo' => 'decimal:2',
            'ponderacion' => 'decimal:2',
            'intentos_maximos' => 'integer',
            'plazo_dias' => 'integer',
            'configuracion_json' => 'array',
        ];
    }

    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class, 'curso_id');
    }

    public function modulo(): BelongsTo
    {
        return $this->belongsTo(ModuloCurso::class, 'modulo_id');
    }

    public function grupo(): BelongsTo
    {
        return $this->belongsTo(Grupo::class, 'grupo_id');
    }

    public function entregas(): HasMany
    {
        return $this->hasMany(Entrega::class, 'actividad_id');
    }

}
