<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Inactivo extends Model
{
    protected $table = 'inactivos';

    // El historial de bajas y restauraciones se asigna explícitamente.
    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'desactivado_en' => 'datetime',
            'restaurado_en' => 'datetime',
        ];
    }

    public function institucion(): BelongsTo
    {
        return $this->belongsTo(Institucion::class, 'institucion_id');
    }

    public function desactivador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'desactivado_por');
    }

    public function restaurador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'restaurado_por');
    }

}
