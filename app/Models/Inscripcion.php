<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Inscripcion extends Model
{
    protected $table = 'inscripciones';

    // Cupo, precio, pertenencia y estado requieren asignación explícita.
    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'precio_acordado' => 'decimal:2',
            'reserva_vence_at' => 'datetime',
            'fecha_inscripcion' => 'datetime',
            'fecha_cancelacion' => 'datetime',
        ];
    }

    public function participante(): BelongsTo
    {
        return $this->belongsTo(Participante::class, 'participante_id');
    }

    public function grupo(): BelongsTo
    {
        return $this->belongsTo(Grupo::class, 'grupo_id');
    }

    public function entregas(): HasMany
    {
        return $this->hasMany(Entrega::class, 'inscripcion_id');
    }

    public function asistencias(): HasMany
    {
        return $this->hasMany(Asistencia::class, 'inscripcion_id');
    }

    public function pagos(): HasMany
    {
        return $this->hasMany(Pago::class, 'inscripcion_id');
    }

    public function constancia(): HasOne
    {
        return $this->hasOne(Constancia::class, 'inscripcion_id');
    }

}
