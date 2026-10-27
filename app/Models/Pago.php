<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Pago extends Model
{
    protected $table = 'pagos';

    // Importes, referencias y verificación se asignan explícitamente.
    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'monto' => 'decimal:2',
            'monto_reembolsado' => 'decimal:2',
            'fecha_pago' => 'datetime',
        ];
    }

    public function inscripcion(): BelongsTo
    {
        return $this->belongsTo(Inscripcion::class, 'inscripcion_id');
    }

    public function verificador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verificado_por');
    }

}
